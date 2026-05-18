<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class AuditDiffResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'field' => $this->resource['field'] ?? null,
            'old' => $this->resource['old'] ?? null,
            'new' => $this->resource['new'] ?? null,
            'type' => $this->resource['type'] ?? null,
            'label' => $this->resource['label'] ?? null,
        ];
    }
}
