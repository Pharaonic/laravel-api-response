<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response as IlluminateResponse;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Pharaonic\Laravel\ApiResponse\Support\Pagination;
use Pharaonic\Laravel\ApiResponse\Support\Profiles;
use Pharaonic\Laravel\ApiResponse\Support\RequestId;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * An API response. success() and error() start one, the fluent methods set
 * its parts in any order, and Laravel (or response()) builds the HTTP response.
 * Nothing is resolved before then.
 */
final class Response implements Responsable
{
    private const NOTIFICATION_TYPES = ['success', 'info', 'warning', 'error'];

    /**
     * The request attribute that holds the metadata shared with shareMeta().
     */
    private const SHARED_META = 'pharaonic.api-response.meta';

    private ?string $message = null;

    /** @var array<array-key, mixed>|null */
    private ?array $errors = null;

    /** @var array<array-key, mixed> */
    private array $meta = [];

    /** @var list<array{type: string, message: string}> */
    private array $notifications = [];

    private ?string $profile = null;

    private bool $seo = false;

    private bool $requestId = false;

    /** @var list<string>|bool|null null: the configured default, true: every key, false: none, list: only these keys */
    private array|bool|null $queryString = null;

    private function __construct(private bool $error, private int $status, private mixed $data)
    {
    }

    /**
     * A success (200) carrying the data: a value, an array, a model, a resource or a paginator.
     */
    public static function success(mixed $data = null): self
    {
        return new self(false, 200, $data);
    }

    /**
     * An error with a status of 400 to 599.
     */
    public static function error(string $message, int $status = 400): self
    {
        return (new self(true, 400, null))->message($message)->status($status);
    }

    /**
     * Share metadata with every response of the current request, such as from a
     * middleware. It lives on the request, so it never reaches another request.
     * A resource's metadata and a response's own meta() replace shared keys.
     *
     * @param  string|array<array-key, mixed>  $key
     */
    public static function shareMeta(string|array $key, mixed $value = null): void
    {
        $request = Container::getInstance()->make('request');

        if (! $request instanceof Request) {
            throw new LogicException('shareMeta() needs the current request.');
        }

        $values = self::metaValues($key, $value);
        $shared = $request->attributes->get(self::SHARED_META);

        $request->attributes->set(self::SHARED_META, array_replace(is_array($shared) ? $shared : [], $values));
    }

