<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;

final class AuditLogFeatureTest extends TestCase
{
    /**
     * Get the latest audit log for the given model
     *
     * @param  string  $modelClass  Class name of the model
     * @return object|null The audit log record
     */
    protected function getLatestAuditLog(string $modelClass): ?object
    {
        // Convert class name to table name (e.g., User -> audit_users_logs)
        $className = class_basename($modelClass);
        $tableName = 'audit_'.Str::snake(Str::plural($className)).'_logs';

        return DB::table($tableName)
            ->latest('id')
            ->first();
    }

    /**
     * Clear all audit logs for testing
     */
    protected function clearAuditLogs(): void
    {
        DB::table('audit_users_logs')->delete();
        DB::table('audit_posts_logs')->delete();
    }

    public function test_user_creation_is_audited(): void
    {
        // Create a new user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'last_login_at' => now(),
        ]);

        // Check that the user was created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'is_active' => true,
        ]);

        // Check that an audit log was created for the user
        $auditLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($auditLog, 'No audit log found for user creation');

        $this->assertEquals($user->id, $auditLog->entity_id);
        $this->assertEquals('created', $auditLog->action);

        // Check that password was excluded from the audit log
        $newValues = json_decode($auditLog->new_values, true);
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('email', $newValues);
        $this->assertArrayNotHasKey('password', $newValues);
        $this->assertArrayNotHasKey('remember_token', $newValues);

        // Check that metadata was included
        $metadata = json_decode($auditLog->metadata, true);
        $this->assertArrayHasKey('ip_address', $metadata);
        $this->assertArrayHasKey('user_agent', $metadata);
    }

    public function test_user_update_is_audited(): void
    {
        // Create a user
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Update the user
        $user->name = 'Jane Smith';
        $user->email = 'jane.smith@example.com';
        $user->save();

        // Check that an audit log was created for the update
        $auditLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($auditLog, 'No audit log found for user update');

        $this->assertEquals($user->id, $auditLog->entity_id);
        $this->assertEquals('updated', $auditLog->action);

        // Check old and new values
        $oldValues = json_decode($auditLog->old_values, true);
        $newValues = json_decode($auditLog->new_values, true);

        $this->assertEquals('Jane Doe', $oldValues['name']);
        $this->assertEquals('jane@example.com', $oldValues['email']);
        $this->assertEquals('Jane Smith', $newValues['name']);
        $this->assertEquals('jane.smith@example.com', $newValues['email']);
    }

    public function test_user_deletion_is_audited(): void
    {
        // Create a user
        $user = User::create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $userId = $user->id;

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Delete the user
        $user->delete();

        // Check that an audit log was created for the deletion
        $auditLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($auditLog, 'No audit log found for user deletion');

        $this->assertEquals($userId, $auditLog->entity_id);
        $this->assertEquals('deleted', $auditLog->action);

        // Check that old values are present
        $oldValues = json_decode($auditLog->old_values, true);
        $this->assertArrayHasKey('name', $oldValues);
        $this->assertEquals('Alice Johnson', $oldValues['name']);

        // Check that new values are null
        if ($auditLog->new_values !== null) {
            $this->assertNull(json_decode($auditLog->new_values, true));
        }
    }

    public function test_post_auditing_with_include_list(): void
    {
        // Create a user first
        $user = User::create([
            'name' => 'Bob Williams',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Create a post
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'My First Post',
            'content' => 'This is the content of the post.',
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Check that an audit log was created for the post
        $auditLog = $this->getLatestAuditLog(Post::class);
        $this->assertNotNull($auditLog, 'No audit log found for post creation');

        $this->assertEquals($post->id, $auditLog->entity_id);

        // Check that only included fields are present
        $newValues = json_decode($auditLog->new_values, true);
        $this->assertArrayHasKey('title', $newValues);
        $this->assertArrayHasKey('status', $newValues);
        $this->assertArrayHasKey('published_at', $newValues);
        $this->assertArrayNotHasKey('content', $newValues); // Not in auditInclude

        // Check post-specific metadata
        $metadata = json_decode($auditLog->metadata, true);
        $this->assertArrayHasKey('related_to', $metadata);
        $this->assertEquals('blog_system', $metadata['related_to']);
    }

    public function test_disabling_auditing_for_specific_operations(): void
    {
        // Create a user
        $user = User::create([
            'name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Disable auditing for this specific operation
        $user->disableAuditing();

        // Update the user
        $user->name = 'Charles Brown';
        $user->save();

        // Check that no audit log was created
        $logCount = DB::table('audit_users_logs')->count();
        $this->assertEquals(0, $logCount, 'Audit log was created when auditing was disabled');

        // Re-enable auditing
        $user->enableAuditing();

        // Make another update
        $user->email = 'charles@example.com';
        $user->save();

        // Check that an audit log was created
        $auditLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($auditLog, 'No audit log found after re-enabling auditing');
    }

    public function test_relationships_between_audited_models(): void
    {
        // Create a user
        $user = User::create([
            'name' => 'David Miller',
            'email' => 'david@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create a post related to the user
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Relationship Post',
            'content' => 'This post demonstrates relationships.',
            'status' => 'draft',
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Publish the post
        $post->status = 'published';
        $post->published_at = now();
        $post->save();

        // Check the audit log for the post update
        $postAuditLog = $this->getLatestAuditLog(Post::class);
        $this->assertNotNull($postAuditLog, 'No audit log found for post update');

        $this->assertEquals($post->id, $postAuditLog->entity_id);

        // The old and new values should show the status change
        $oldValues = json_decode($postAuditLog->old_values, true);
        $newValues = json_decode($postAuditLog->new_values, true);

        $this->assertEquals('draft', $oldValues['status']);
        $this->assertEquals('published', $newValues['status']);

        // Check if published_at exists in old values before asserting
        if (array_key_exists('published_at', $oldValues)) {
            $this->assertNull($oldValues['published_at']);
        }

        $this->assertNotNull($newValues['published_at']);
    }

    public function test_complex_scenario_with_multiple_changes(): void
    {
        // Create initial data
        $user = User::create([
            'name' => 'Eva Green',
            'email' => 'eva@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'content' => 'Original content.',
            'status' => 'draft',
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Scenario: User becomes inactive and all their posts are archived

        // 1. Make user inactive
        $user->is_active = false;
        $user->save();

        // 2. Archive their post
        $post->status = 'archived';
        $post->save();

        // Check user audit log
        $userLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($userLog, 'No audit log found for user update');
        $this->assertEquals($user->id, $userLog->entity_id);

        $userOldValues = json_decode($userLog->old_values, true);
        $userNewValues = json_decode($userLog->new_values, true);
        $this->assertTrue($userOldValues['is_active']);
        $this->assertFalse($userNewValues['is_active']);

        // Check post audit log
        $postLog = $this->getLatestAuditLog(Post::class);
        $this->assertNotNull($postLog, 'No audit log found for post update');
        $this->assertEquals($post->id, $postLog->entity_id);

        $postOldValues = json_decode($postLog->old_values, true);
        $postNewValues = json_decode($postLog->new_values, true);
        $this->assertEquals('draft', $postOldValues['status']);
        $this->assertEquals('archived', $postNewValues['status']);
    }
}
