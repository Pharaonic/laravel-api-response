<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Support\Finalizer;
use Pharaonic\Laravel\ApiResponse\Support\Headers;
use Pharaonic\Laravel\ApiResponse\Support\Messages;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A request-local API response. The static constructors start one, the fluent
 * methods collect its state in any order, and Laravel (or response()) turns it
 * into an HTTP response. Nothing is resolved before then.
 */
class Response implements Responsable
{
    use Macroable {
        macro as private registerMacro;
    }

    private bool $error;

    private int $status;

    private bool $hasData = false;

    private mixed $data = null;

    private ?string $message = null;

    private ?string $code = null;

    /** @var array<array-key, mixed>|null */
    private ?array $errors = null;

    /** @var array<array-key, mixed> */
    private array $meta = [];

    /** @var list<array{type: string, message: string, title: ?string, duration: ?int, data: array<array-key, mixed>}> */
    private array $notifications = [];

    /** @var array<string, string> */
    private array $headers = [];

    private ?string $profile = null;

    private bool $seo = false;

    private ?bool $requestId = null;

    /** @var list<string>|bool|null null: the configured default, true: every allowed key, false: none, list: only these keys */
    private array|bool|null $queryString = null;

    private bool $pagination = true;

    /** @var list<callable(mixed): mixed> */
    private array $transformers = [];

    final private function __construct(bool $error, int $status)
    {
        $this->error = $error;
        $this->status($status);
    }

    /**
     * A successful response (200). Called without an argument it sends no data field.
     */
    public static function success(mixed $data = null): static
    {
        return self::make(false, 200, func_num_args() > 0, $data);
    }

    /**
     * A created response (201).
     */
    public static function created(mixed $data = null): static
    {
        return self::make(false, 201, func_num_args() > 0, $data);
    }

    /**
     * An accepted response (202).
     */
    public static function accepted(mixed $data = null): static
    {
        return self::make(false, 202, func_num_args() > 0, $data);
    }

    /**
     * A bodyless 204 response: only its headers are sent.
     */
    public static function noContent(): static
    {
        return self::make(false, 204, false, null);
    }

    /**
     * An error response. The status must be 400 or more.
     */
    public static function error(string $message, int $status = 400): static
    {
        return self::make(true, $status, false, null)->message($message);
    }

    /**
     * A 401 response. Without a message it sends api-response::messages.unauthenticated.
     */
    public static function unauthorized(?string $message = null): static
    {
        return static::error($message ?? Messages::get('unauthenticated', 'Unauthenticated.'), 401)->code('UNAUTHENTICATED');
    }

    /**
     * A 403 response. Without a message it sends api-response::messages.forbidden.
     */
    public static function forbidden(?string $message = null): static
    {
        return static::error($message ?? Messages::get('forbidden', 'This action is unauthorized.'), 403)->code('FORBIDDEN');
    }

    /**
     * A 404 response. Without a message it sends api-response::messages.not_found.
     */
    public static function notFound(?string $message = null): static
    {
        return static::error($message ?? Messages::get('not_found', 'Not Found.'), 404)->code('NOT_FOUND');
    }

    /**
     * A 422 response with the errors of each field. Without a message it sends api-response::messages.validation.
     *
     * @param  array<array-key, mixed>  $errors
     */
    public static function validationError(array $errors, ?string $message = null): static
    {
        return static::error($message ?? Messages::get('validation', 'The given data was invalid.'), 422)->code('VALIDATION_ERROR')->errors($errors);
    }

    private static function make(bool $error, int $status, bool $hasData, mixed $data): static
    {
        $response = new static($error, $status);

        if ($hasData) {
            $response->data($data);
        }

        return $response;
    }

    /**
     * Register a macro. A name the class already defines is refused, because the method would always win.
     */
    public static function macro(string $name, object|callable $macro): void
    {
        if (method_exists(static::class, $name)) {
            throw new InvalidArgumentException("Response already defines [{$name}]; choose another macro name.");
        }

        self::registerMacro($name, $macro);
    }

    /**
     * Forget every registered macro.
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Set the payload of a success. An error carries its details in errors() and meta() instead.
     */
    public function data(mixed $data): static
    {
        if ($this->error) {
            throw new InvalidArgumentException('An error response cannot carry data; use errors() or meta().');
        }

        $this->hasData = true;
        $this->data = $data;

        return $this;
    }

