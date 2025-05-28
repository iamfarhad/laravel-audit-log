<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Examples;

use iamfarhad\LaravelAuditLog\Contracts\AuditableInterface;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Example model demonstrating how to implement audit logging.
 */
class ExampleModel extends Model implements AuditableInterface
{
    use Auditable;

    protected $fillable = [
        'name',
        'email',
        'status',
        'price',
        'description',
        'secret_key',
        'internal_notes',
    ];

    /**
     * Fields to exclude from audit logging.
     * These fields will never be logged.
     */
    protected array $auditExclude = [
        'secret_key',
        'internal_notes',
        'updated_at',
    ];

    /**
     * Fields to include in audit logging.
     * Use ['*'] to include all fields except those in $auditExclude.
     * Or specify specific fields to only log those.
     */
    protected array $auditInclude = ['*'];

    /**
     * Get custom metadata for audit logs.
     * This method is called for each audit log entry.
     */
    public function getAuditMetadata(): array
    {
        return [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID'),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Example of temporarily disabling auditing.
     */
    public function updateWithoutAudit(array $attributes): bool
    {
        return $this->disableAuditing()->update($attributes);
    }

    /**
     * Example of custom business logic that triggers manual audit.
     */
    public function approve(): void
    {
        $this->update(['status' => 'approved']);

        // Log a custom action
        app('audit-logger')->log(
            entityType: static::class,
            entityId: $this->id,
            action: 'approved',
            oldValues: ['status' => $this->getOriginal('status')],
            newValues: ['status' => 'approved'],
            metadata: [
                'approved_at' => now()->toIso8601String(),
                'approver_id' => auth()->id(),
            ]
        );
    }
}
