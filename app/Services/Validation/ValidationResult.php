<?php

declare(strict_types=1);

namespace App\Services\Validation;

final class ValidationResult
{
    /** @param array<string, string> $errors  check code => human message */
    public function __construct(
        public readonly array $errors,
        public readonly int $deviation,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