    public function message(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Set the HTTP status. A success keeps a status below 400 and an error one of 400 or more.
     */
    public function status(int $status): static
    {
        if ($status < 200 || $status > 599) {
            throw new InvalidArgumentException("The HTTP status must be between 200 and 599, {$status} given.");
        }

        if ($this->error && $status < 400) {
            throw new InvalidArgumentException("An error response needs a status of 400 or more, {$status} given.");
        }

        if (! $this->error && $status >= 400) {
            throw new InvalidArgumentException("A success response needs a status below 400, {$status} given; use Response::error().");
        }

        $this->status = $status;

        return $this;
    }

    /**
     * Set the application error code: a stable, machine-readable identifier such as INSUFFICIENT_BALANCE.
     */
    public function code(?string $code): static
    {
        if ($code !== null && ! preg_match('/^[A-Za-z0-9_.:\-]+$/', $code)) {
            throw new InvalidArgumentException("The response code [{$code}] may only contain letters, digits, '_', '.', ':' and '-'.");
        }

        $this->code = $code;

        return $this;
    }

    /**
     * Set the errors of an error response, such as the messages of each field.
     *
     * @param  array<array-key, mixed>|null  $errors
     */
    public function errors(?array $errors): static
    {
        if ($errors !== null && ! $this->error) {
            throw new InvalidArgumentException('A success response cannot carry errors; use Response::error().');
        }

        $this->errors = $errors;

        return $this;
    }

    /**
     * Add metadata: one key and value, or an array of them. Later values replace earlier ones.
     *
     * @param  string|array<array-key, mixed>  $key
     */
    public function meta(string|array $key, mixed $value = null): static
    {
        $values = is_array($key) ? $key : [$key => $value];

        foreach ($values as $name => $item) {
            if ($name === 'pagination') {
                throw new InvalidArgumentException('The meta key [pagination] belongs to the package.');
            }

            $this->meta[$name] = $item;
        }

        return $this;
    }

    /**
     * Add a notification for the client to display. Notifications keep the order they were added in.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function notify(string $message, ?string $type = null, ?string $title = null, ?int $duration = null, array $data = []): static
    {
        if ($duration !== null && $duration < 0) {
            throw new InvalidArgumentException('A notification duration cannot be negative.');
        }

        $this->notifications[] = [
            'type' => $type ?? '',
            'message' => $message,
            'title' => $title,
            'duration' => $duration,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Use a registered profile for this response, whatever the route or the Accept header say.
     */
    public function profile(string $name): static
    {
        $this->profile = $name;

        return $this;
    }

    /**
     * Add the SEO tags of pharaonic/laravel-seo. A response never carries them without this call.
     */
    public function withSEO(): static
    {
        $this->seo = true;

        return $this;
    }

    public function withRequestId(): static
    {
        $this->requestId = true;

        return $this;
    }

    public function withoutRequestId(): static
    {
        $this->requestId = false;

        return $this;
    }

    /**
     * Keep the query string in pagination links: every key but the denied ones, or only the given keys.
     *
     * @param  list<string>|null  $only
     */
    public function withQueryString(?array $only = null): static
    {
        $this->queryString = $only ?? true;

        return $this;
    }

    /**
     * Drop the query string from pagination links, except the page or cursor parameter.
     */
    public function withoutQueryString(): static
    {
        $this->queryString = false;

        return $this;
    }

    /**
     * Send a paginator's items without the pagination metadata.
     */
    public function withoutPagination(): static
    {
        $this->pagination = false;

        return $this;
    }

    public function header(string $name, string $value): static
    {
        Headers::validate($name, $value);

        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }

        return $this;
    }

    /**
     * Run the callback when the value (or the value the closure returns) is truthy, or the default otherwise.
     *
     * @param  callable(static, mixed): mixed  $callback
     * @param  (callable(static, mixed): mixed)|null  $default
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if ($value) {
            $callback($this, $value);
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Run the callback when the value (or the value the closure returns) is falsy, or the default otherwise.
     *
     * @param  callable(static, mixed): mixed  $callback
     * @param  (callable(static, mixed): mixed)|null  $default
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if (! $value) {
            $callback($this, $value);
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Change the payload after resources and paginators are resolved and before the envelope is built.
     *
     * @param  callable(mixed): mixed  $callback
     */
    public function transform(callable $callback): static
    {
        $this->transformers[] = $callback;

        return $this;
    }

    /**
     * Build the HTTP response for the current request.
     */
    public function response(): SymfonyResponse
    {
        $request = Container::getInstance()->make('request');

        return $this->toResponse($request instanceof Request ? $request : Request::capture());
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): SymfonyResponse
    {
        return (new Finalizer(Container::getInstance()))->finalize($this->state(), $request);
    }

    /**
     * @return array{error: bool, status: int, hasData: bool, data: mixed, message: ?string, code: ?string, errors: array<array-key, mixed>|null, meta: array<array-key, mixed>, notifications: list<array{type: string, message: string, title: ?string, duration: ?int, data: array<array-key, mixed>}>, headers: array<string, string>, profile: ?string, seo: bool, requestId: ?bool, queryString: list<string>|bool|null, pagination: bool, transformers: list<callable(mixed): mixed>}
     */
    private function state(): array
    {
        return [
            'error' => $this->error,
            'status' => $this->status,
            'hasData' => $this->hasData,
            'data' => $this->data,
            'message' => $this->message,
            'code' => $this->code,
            'errors' => $this->errors,
            'meta' => $this->meta,
            'notifications' => $this->notifications,
            'headers' => $this->headers,
            'profile' => $this->profile,
            'seo' => $this->seo,
            'requestId' => $this->requestId,
            'queryString' => $this->queryString,
            'pagination' => $this->pagination,
            'transformers' => $this->transformers,
        ];
    }
}
