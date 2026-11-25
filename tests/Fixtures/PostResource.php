<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Post $resource
 */
class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->resource->getKey(),
            'title' => $this->resource->getAttribute('title'),
            'admin' => $this->when($request->query('admin') === '1', 'yes'),
        ];
    }

    public function with($request)
    {
        return ['meta' => ['resource' => 'post'], 'links' => ['self' => '/posts/'.$this->resource->getKey()]];
    }

    public function withResponse($request, $response)
    {
        $response->headers->set('X-Resource', 'post');
    }
}
