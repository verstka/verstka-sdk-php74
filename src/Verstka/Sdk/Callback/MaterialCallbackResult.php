<?php

declare(strict_types=1);

namespace Verstka\Sdk\Callback;

final class MaterialCallbackResult
{
    public bool $success;
    public string $message;

    /** @var array<string, mixed> */
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(bool $success, string $message, array $data)
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * @return array{rc: int, rm: string, data: array<string, mixed>}
     */
    public function toResponse(): array
    {
        return [
            'rc' => $this->success ? 1 : 0,
            'rm' => $this->message,
            'data' => $this->data,
        ];
    }
}
