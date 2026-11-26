<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Orchestra\Testbench\Exceptions\Handler;
use Pharaonic\Laravel\ApiResponse\ExceptionRenderer;

/**
 * An application handler wired the way the documentation shows for Laravel 8 to 10.
 */
class ApiExceptionHandler extends Handler
{
    /** @var list<string> */
    public array $reported = [];

    public function register()
    {
        $this->renderable(new ExceptionRenderer);
    }

    public function report($e)
    {
        $this->reported[] = get_class($e);

        parent::report($e);
    }
}
