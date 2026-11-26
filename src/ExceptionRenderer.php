<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders the exceptions of API requests in the error envelope. The
 * application registers it with its exception handler; it only renders, so
 * Laravel still reports. It returns null, leaving the exception to Laravel,
 * for web requests, for exceptions that carry their own response, and for
 * unexpected exceptions while app.debug is on.
 */
final class ExceptionRenderer
{
    /**
     * Laravel's own English messages, matched exactly, and the line that translates each one.
     */
    private const FRAMEWORK = [
        'Unauthenticated.' => 'unauthenticated',
        'This action is unauthorized.' => 'forbidden',
        'The given data was invalid.' => 'validation',
        'Too Many Attempts.' => 'too_many_attempts',
        'CSRF token mismatch.' => 'page_expired',
    ];

    public function __invoke(Throwable $exception, Request $request): ?SymfonyResponse
    {
        $config = Container::getInstance()->make('config');

        if (! $config instanceof Repository || ! $this->isApiRequest($request, $config) || $this->rendersItself($exception)) {
            return null;
        }

        $error = $this->map($exception);

        if ($error === null) {
            if ($config->get('app.debug')) {
                return null;
            }

            $error = Response::error($this->message('server_error', 'Server Error.'), 500);
        }

        $response = $error->toResponse($request);

        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->add($exception->getHeaders());
        }

        return $response;
    }

    private function isApiRequest(Request $request, Repository $config): bool
    {
        $paths = $config->get('pharaonic.api-response.exceptions.paths', []);
        $paths = is_array($paths) ? array_values(array_filter($paths, 'is_string')) : [];

        return $request->expectsJson() || ($paths !== [] && $request->is(...$paths));
    }

    private function rendersItself(Throwable $exception): bool
    {
        return $exception instanceof HttpResponseException
            || $exception instanceof Responsable
            || ($exception instanceof ValidationException && $exception->response !== null)
            || method_exists($exception, 'render');
    }

    /**
     * Each exception Laravel knows, raw (as the handler's render() sees it on
     * Laravel 6 and 7) or prepared into an HTTP exception (as renderable()
     * and withExceptions() callbacks see it from Laravel 8).
     */
    private function map(Throwable $exception): ?Response
    {
        if ($exception instanceof ValidationException) {
            $status = $exception->status >= 400 && $exception->status <= 599 ? $exception->status : 422;

            return Response::error($this->framework($exception->getMessage()), $status)->errors($exception->errors());
        }

        if ($exception instanceof AuthenticationException) {
            return Response::error($this->framework($exception->getMessage()) ?: $this->message('unauthenticated', 'Unauthenticated.'), 401);
        }

        if ($exception instanceof AuthorizationException) {
            return Response::error($this->framework($exception->getMessage()) ?: $this->message('forbidden', 'This action is unauthorized.'), 403);
        }

        // From Laravel 8 the handler passes these as the HTTP exception it made of them.
        $cause = $exception instanceof HttpExceptionInterface ? ($exception->getPrevious() ?? $exception) : $exception;

        // Laravel's message names the model class and the keys, so it is never sent.
        if ($cause instanceof ModelNotFoundException) {
            return Response::error($this->message('not_found', 'Not Found.'), 404);
        }

        if ($cause instanceof TokenMismatchException) {
            return Response::error($this->message('page_expired', 'CSRF token mismatch.'), 419);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            if ($status < 400 || $status > 599) {
                return null;
            }

            $text = SymfonyResponse::$statusTexts[$status] ?? null;
            $message = $exception->getMessage() === ''
                ? $this->message('status.'.$status, is_string($text) ? $text : 'Error')
                : $this->framework($exception->getMessage());

            return Response::error($message, $status);
        }

        return null;
    }

    /**
     * A line of api-response::messages in the current locale, or its English text when the line is missing.
     */
    private function message(string $key, string $fallback): string
    {
        $container = Container::getInstance();
        $translator = $container->bound('translator') ? $container->make('translator') : null;
        $line = 'api-response::messages.'.$key;
        $value = $translator instanceof Translator ? $translator->get($line) : $line;

        return is_string($value) && $value !== $line ? $value : $fallback;
    }

    /**
     * Translate a message Laravel set in English; any other message is the application's own and is kept.
     */
    private function framework(string $message): string
    {
        return isset(self::FRAMEWORK[$message]) ? $this->message(self::FRAMEWORK[$message], $message) : $message;
    }
}
