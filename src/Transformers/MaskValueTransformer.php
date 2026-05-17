<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Transformers;

use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use Illuminate\Database\Eloquent\Model;

final class MaskValueTransformer implements FieldTransformerInterface
{
    public function transform(string $field, mixed $value, string $direction, Model $model): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $stringValue = (string) $value;
        $length = mb_strlen($stringValue);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($stringValue, 0, 2)
            .str_repeat('*', max(0, $length - 4))
            .mb_substr($stringValue, -2);
    }
}
