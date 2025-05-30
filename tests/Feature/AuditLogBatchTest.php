<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;

final class AuditLogBatchTest extends TestCase
{
    private User $user;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create a test post
        $this->post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Test Post',
            'content' => 'This is a test post content',
            'status' => 'draft',
        ]);

        // Clear existing audit logs for cleaner testing
        DB::table('audit_posts_logs')->where('entity_id', $this->post->id)->delete();
        DB::table('audit_users_logs')->where('entity_id', $this->user->id)->delete();
    }

    public function test_can_store_batch_of_audit_logs(): void
    {
        // Get the audit logger service
        $auditLogger = app(AuditLogger::class);

        // Create audit log DTOs
        $logs = [
            new AuditLog(
                entityType: User::class,
                entityId: $this->user->id,
                action: 'login',
                oldValues: null,
                newValues: ['last_login_at' => now()->toDateTimeString()],
                metadata: ['ip' => '127.0.0.1'],
                causerType: null,
                causerId: null,
                createdAt: Carbon::now()
            ),
            new AuditLog(
                entityType: Post::class,
                entityId: $this->post->id,
                action: 'viewed',
                oldValues: null,
                newValues: null,
                metadata: ['viewer' => 'guest'],
                causerType: null,
                causerId: null,
                createdAt: Carbon::now()
            ),
        ];

        // Store batch of logs
        $auditLogger->batch($logs);

        // Verify both logs were stored in their respective tables
        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => $this->user->id,
            'action' => 'login',
        ]);

        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $this->post->id,
            'action' => 'viewed',
        ]);
    }
}
