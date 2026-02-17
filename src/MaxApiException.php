<?php

declare(strict_types=1);

namespace MaxApi;

use RuntimeException;

final class MaxApiException extends RuntimeException
{
    /** @var array<string,mixed> */
    private array $payload;

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(string $message, int $code = 0, array $payload = [])
    {
        parent::__construct($message, $code);
        $this->payload = $payload;
    }

    /**
     * @return array<string,mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
