<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $input */
    public function __construct(private array $input) {}

    public function value(string $field, int $maxLength = 255): ?string
    {
        $raw = $this->input[$field] ?? null;
        if (!is_string($raw)) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        return mb_substr($trimmed, 0, $maxLength);
    }

    public function required(string $field, string $message, int $maxLength = 255): ?string
    {
        $value = $this->value($field, $maxLength);
        if ($value === null) {
            $this->errors[$field] = $message;
        }
        return $value;
    }

    public function email(string $field, string $message): ?string
    {
        $value = $this->value($field, 254);
        if ($value === null || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message;
            return null;
        }
        return mb_strtolower($value);
    }

    /** @param array<int,string> $allowed */
    public function inList(string $field, array $allowed, string $message): ?string
    {
        $value = $this->value($field);
        if ($value === null || !in_array($value, $allowed, true)) {
            $this->errors[$field] = $message;
            return null;
        }
        return $value;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }
}
