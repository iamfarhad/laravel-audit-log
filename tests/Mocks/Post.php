<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Mocks;

use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Post extends Model
{
    use Auditable;

    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'status',
        'published_at',
    ];

    // Only include specific fields in audit logs
    protected array $auditInclude = [
        'title',
        'status',
        'published_at',
    ];

    // Property to control auditing
    public bool $auditingEnabled = true;

    /**
     * The user that created the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get custom metadata for audit logs.
     */
    public function getAuditMetadata(): array
    {
        return [
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'related_to' => 'blog_system',
        ];
    }
}
