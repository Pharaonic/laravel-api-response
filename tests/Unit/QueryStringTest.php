<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Unit;

use Pharaonic\Laravel\ApiResponse\Support\QueryString;
use Pharaonic\Laravel\ApiResponse\Tests\TestCase;

final class QueryStringTest extends TestCase
{
    public function test_null_urls_stay_null(): void
    {
        $this->assertNull((new QueryString([], true, []))->url(null, 'page'));
    }

    public function test_the_page_parameter_always_comes_last_and_survives_every_policy(): void
    {
        foreach ([true, false, ['a']] as $policy) {
            $url = (new QueryString(['a' => '1', 'page' => '9'], $policy, []))->url('/x?page=2', 'page');

            $this->assertStringEndsWith('page=2', (string) $url);
        }
    }

    public function test_denied_keys_are_case_insensitive_and_win_over_the_allowlist(): void
    {
        $url = (new QueryString(['Api_Key' => 'k', 'a' => '1'], ['Api_Key', 'a'], ['api_key']))->url('/x?page=2', 'page');

        $this->assertSame('/x?a=1&page=2', $url);
    }

    public function test_fragments_nested_arrays_and_non_ascii_values_are_encoded(): void
    {
        $url = (new QueryString(['q' => 'شاي & كعك', 'f' => ['a' => ['b' => 'c']]], true, []))->url('https://h.test/x?page=2#top', 'page');

        $this->assertSame('https://h.test/x?q=%D8%B4%D8%A7%D9%8A%20%26%20%D9%83%D8%B9%D9%83&f%5Ba%5D%5Bb%5D=c&page=2#top', $url);
    }

    public function test_encoded_cursors_are_kept_intact(): void
    {
        $cursor = 'eyJpZCI6MiwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ';

        $this->assertSame("/x?s=1&cursor={$cursor}", (new QueryString(['s' => '1'], true, []))->url("/x?cursor={$cursor}", 'cursor'));
    }

    public function test_the_paginator_own_query_wins_over_the_request(): void
    {
        $this->assertSame('/x?a=own&page=2', (new QueryString(['a' => 'request'], true, []))->url('/x?a=own&page=2', 'page'));
    }
}
