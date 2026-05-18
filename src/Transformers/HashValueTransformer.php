<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Transformers;

use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use Illuminate\Database\Eloquent\Model;

final class HashValueTransformer implements FieldTransformerInterface
{
    public function transform(string $field, mixed $value, string $direction, Model $model): mixed
    {
        if ($value === null) {
            return null;
        }

        return hash('sha256', (string) $value);
    }
}
