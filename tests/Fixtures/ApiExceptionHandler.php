<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Orchestra\Testbench\Exceptions\Handler;
use Pharaonic\Laravel\ApiResponse\ExceptionRenderer;

/**
 * An application handler wired the way the documentation shows for Laravel 6.
 */
class ApiExceptionHandler extends Handler
{
    /** @var list<string> */
    public array $reported = [];

    public function report($e)
    {
        $this->reported[] = get_class($e);

        parent::report($e);
    }

    public function render($request, $e)
    {
        return (new ExceptionRenderer)($e, $request) ?? parent::render($request, $e);
    }
}
