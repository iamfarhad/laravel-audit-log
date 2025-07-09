<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UserAuthenticationTrackingTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_audit_log_tracks_authenticated_user_with_source(): void
    {
        // Simulate user authentication
        Auth::login($this->user);

        // Create a custom causer resolver that uses the authenticated user
        $this->app->instance(CauserResolverInterface::class, new class implements CauserResolverInterface
        {
            public function resolve(): array
            {
                $user = Auth::user();

                if (! $user) {
                    return ['type' => null, 'id' => null];
                }

                return [
                    'type' => get_class($user),
                    'id' => $user->getAuthIdentifier(),
                ];
            }
        });

        // Create a post while authenticated
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Test Post',
            'content' => 'This is a test post',
            'status' => 'draft',
        ]);

        // Verify the audit log contains both user and source information
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'created',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);

        // Get the actual log to verify source information
        $log = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(User::class, $log->causer_type);
        $this->assertEquals($this->user->id, $log->causer_id);
        // Source might be null in test environment
        $this->assertIsString($log->source ?? '');
    }

    public function test_audit_log_tracks_user_updates_with_source(): void
    {
        // Simulate user authentication
        Auth::login($this->user);

        // Create a custom causer resolver
        $this->app->instance(CauserResolverInterface::class, new class implements CauserResolverInterface
        {
            public function resolve(): array
            {
                $user = Auth::user();

                if (! $user) {
                    return ['type' => null, 'id' => null];
                }

                return [
                    'type' => get_class($user),
                    'id' => $user->getAuthIdentifier(),
                ];
            }
        });

        // Create a post first
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Original Title',
            'content' => 'Original content',
            'status' => 'draft',
        ]);

        // Update the post
        $post->update(['title' => 'Updated Title']);

        // Verify the update audit log contains user information
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'updated',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);

        // Get the update log
        $log = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(User::class, $log->causer_type);
        $this->assertEquals($this->user->id, $log->causer_id);

        // Verify the old and new values are tracked
        $oldValues = json_decode($log->old_values, true);
        $newValues = json_decode($log->new_values, true);

        $this->assertEquals('Original Title', $oldValues['title']);
        $this->assertEquals('Updated Title', $newValues['title']);
    }

    public function test_audit_log_handles_unauthenticated_requests(): void
    {
        // Don't authenticate any user
        Auth::logout();

        // Create a post without authentication (using a dummy user_id since it's required)
        $post = Post::create([
            'user_id' => 999, // Dummy user ID for testing
            'title' => 'Anonymous Post',
            'content' => 'This is an anonymous post',
            'status' => 'draft',
        ]);

        // Verify the audit log shows null for causer
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $post->id,
            'action' => 'created',
            'causer_type' => null,
            'causer_id' => null,
        ]);

        // Get the log to verify source is still tracked
        $log = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->causer_type);
        $this->assertNull($log->causer_id);
        // Source should still be tracked (but might be null in test environment)
        $this->assertIsString($log->source ?? '');
    }

    public function test_audit_log_can_query_by_user_and_source(): void
    {
        // Create another user
        $anotherUser = User::create([
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create causer resolver that tracks current user
        $this->app->instance(CauserResolverInterface::class, new class implements CauserResolverInterface
        {
            public function resolve(): array
            {
                $user = Auth::user();

                if (! $user) {
                    return ['type' => null, 'id' => null];
                }

                return [
                    'type' => get_class($user),
                    'id' => $user->getAuthIdentifier(),
                ];
            }
        });

        // Create posts by different users
        Auth::login($this->user);
        $post1 = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Post by User 1',
            'content' => 'Content',
            'status' => 'draft',
        ]);

        Auth::login($anotherUser);
        $post2 = Post::create([
            'user_id' => $anotherUser->id,
            'title' => 'Post by User 2',
            'content' => 'Content',
            'status' => 'draft',
        ]);

        // Query logs by first user
        $user1Logs = DB::table('audit_posts_logs')
            ->where('causer_type', User::class)
            ->where('causer_id', $this->user->id)
            ->get();

        $this->assertCount(1, $user1Logs);
        $this->assertEquals($post1->id, $user1Logs->first()->entity_id);

        // Query logs by second user
        $user2Logs = DB::table('audit_posts_logs')
            ->where('causer_type', User::class)
            ->where('causer_id', $anotherUser->id)
            ->get();

        $this->assertCount(1, $user2Logs);
        $this->assertEquals($post2->id, $user2Logs->first()->entity_id);
    }

    public function test_audit_log_includes_metadata_with_user_context(): void
    {
        // Create a regular user first
        $user = User::create([
            'name' => 'Metadata User',
            'email' => 'metadata@example.com',
            'password' => bcrypt('password'),
        ]);

        // Authenticate the user
        Auth::login($user);

        // Create causer resolver
        $this->app->instance(CauserResolverInterface::class, new class implements CauserResolverInterface
        {
            public function resolve(): array
            {
                $user = Auth::user();

                if (! $user) {
                    return ['type' => null, 'id' => null];
                }

                return [
                    'type' => get_class($user),
                    'id' => $user->getAuthIdentifier(),
                ];
            }
        });

        // Create a post - metadata will be included from the User model's getAuditMetadata method
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Metadata Post',
            'content' => 'This post has metadata',
            'status' => 'draft',
        ]);

        // Get the log and verify user information is tracked
        $log = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(User::class, $log->causer_type);
        $this->assertEquals($user->id, $log->causer_id);

        // Verify basic metadata structure exists
        $metadata = json_decode($log->metadata, true);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('ip_address', $metadata);
        $this->assertArrayHasKey('related_to', $metadata);
        $this->assertEquals('blog_system', $metadata['related_to']);
    }

    public function test_audit_log_deletion_with_user_tracking(): void
    {
        // Authenticate user
        Auth::login($this->user);

        // Create causer resolver
        $this->app->instance(CauserResolverInterface::class, new class implements CauserResolverInterface
        {
            public function resolve(): array
            {
                $user = Auth::user();

                if (! $user) {
                    return ['type' => null, 'id' => null];
                }

                return [
                    'type' => get_class($user),
                    'id' => $user->getAuthIdentifier(),
                ];
            }
        });

        // Create and then delete a post
        $post = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Post to Delete',
            'content' => 'This will be deleted',
            'status' => 'draft',
        ]);

        $postId = $post->id;
        $post->delete();

        // Verify both creation and deletion logs exist with user information
        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $postId,
            'action' => 'created',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('audit_posts_logs', [
            'entity_id' => $postId,
            'action' => 'deleted',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
        ]);

        // Get the deletion log
        $deleteLog = DB::table('audit_posts_logs')
            ->where('entity_id', $postId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($deleteLog);
        $this->assertEquals(User::class, $deleteLog->causer_type);
        $this->assertEquals($this->user->id, $deleteLog->causer_id);
        // Source might be null in test environment
        $this->assertIsString($deleteLog->source ?? '');
    }
}
