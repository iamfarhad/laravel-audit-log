<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\DTOs;

final readonly class RetentionResult
{
    /**
     * @param  array<string, int>  $entitiesProcessed  Array of entity => count processed
     * @param  array<string>  $errors  Array of error messages
     */
    public function __construct(
        public int $totalProcessed,
        public array $entitiesProcessed,
        public array $errors,
        public float $executionTime,
    ) {}

    public static function empty(): self
    {
        return new self(
            totalProcessed: 0,
            entitiesProcessed: [],
            errors: [],
            executionTime: 0.0,
        );
    }

    public static function fromSingle(string $entityType, int $processed, float $executionTime): self
    {
        return new self(
            totalProcessed: $processed,
            entitiesProcessed: [$entityType => $processed],
            errors: [],
            executionTime: $executionTime,
        );
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function isSuccessful(): bool
    {
        return ! $this->hasErrors();
    }

    public function merge(RetentionResult $other): self
    {
        return new self(
            totalProcessed: $this->totalProcessed + $other->totalProcessed,
            entitiesProcessed: array_merge($this->entitiesProcessed, $other->entitiesProcessed),
            errors: array_merge($this->errors, $other->errors),
            executionTime: $this->executionTime + $other->executionTime,
        );
    }

    public function addError(string $error): self
    {
        return new self(
            totalProcessed: $this->totalProcessed,
            entitiesProcessed: $this->entitiesProcessed,
            errors: array_merge($this->errors, [$error]),
            executionTime: $this->executionTime,
        );
    }
}
