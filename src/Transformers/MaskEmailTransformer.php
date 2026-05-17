<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Transformers;

use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use Illuminate\Database\Eloquent\Model;

final class MaskEmailTransformer implements FieldTransformerInterface
{
    public function transform(string $field, mixed $value, string $direction, Model $model): mixed
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return $value;
        }

        [$localPart, $domain] = explode('@', $value, 2);
        $visible = mb_substr($localPart, 0, 1);

        return $visible.str_repeat('*', max(3, mb_strlen($localPart) - 1)).'@'.$domain;
    }
}
