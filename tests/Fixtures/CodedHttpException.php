<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\ApiResponse\Tests\Fixtures;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shaped like pharaonic/laravel-api-versioning's ApiVersionException: an HTTP exception with errorCode().
 */
class CodedHttpException extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(int $status, string $message, private string $errorCode, array $headers = [], private ?string $localized = null)
    {
        parent::__construct($status, $message, null, $headers);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function localizedMessage(): string
    {
        return $this->localized ?? $this->getMessage();
    }
}
