<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Mocks;

use Illuminate\Database\Eloquent\Model;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

final class User extends Model
{
    use Auditable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    // Define fields to exclude from audit logs
    protected array $auditExclude = [
        'password',
        'remember_token'
    ];

    // Property to control auditing
    public bool $auditingEnabled = true;

    /**
     * Get custom metadata for audit logs.
     */
    public function getAuditMetadata(): array
    {
        return [
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Testing',
        ];
    }
}
