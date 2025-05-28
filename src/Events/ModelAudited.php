<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

final class ModelAudited
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Model $model,
        public readonly string $action,
        public readonly ?array $oldValues,
        public readonly ?array $newValues
    ) {}
}
