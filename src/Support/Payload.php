<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use JsonSerializable;
use Pharaonic\Laravel\ApiResponse\Contracts\PaginationAdapter;
use Pharaonic\Laravel\ApiResponse\Pagination\CursorAdapter;
use Pharaonic\Laravel\ApiResponse\Pagination\LengthAwareAdapter;
use Pharaonic\Laravel\ApiResponse\Pagination\SimpleAdapter;

/**
 * Resolves a response's data: API resources through Laravel's own resolve(),
 * paginators through their adapter, and Laravel's serializable types the way
 * JsonResponse does. Values the caller set are never dropped.
 *
 * @internal
 */
final class Payload
{
    public function __construct(private Container $container, private Settings $settings, private Request $request)
    {
    }

    /**
     * @return array{data: mixed, pagination: array<string, mixed>|null, meta: array<array-key, mixed>}
     */
    public function resolve(mixed $data, bool $paginate, QueryString $query): array
    {
        $url = fn (?string $link, string $pageName): ?string => $query->url($link, $pageName);

        if ($data instanceof JsonResource) {
            return $this->resource($data, $paginate, $url);
        }

        if (is_object($data) && $adapter = $this->adapter($data)) {
            return [
                'data' => $this->normalize($adapter->items($data)),
                'pagination' => $paginate ? $adapter->meta($data, $url) : null,
                'meta' => [],
            ];
        }

        return ['data' => $this->normalize($data), 'pagination' => null, 'meta' => []];
    }

    /**
     * @return array{data: mixed, pagination: array<string, mixed>|null, meta: array<array-key, mixed>}
     */
    private function resource(JsonResource $resource, bool $paginate, \Closure $url): array
    {
        $data = $resource->resolve($this->request);
        $meta = array_merge($this->resourceMeta($resource->with($this->request)), $this->resourceMeta($resource->additional));
        $pagination = null;
        $paginator = $resource instanceof ResourceCollection ? $resource->resource : null;

        if ($paginate && is_object($paginator) && $adapter = $this->adapter($paginator)) {
            $pagination = $adapter->meta($paginator, $url);

            if (method_exists($resource, 'paginationInformation')) {
                $information = $resource->paginationInformation(
                    $this->request,
                    method_exists($paginator, 'toArray') ? $paginator->toArray() : [],
                    ['pagination' => $pagination]
                );
                $information = is_array($information) ? $information : [];
                $pagination = isset($information['pagination']) && is_array($information['pagination'])
                    ? $information['pagination']
                    : null;
                unset($information['pagination']);
                $meta = array_merge($meta, $this->resourceMeta($information));
            }
        }

        /** @var array<string, mixed>|null $pagination */
        return ['data' => $data, 'pagination' => $pagination, 'meta' => $meta];
    }

    /**
     * A resource's with() and additional() become metadata: the contents of a
     * "meta" key are merged in, every other key is kept under its own name.
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

    private function adapter(object $paginator): ?PaginationAdapter
    {
        $classes = array_merge($this->settings->adapters(), [LengthAwareAdapter::class, SimpleAdapter::class, CursorAdapter::class]);

        foreach ($classes as $class) {
            $adapter = $this->container->make($class);

            if ($adapter instanceof PaginationAdapter && $adapter->supports($paginator)) {
                return $adapter;
            }
        }

        return null;
    }

    private function normalize(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve($this->request);
        }

        if ($data instanceof Jsonable && ! $data instanceof JsonSerializable && ! $data instanceof Arrayable) {
            return json_decode($data->toJson(), true, 512, JSON_THROW_ON_ERROR);
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return array_map(fn ($item) => $item instanceof JsonResource ? $item->resolve($this->request) : $item, $data);
        }

        return $data;
    }
}
