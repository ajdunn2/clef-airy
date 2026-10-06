<?php

namespace App\Services;

readonly class SystemOneResponse
{
    public function __construct(
        public string $url,
        public ?int $status,
        public string $body,
        public ?float $duration = null,
    ) {}

    public function formattedDuration(): ?string
    {
        if ($this->duration === null) {
            return null;
        }

        if ($this->duration < 1.0) {
            return (int) round($this->duration * 1000).' ms';
        }

        return number_format($this->duration, 2).' s';
    }
}
