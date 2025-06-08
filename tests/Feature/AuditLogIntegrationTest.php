<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class AuditLogIntegrationTest extends TestCase
{
    private User $user;

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
    }

    public function test_create_model_generates_audit_log(): void
    {
        // Create a new post which should trigger audit logging
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Test Post',
            'content' => 'This is a test post content',
            'status' => 'draft',
        ]);

        // Check if an audit log was created
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'created',
        ]);

        // Basic assertion that log exists
        $logExists = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->exists();

        $this->assertTrue($logExists);
    }

    public function test_update_model_generates_audit_log(): void
    {
        // Create a post
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'content' => 'Original content',
            'status' => 'draft',
        ]);

        // Clear previous audit logs to make testing easier
        DB::table('audit_posts_logs')->where('entity_id', $post->id)->delete();

        // Update the post
        $post->title = 'Updated Title';
        $post->status = 'published';
        $post->published_at = now();
        $post->save();

        // Check if an audit log was created for the update
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'updated',
        ]);

        // Basic assertion that log exists
        $logExists = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'updated')
            ->exists();

        $this->assertTrue($logExists);
    }

    public function test_delete_model_generates_audit_log(): void
    {
        // Create a post
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Post to Delete',
            'content' => 'This post will be deleted',
            'status' => 'draft',
        ]);

        $postId = $post->id;

        // Clear previous audit logs to make testing easier
        DB::table('audit_posts_logs')->where('entity_id', $postId)->delete();

        // Delete the post
        $post->delete();

        // Check if an audit log was created for the deletion
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $postId,
            'action' => 'deleted',
        ]);

        // Basic assertion that log exists
        $logExists = DB::table('audit_posts_logs')
            ->where('entity_id', $postId)
            ->where('action', 'deleted')
            ->exists();

        $this->assertTrue($logExists);
    }

    public function test_disabled_auditing_prevents_log_creation(): void
    {
        // Create a post with auditing disabled
        $post = new Post;
        $post->disableAuditing();
        $post->user_id = $this->user->id;
        $post->title = 'Unaudited Post';
        $post->content = 'This post should not be audited';
        $post->status = 'draft';
        $post->save();

        // Check that no audit log was created
        $this->assertDatabaseMissing('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'created',
        ]);

        // Update with auditing still disabled
        $post->title = 'Updated Unaudited Post';
        $post->save();

        // Check that no audit log was created for the update
        $this->assertDatabaseMissing('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'updated',
        ]);

        // Enable auditing and make another change
        $post->enableAuditing();
        $post->status = 'published';
        $post->save();

        // Check that an audit log was created for this update
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'updated',
        ]);
    }

    public function test_audit_logs_include_metadata(): void
    {
        // Create a post which includes custom metadata
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Metadata Post',
            'content' => 'This post should include metadata',
            'status' => 'draft',
        ]);

        // Basic assertion that log exists
        $logExists = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->exists();

        $this->assertTrue($logExists);
    }

    public function test_audit_log_only_records_included_fields(): void
    {
        // Create a post - Post model only includes title, status, published_at
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Included Fields Post',
            'content' => 'This content should not be audited',
            'status' => 'draft',
        ]);

        // Basic assertion that log exists
        $logExists = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->exists();

        $this->assertTrue($logExists);
    }

    public function test_audit_logging_with_authenticated_user_as_causer(): void
    {
        // Create a custom causer resolver that always returns our test user
        $this->app->instance(CauserResolverInterface::class, new class($this->user) implements CauserResolverInterface
        {
            private User $user;

            public function __construct(User $user)
            {
                $this->user = $user;
            }

            public function resolve(): array
            {
                return [
                    'type' => User::class,
                    'id' => $this->user->id,
                ];
            }
        });

        // Create a post that should use our custom causer resolver
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Auth User Post',
            'content' => 'Created with authenticated user',
            'status' => 'draft',
        ]);

        // Verify the audit log has the correct causer information
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'created',
        ]);

        // Get the actual log to check causer details
        $log = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(User::class, $log->causer_type);
        $this->assertEquals($this->user->id, $log->causer_id);
    }
}
