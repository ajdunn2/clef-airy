<?php

namespace App\Services;

readonly class SystemOneResponse
{
    public function __construct(
        public string $url,
        public ?int $status,
        public string $body,
    ) {}
}
