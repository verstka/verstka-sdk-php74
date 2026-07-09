<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration;

final class ErrorResponse
{
    public int $status;
    public string $code;
    public string $message;

    public function __construct(int $status, string $code, string $message)
    {
        $this->status = $status;
        $this->code = $code;
        $this->message = $message;
    }

    /**
     * @return array{error: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'error' => $this->code,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
