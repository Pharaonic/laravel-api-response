<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response as IlluminateResponse;
use InvalidArgumentException;
use Pharaonic\Laravel\ApiResponse\Profiles\Profile;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single finalization path of an Response. It reads the builder's
 * state and never changes it, so finalizing twice gives the same response.
 *
 * @internal
 */
final class Finalizer
{
    public function __construct(private Container $container)
    {
    }

    /**
     * @param  array{error: bool, status: int, hasData: bool, data: mixed, message: ?string, code: ?string, errors: array<array-key, mixed>|null, meta: array<array-key, mixed>, notifications: list<array{type: string, message: string, title: ?string, duration: ?int, data: array<array-key, mixed>}>, headers: array<string, string>, profile: ?string, seo: bool, requestId: ?bool, queryString: list<string>|bool|null, pagination: bool, transformers: list<callable(mixed): mixed>}  $state
     */
    public function finalize(array $state, Request $request): Response
    {
        $config = $this->container->make('config');

        if (! $config instanceof Repository) {
            throw new InvalidArgumentException('The container has no configuration repository.');
        }

        $settings = new Settings($config);
        $resolved = (new Profiles($this->container, $settings))->resolve($state['profile'], $request);
        $profile = $resolved['profile'];

        $headers = [];

        foreach (array_merge($profile->headers(), $state['headers']) as $name => $value) {
            Headers::validate($name, $value);
            $headers[$name] = $value;
        }

        if (in_array($state['status'], [204, 304], true)) {
            return $this->varyOnAccept(new IlluminateResponse('', $state['status'], $headers), $settings);
        }

        $parts = ['success' => ! $state['error']];

        if ($state['message'] !== null) {
            $parts['message'] = $state['message'];
        }

        if ($state['code'] !== null) {
            $parts['code'] = $state['code'];
        }

        $meta = [];

        if ($state['hasData']) {
            $query = new QueryString(
                $request->query->all(),
                $state['queryString'] ?? $settings->queryStringEnabled(),
                $settings->queryStringExcept()
            );
            $payload = (new Payload($this->container, $settings, $request))
                ->resolve($state['data'], $state['pagination'] && $profile->paginates(), $query);

            $data = $payload['data'];

            foreach ($state['transformers'] as $transformer) {
                $data = $transformer($data);
            }

            $parts['data'] = $data;
            $meta = $payload['meta'];

            if ($payload['pagination'] !== null) {
                $pagination = $profile->pagination($payload['pagination']);
            }
        }

        if ($state['errors'] !== null) {
            $parts['errors'] = $state['errors'];
        }

        // Resource metadata, then the response's own meta(), then the package's pagination.
        $meta = array_merge($meta, $state['meta']);

        if (isset($pagination)) {
            $meta['pagination'] = $pagination;
        }

        if ($meta !== []) {
            $parts['meta'] = $meta;
        }

        if ($state['notifications'] !== []) {
            $parts['notifications'] = $this->notifications($state['notifications'], $settings, $profile);
        }

        if ($state['seo'] && ($tags = Seo::tags($this->container, $settings)) !== null) {
            $parts['seo'] = $tags;
        }

        $requestId = RequestId::of($request);

        if ($requestId !== null && ($state['requestId'] ?? $profile->includesRequestId())) {
            $parts['request_id'] = $requestId;
        }

        $json = json_encode($profile->body($parts), $settings->jsonOptions(), 512);

        // Built empty, then given the JSON as is: Laravel's JsonResponse would encode it again.
        $response = new JsonResponse(null, $state['status'], $headers, $settings->jsonOptions() & ~JSON_THROW_ON_ERROR);
        $response->setJson((string) $json);

        if ($resolved['media_type'] !== null) {
            $response->headers->set('Content-Type', $resolved['media_type']);
        }

        if ($state['hasData'] && $state['data'] instanceof JsonResource) {
            $state['data']->withResponse($request, $response);
        }

        if ($request->isMethod('HEAD')) {
            $response->setContent('');
        }

        return $this->varyOnAccept($response, $settings);
    }

    /**
     * While negotiation is on the body depends on Accept, so Accept joins any Vary
     * the profile, the response or a resource set, or a cache could serve one
     * profile's body to another.
     */
    private function varyOnAccept(Response $response, Settings $settings): Response
    {
        if (! $settings->negotiates()) {
            return $response;
        }

        $vary = [];

        foreach ($response->getVary() as $name) {
            if (is_string($name)) {
                $vary[] = $name;
            }
        }

        foreach ($vary as $name) {
            if ($name === '*' || strtolower($name) === 'accept') {
                return $response;
            }
        }

        $response->setVary(array_merge($vary, ['Accept']));

        return $response;
    }

    /**
     * @param  list<array{type: string, message: string, title: ?string, duration: ?int, data: array<array-key, mixed>}>  $notifications
     * @return list<array<string, mixed>>
     */
    private function notifications(array $notifications, Settings $settings, Profile $profile): array
    {
        $types = $settings->notificationTypes();
        $default = $settings->defaultNotificationType();
        $shaped = [];

        foreach ($notifications as $notification) {
            $notification['type'] = $notification['type'] === '' ? $default : $notification['type'];

            if (! in_array($notification['type'], $types, true)) {
                throw new InvalidArgumentException("The notification type [{$notification['type']}] is not one of pharaonic.api-response.notifications.types.");
            }

            $shaped[] = $profile->notification($notification);
        }

        return $shaped;
    }
}
