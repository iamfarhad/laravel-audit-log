<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'entity_id' => $this->resource->entity_id,
            'action' => $this->resource->action,
            'old_values' => $this->resource->old_values,
            'new_values' => $this->resource->new_values,
            'changes' => $this->resource->changes,
            'causer_type' => $this->resource->causer_type,
            'causer_id' => $this->resource->causer_id,
            'tenant_type' => $this->resource->tenant_type ?? null,
            'tenant_id' => $this->resource->tenant_id ?? null,
            'metadata' => $this->resource->metadata,
            'source' => $this->resource->source,
            'audit_hash' => $this->resource->audit_hash ?? null,
            'previous_hash' => $this->resource->previous_hash ?? null,
            'created_at' => $this->resource->created_at,
        ];
    }
}
