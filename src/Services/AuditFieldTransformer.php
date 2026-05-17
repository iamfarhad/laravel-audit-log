<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AuditFieldTransformer
{
    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function transform(array $attributes, Model $model, string $direction): array
    {
        $redactedFields = config('audit-logger.fields.redact', []);
        $replacement = config('audit-logger.fields.redaction_replacement', '[REDACTED]');
        $transformers = config('audit-logger.fields.transformers', []);

        foreach ($attributes as $field => $value) {
            if (in_array($field, $redactedFields, true)) {
                $attributes[$field] = $replacement;
                continue;
            }

            if (array_key_exists($field, $transformers)) {
                $attributes[$field] = $this->resolve($transformers[$field])->transform($field, $value, $direction, $model);
            }
        }

        return $attributes;
    }

    private function resolve(mixed $transformer): FieldTransformerInterface
    {
        if ($transformer instanceof FieldTransformerInterface) {
            return $transformer;
        }

        if (is_string($transformer) && class_exists($transformer)) {
            $instance = app($transformer);
            if ($instance instanceof FieldTransformerInterface) {
                return $instance;
            }
        }

        throw new InvalidArgumentException('Audit field transformers must implement '.FieldTransformerInterface::class.'.');
    }
}
