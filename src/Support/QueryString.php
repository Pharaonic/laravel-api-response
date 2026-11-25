<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Support;

use Illuminate\Support\Arr;

/**
 * Applies a response's query-string policy to the pagination URLs it sends.
 * The page or cursor parameter is always kept; denied keys never are.
 *
 * @internal
 */
final class QueryString
{
    /** @var list<string> */
    private array $except;

    /**
     * @param  array<array-key, mixed>  $requestQuery
     * @param  list<string>|bool  $keep  true: every allowed key, false: none, list: only these keys
     * @param  list<string>  $except
     */
    public function __construct(private array $requestQuery, private array|bool $keep, array $except)
    {
        $this->except = array_map('strtolower', $except);
    }

    public function url(?string $url, string $pageName): ?string
    {
        if ($url === null) {
            return null;
        }

        $fragment = '';

        if (($hash = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $parts = explode('?', $url, 2);
        $path = $parts[0];
        parse_str($parts[1] ?? '', $own);

        $page = $own[$pageName] ?? null;
        unset($own[$pageName]);

        $extras = array_filter(
            array_merge($this->keep === false ? [] : $this->requestQuery, $own),
            fn ($key) => $this->allows((string) $key, $pageName),
            ARRAY_FILTER_USE_KEY
        );

        if ($page !== null) {
            $extras[$pageName] = $page;
        }

        return $path.($extras === [] ? '' : '?'.Arr::query($extras)).$fragment;
    }

    private function allows(string $key, string $pageName): bool
    {
        if ($key === $pageName || $this->keep === false || in_array(strtolower($key), $this->except, true)) {
            return false;
        }

        return $this->keep === true || in_array($key, $this->keep, true);
    }
}
