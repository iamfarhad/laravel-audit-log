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

final class CustomAuditActionTest extends TestCase
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
    }

    public function test_can_log_custom_actions(): void
    {
        // Get the audit logger service
        $auditLogger = app(AuditLogger::class);

        // Create a custom action log for post approval
        $approveLog = new AuditLog(
            entityType: Post::class,
            entityId: $this->post->id,
            action: 'approved',
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'published'],
            metadata: [
                'approved_by' => $this->user->id,
                'approved_at' => now()->toDateTimeString(),
                'comments' => 'Content looks good',
            ],
            causerType: User::class,
            causerId: $this->user->id,
            createdAt: Carbon::now()
        );

        // Log the custom action
        $auditLogger->log($approveLog);

        // Verify the custom action was logged
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $this->post->id,
            'action' => 'approved',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);

        // Create a custom action log for export
        $exportLog = new AuditLog(
            entityType: Post::class,
            entityId: $this->post->id,
            action: 'exported',
            oldValues: null,
            newValues: null,
            metadata: [
                'format' => 'pdf',
                'exported_by' => $this->user->id,
                'exported_at' => now()->toDateTimeString(),
            ],
            causerType: User::class,
            causerId: $this->user->id,
            createdAt: Carbon::now()
        );

        // Log the custom action
        $auditLogger->log($exportLog);

        // Verify the custom action was logged
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $this->post->id,
            'action' => 'exported',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);
    }
}
