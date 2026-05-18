<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class AuditTimelineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'action' => $this->resource['action'] ?? null,
            'entity_id' => $this->resource['entity_id'] ?? null,
            'causer_type' => $this->resource['causer_type'] ?? null,
            'causer_id' => $this->resource['causer_id'] ?? null,
            'source' => $this->resource['source'] ?? null,
            'created_at' => $this->resource['created_at'] ?? null,
            'changes' => $this->resource['changes'] ?? [],
            'metadata' => $this->resource['metadata'] ?? [],
            'audit_hash' => $this->resource['audit_hash'] ?? null,
            'previous_hash' => $this->resource['previous_hash'] ?? null,
        ];
    }
}
