<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Contracts\ExceptionMapper;
use Pharaonic\Laravel\ApiResponse\Support\Messages;
use Pharaonic\Laravel\ApiResponse\Support\Settings;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders exceptions of API requests as API responses. Register it with the
 * application's exception handler; it only renders, so Laravel still reports.
 * It returns null, leaving the exception to Laravel, for requests that are not
 * API requests and for exceptions that carry their own response.
 */
final class ExceptionRenderer
{
    /**
     * The code of each HTTP status, fixed so it never follows a framework's status text.
     * The names are those of RFC 9110; 401 is UNAUTHENTICATED, as Response::unauthorized() sends.
     */
    private const CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        402 => 'PAYMENT_REQUIRED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        406 => 'NOT_ACCEPTABLE',
        407 => 'PROXY_AUTHENTICATION_REQUIRED',
        408 => 'REQUEST_TIMEOUT',
        409 => 'CONFLICT',
        410 => 'GONE',
        411 => 'LENGTH_REQUIRED',
        412 => 'PRECONDITION_FAILED',
        413 => 'CONTENT_TOO_LARGE',
        414 => 'URI_TOO_LONG',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        416 => 'RANGE_NOT_SATISFIABLE',
        417 => 'EXPECTATION_FAILED',
        418 => 'I_M_A_TEAPOT',
        419 => 'PAGE_EXPIRED',
        421 => 'MISDIRECTED_REQUEST',
        422 => 'UNPROCESSABLE_CONTENT',
        423 => 'LOCKED',
        424 => 'FAILED_DEPENDENCY',
        425 => 'TOO_EARLY',
        426 => 'UPGRADE_REQUIRED',
        428 => 'PRECONDITION_REQUIRED',
        429 => 'TOO_MANY_REQUESTS',
        431 => 'REQUEST_HEADER_FIELDS_TOO_LARGE',
        451 => 'UNAVAILABLE_FOR_LEGAL_REASONS',
        500 => 'SERVER_ERROR',
        501 => 'NOT_IMPLEMENTED',
        502 => 'BAD_GATEWAY',
        503 => 'SERVICE_UNAVAILABLE',
        504 => 'GATEWAY_TIMEOUT',
        505 => 'HTTP_VERSION_NOT_SUPPORTED',
        506 => 'VARIANT_ALSO_NEGOTIATES',
        507 => 'INSUFFICIENT_STORAGE',
        508 => 'LOOP_DETECTED',
        510 => 'NOT_EXTENDED',
        511 => 'NETWORK_AUTHENTICATION_REQUIRED',
    ];

    public function __invoke(Throwable $exception, Request $request): ?SymfonyResponse
    {
        $container = Container::getInstance();
        $config = $container->make('config');

        if (! $config instanceof Repository) {
            return null;
        }

        $settings = new Settings($config);

        if (! $this->isApiRequest($request, $settings) && ! $this->isCodedHttpException($exception)) {
            return null;
        }

        $response = $this->mapped($exception, $request, $settings, $container);

        if ($response === null && $this->carriesOwnResponse($exception)) {
            return null;
        }

        return ($response ?? $this->builtIn($exception, (bool) $config->get('app.debug')))->toResponse($request);
    }

    private function isApiRequest(Request $request, Settings $settings): bool
    {
        $paths = $settings->exceptionPaths();

        return $request->expectsJson() || ($paths !== [] && $request->is(...$paths));
    }

    /**
     * An HTTP exception with an error code belongs to an API, such as a refusal of
     * pharaonic/laravel-api-idempotency, so it is rendered whatever the request accepts.
     */
    private function isCodedHttpException(Throwable $exception): bool
    {
        return $exception instanceof HttpExceptionInterface && $this->errorCode($exception) !== null;
    }

    /**
     * Whether Laravel should send the exception's own response. An HTTP exception
     * with an error code is rendered in the envelope even when it has render():
     * the envelope carries its status, message, code and headers.
     */
    private function carriesOwnResponse(Throwable $exception): bool
    {
        if ($exception instanceof HttpResponseException
            || $exception instanceof Responsable
            || ($exception instanceof ValidationException && $exception->response !== null)) {
            return true;
        }

        return method_exists($exception, 'render') && ! $this->isCodedHttpException($exception);
    }

    /**
     * The response of the first configured mapper that maps the exception. Mappers win over render().
     */
    private function mapped(Throwable $exception, Request $request, Settings $settings, Container $container): ?Response
    {
        $mappers = $settings->exceptionMappers();

        foreach ($this->lineage($exception) as $type) {
            if (! isset($mappers[$type])) {
                continue;
            }

            $mapper = $container->make($mappers[$type]);

            if (! $mapper instanceof ExceptionMapper) {
                throw new InvalidArgumentException("[{$mappers[$type]}] must implement ".ExceptionMapper::class.'.');
            }

            if ($response = $mapper->map($exception, $request)) {
                return $response;
            }
        }

        return null;
    }

    /**
     * The code an exception names through a public errorCode() method, as the
     * exceptions of pharaonic/laravel-api-versioning and laravel-api-idempotency do,
     * in upper case like every code the package sends.
     */
    private function errorCode(Throwable $exception): ?string
    {
        if (! is_callable([$exception, 'errorCode'])) {
            return null;
        }

        $code = $exception->errorCode();

        return is_string($code) && preg_match('/^[A-Za-z0-9_.:\-]+$/', $code) ? strtoupper($code) : null;
    }

    /**
     * The message an exception translates itself through a public localizedMessage() method.
     */
    private function localizedMessage(Throwable $exception): ?string
    {
        if (! is_callable([$exception, 'localizedMessage'])) {
            return null;
        }

        $message = $exception->localizedMessage();

        return is_string($message) && $message !== '' ? $message : null;
    }

    /**
     * The exception's class, its parents from the closest, then its interfaces.
     *
     * @return list<string>
     */
    private function lineage(Throwable $exception): array
    {
        $types = [get_class($exception)];

        foreach (class_parents($exception) ?: [] as $parent) {
            $types[] = $parent;
        }

        foreach (class_implements($exception) ?: [] as $interface) {
            $types[] = $interface;
        }

        return $types;
    }

    private function builtIn(Throwable $exception, bool $debug): Response
    {
        if ($exception instanceof ValidationException) {
            return Response::validationError($exception->errors(), Messages::framework($exception->getMessage()))
                ->status($exception->status >= 400 ? $exception->status : 422);
        }

        if ($exception instanceof AuthenticationException) {
            return Response::unauthorized($exception->getMessage() === '' ? null : Messages::framework($exception->getMessage()));
        }

        if ($exception instanceof AuthorizationException) {
            return Response::forbidden($exception->getMessage() === '' ? null : Messages::framework($exception->getMessage()));
        }

        if ($exception instanceof ModelNotFoundException) {
            $response = Response::notFound();

            if ($debug) {
                $response->meta('debug', array_merge(
                    ['model' => $exception->getModel(), 'ids' => $exception->getIds()],
                    $this->debug($exception)
                ));
            }

            return $response;
        }

        if ($exception instanceof TokenMismatchException) {
            return Response::error(Messages::get('page_expired', 'CSRF token mismatch.'), 419)->code('PAGE_EXPIRED');
        }

        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() >= 400 && $exception->getStatusCode() <= 599) {
            $status = $exception->getStatusCode();
            $text = SymfonyResponse::$statusTexts[$status] ?? null;
            $text = is_string($text) ? $text : 'Error';
            $code = $this->errorCode($exception) ?? self::CODES[$status] ?? 'HTTP_'.$status;

            $message = $this->localizedMessage($exception) ?? ($exception->getMessage() === ''
                ? Messages::get('status.'.$status, $text)
                : Messages::framework($exception->getMessage()));

            return Response::error($message, $status)
                ->code($code)
                ->withHeaders($this->headers($exception->getHeaders()));
        }

        $response = Response::error(Messages::get('server_error', 'Server Error.'), 500)->code('SERVER_ERROR');

        if ($debug) {
            $response->meta('debug', $this->debug($exception));
        }

        return $response;
    }

    /**
     * The details meta.debug carries with app.debug on. Trace frames keep no arguments.
     *
     * @return array<string, mixed>
     */
    private function debug(Throwable $exception): array
    {
        return [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_map(
                fn (array $frame) => array_intersect_key($frame, array_flip(['file', 'line', 'function', 'class'])),
                $exception->getTrace()
            ),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        $valid = [];

        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }

            if (is_string($name) && is_scalar($value)) {
                $valid[$name] = (string) $value;
            }
        }

        return $valid;
    }
}
