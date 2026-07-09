<?php

declare(strict_types=1);

namespace Verstka\Sdk\Callback;

final class FontsCallbackResult
{
    public bool $success;
    public string $message;

    /** @var array<string, mixed> */
    public array $fonts;

    /**
     * @param array<string, mixed> $fonts
     */
    public function __construct(bool $success, string $message, array $fonts)
    {
        $this->success = $success;
        $this->message = $message;
        $this->fonts = $fonts;
    }

    /**
     * @return array{rc: int, rm: string, data: array{fonts: array<string, mixed>}}
     */
    public function toResponse(): array
    {
        return [
            'rc' => $this->success ? 1 : 0,
            'rm' => $this->message,
            'data' => ['fonts' => $this->fonts],
        ];
    }
}