    public function message(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * A success keeps a 2xx status and an error a 4xx or 5xx one. 204 and 205 have no body.
     */
    public function status(int $status): self
    {
        if (! $this->error && ($status < 200 || $status > 299)) {
            throw new InvalidArgumentException("A success needs a 2xx status, {$status} given; use Response::error().");
        }

        if ($this->error && ($status < 400 || $status > 599)) {
            throw new InvalidArgumentException("An error needs a 4xx or 5xx status, {$status} given.");
        }

        $this->status = $status;

        return $this;
    }

    /**
     * The errors of an error response, such as the messages of each field.
     *
     * @param  array<array-key, mixed>|null  $errors
     */
    public function errors(?array $errors): self
    {
        if (! $this->error) {
            throw new InvalidArgumentException('A success cannot carry errors; use Response::error().');
        }

        $this->errors = $errors;

        return $this;
    }

    /**
     * Add metadata: one key and value, or an array of them. Later values replace
     * earlier ones, and replace what a resource's with() or additional() sends.
     *
     * @param  string|array<array-key, mixed>  $key
     */
    public function meta(string|array $key, mixed $value = null): self
    {
        $this->meta = array_replace($this->meta, self::metaValues($key, $value));

        return $this;
    }

    /**
     * @param  string|array<array-key, mixed>  $key
     * @return array<array-key, mixed>
     */
    private static function metaValues(string|array $key, mixed $value): array
    {
        $values = is_array($key) ? $key : [$key => $value];

        if (array_key_exists('pagination', $values)) {
            throw new InvalidArgumentException('The meta key [pagination] belongs to the package.');
        }

        return $values;
    }

    /**
     * Add a notification for the client to show. Notifications keep the order they were added in.
     */
    public function notify(string $message, string $type = 'info'): self
    {
        if (! in_array($type, self::NOTIFICATION_TYPES, true)) {
            throw new InvalidArgumentException("The notification type [{$type}] must be one of: ".implode(', ', self::NOTIFICATION_TYPES).'.');
        }

        $this->notifications[] = ['type' => $type, 'message' => $message];

        return $this;
    }

    /**
     * Shape this response with a profile registered in pharaonic.api-response.profiles instead of the default one.
     */
    public function profile(string $name): self
    {
        $this->profile = $name;

        return $this;
    }

    /**
     * Add the page's tags from pharaonic/laravel-seo. A response never carries them without this call.
     */
    public function withSEO(): self
    {
        $this->seo = true;

        return $this;
    }

    /**
     * Add the request ID to the body even when the profile leaves it out.
     */
    public function withRequestId(): self
    {
        $this->requestId = true;

        return $this;
    }

    /**
     * Keep the request's query string in pagination links: every key but the
     * sensitive ones, or only the given keys.
     *
     * @param  list<string>|null  $only
     */
    public function withQueryString(?array $only = null): self
    {
        $this->queryString = $only ?? true;

        return $this;
    }

    /**
     * Keep only the page or cursor parameter in pagination links.
     */
    public function withoutQueryString(): self
    {
        $this->queryString = false;

        return $this;
    }

    /**
     * Build the HTTP response for the current request.
     */
    public function response(): SymfonyResponse
    {
        $request = Container::getInstance()->make('request');

        if (! $request instanceof Request) {
            throw new LogicException('response() needs the current request; call toResponse($request) instead.');
        }

        return $this->toResponse($request);
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): SymfonyResponse
    {
        if ($this->status === 204 || $this->status === 205) {
            return new IlluminateResponse('', $this->status);
        }

        $container = Container::getInstance();
        $config = $container->make('config');

        if (! $config instanceof Repository) {
            throw new LogicException('The container has no configuration repository.');
        }

        $profile = Profiles::resolve($container, $config, $this->profile);
        $fields = $profile['fields'];
        $body = [$fields['success'] => ! $this->error, $fields['message'] => $this->message];
        $shared = $request->attributes->get(self::SHARED_META);
        $shared = is_array($shared) ? $shared : [];
        $meta = array_replace($shared, $this->meta);

        if ($this->error) {
            $body[$fields['errors']] = $this->errors;
        } else {
            [$data, $meta] = $this->resolve($request, $config, $shared);
            $body[$fields['data']] = $data;
        }

        $body[$fields['meta']] = $meta === [] ? null : $meta;

        if ($this->notifications !== []) {
            $body[$fields['notifications']] = $this->notifications;
        }

        if ($this->seo) {
            $body[$fields['seo']] = $this->seoTags();
        }

        $requestId = RequestId::of($request);

        if ($requestId !== null && ($this->requestId || $profile['request_id_in_body'])) {
            $body[$fields['request_id']] = $requestId;
        }

        $response = new JsonResponse($body, $this->status);

        if ($this->data instanceof JsonResource) {
            $this->data->withResponse($request, $response);
        }

        return $response;
    }

    /**
     * The data to send and its metadata: the shared metadata, a resource's
     * with() and additional(), then meta(), then the pagination of a paginator
     * or a collection over one.
     *
     * @param  array<array-key, mixed>  $shared
     * @return array{0: mixed, 1: array<array-key, mixed>}
     */
    private function resolve(Request $request, Repository $config, array $shared): array
    {
        $data = $this->data;
        $meta = $shared;

        if ($data instanceof JsonResource) {
            $meta = array_replace($meta, $this->resourceMeta($data->with($request)), $this->resourceMeta($data->additional));
            $paginator = $data instanceof ResourceCollection ? $data->resource : null;
            $data = $data->resolve($request);
        } else {
            $paginator = $data;
        }

        $meta = array_replace($meta, $this->meta);

        if (is_object($paginator)) {
            $sensitive = $config->get('pharaonic.api-response.pagination.sensitive_query', []);
            $pagination = new Pagination(
                $request->query->all(),
                $this->queryString ?? (bool) $config->get('pharaonic.api-response.pagination.query_string', true),
                is_array($sensitive) ? array_values(array_filter($sensitive, 'is_string')) : []
            );

            if ($page = $pagination->resolve($paginator)) {
                $data = $this->data instanceof JsonResource ? $data : $page['items'];
                $meta['pagination'] = $page['meta'];
            }
        }

        return [$this->normalize($data), $meta];
    }

    /**
     * A resource's "meta" key is merged in; its other keys keep their own names.
     *
     * @param  array<array-key, mixed>  $extra
     * @return array<array-key, mixed>
     */
    private function resourceMeta(array $extra): array
    {
        $meta = isset($extra['meta']) && is_array($extra['meta']) ? $extra['meta'] : [];
        unset($extra['meta']);

        return array_merge($extra, $meta);
    }

    /**
     * Serializes what json_encode() cannot, the way Laravel's JsonResponse does.
     */
    private function normalize(mixed $data): mixed
    {
        if ($data instanceof JsonSerializable) {
            return $data;
        }

        if ($data instanceof Jsonable) {
            return json_decode($data->toJson(), true, 512, JSON_THROW_ON_ERROR);
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        return $data;
    }

    /**
     * Reads seo()->toArray() of pharaonic/laravel-seo (N.1 or later on each line), without naming its classes.
     *
     * @return array<array-key, mixed>
     */
    private function seoTags(): array
    {
        $container = Container::getInstance();
        $manager = $container->bound('pharaonic-seo') ? $container->make('pharaonic-seo') : null;
        $export = is_object($manager) ? [$manager, 'toArray'] : null;

        if ($export === null || ! is_callable($export)) {
            throw new LogicException('withSEO() needs pharaonic/laravel-seo with seo()->toArray(); install it with "composer require pharaonic/laravel-seo".');
        }

        $tags = $export();

        return is_array($tags) ? $tags : [];
    }
}
