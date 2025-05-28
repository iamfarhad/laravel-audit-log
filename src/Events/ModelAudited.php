<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Events;

use iamfarhad\LaravelAuditLog\Contracts\AuditableInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ModelAudited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AuditableInterface $model,
        public readonly string $action,
        public readonly ?array $oldValues,
        public readonly ?array $newValues
    ) {}
}
