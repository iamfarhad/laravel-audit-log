<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Contracts;

use Illuminate\Database\Eloquent\Model;

interface FieldTransformerInterface
{
    /**
     * Transform an audited field value before it is stored.
     */
    public function transform(string $field, mixed $value, string $direction, Model $model): mixed;
}
