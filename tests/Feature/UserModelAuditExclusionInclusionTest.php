<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class UserModelAuditExclusionInclusionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing audit logs before each test
        DB::table('audit_users_logs')->delete();
    }

    public function test_user_model_audit_exclusions_verification(): void
    {
        // Test the actual User model to verify audit exclusions work
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        // Verify user audit table exists
        $this->assertTrue(Schema::hasTable('audit_users_logs'));

        // Get the audit log
        $auditLog = DB::table('audit_users_logs')
            ->where('entity_id', $user->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log should be created for user creation');

        $newValues = json_decode($auditLog->new_values, true);

        // These fields should be logged
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('email', $newValues);
        $this->assertArrayHasKey('is_active', $newValues);
        $this->assertEquals('Test User', $newValues['name']);
        $this->assertEquals('test@example.com', $newValues['email']);
        $this->assertTrue($newValues['is_active']);

        // These sensitive fields should be excluded
        $this->assertArrayNotHasKey(
            'password',
            $newValues,
            'Password field should be excluded from audit logs but it appears in: '.json_encode(array_keys($newValues))
        );

        $this->assertArrayNotHasKey(
            'remember_token',
            $newValues,
            'Remember token should be excluded from audit logs but it appears in: '.json_encode(array_keys($newValues))
        );
    }

    public function test_user_model_audit_exclusions_on_update(): void
    {
        // Create user first
        $user = User::create([
            'name' => 'Initial Name',
            'email' => 'initial@example.com',
            'password' => 'initial-password',
            'is_active' => false,
        ]);

        // Clear the creation audit log
        DB::table('audit_users_logs')->delete();

        // Update user with both allowed and excluded fields
        $user->update([
            'name' => 'Updated Name',
            'password' => 'updated-password',
            'is_active' => true,
        ]);

        // Get the update audit log
        $auditLog = DB::table('audit_users_logs')
            ->where('entity_id', $user->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log should be created for user update');

        $oldValues = json_decode($auditLog->old_values, true);
        $newValues = json_decode($auditLog->new_values, true);

        // Allowed fields should be logged
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('is_active', $newValues);
        $this->assertEquals('Updated Name', $newValues['name']);
        $this->assertTrue($newValues['is_active']);

        $this->assertArrayHasKey('name', $oldValues);
        $this->assertArrayHasKey('is_active', $oldValues);
        $this->assertEquals('Initial Name', $oldValues['name']);
        $this->assertFalse($oldValues['is_active']);

        // Excluded fields should not be logged even if changed
        $this->assertArrayNotHasKey(
            'password',
            $newValues,
            'Password field should be excluded from audit logs in new values'
        );

        $this->assertArrayNotHasKey(
            'password',
            $oldValues,
            'Password field should be excluded from audit logs in old values'
        );
    }

    public function test_user_model_respects_global_and_model_exclusions(): void
    {
        // Create user with all possible fields that might be excluded
        $user = new User;
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = 'secret-password';
        $user->is_active = true;
        $user->remember_token = 'test-token';
        $user->save();

        // Get the audit log
        $auditLog = DB::table('audit_users_logs')
            ->where('entity_id', $user->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($auditLog);

        $newValues = json_decode($auditLog->new_values, true);

        // Verify allowed fields are present
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('email', $newValues);
        $this->assertArrayHasKey('is_active', $newValues);

        // Verify model-level exclusions (from User model's $auditExclude)
        $this->assertArrayNotHasKey('password', $newValues);
        $this->assertArrayNotHasKey('remember_token', $newValues);

        // Verify global exclusions (from config) - timestamps should be excluded by default
        $this->assertArrayNotHasKey('created_at', $newValues);
        $this->assertArrayNotHasKey('updated_at', $newValues);
    }

    public function test_user_model_audit_exclusions_with_disabled_auditing(): void
    {
        // Create user with auditing disabled
        $user = new User;
        $user->disableAuditing();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = 'secret-password';
        $user->is_active = true;
        $user->save();

        // Verify no audit log was created
        $auditLogCount = DB::table('audit_users_logs')
            ->where('entity_id', $user->id)
            ->count();

        $this->assertEquals(0, $auditLogCount, 'No audit logs should be created when auditing is disabled');
    }

    public function test_post_model_audit_inclusions_verification(): void
    {
        // Clear any existing audit logs
        DB::table('audit_posts_logs')->delete();

        // Create a user first (required for posts)
        $user = User::create([
            'name' => 'Post Author',
            'email' => 'author@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        // Test the Post model which has auditInclude = ['title', 'status', 'published_at']
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Test Post Title',
            'content' => 'This content should NOT be audited',
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Verify post audit table exists
        $this->assertTrue(Schema::hasTable('audit_posts_logs'));

        // Get the audit log
        $auditLog = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log should be created for post creation');

        $newValues = json_decode($auditLog->new_values, true);

        // These fields should be logged (from auditInclude array)
        $this->assertArrayHasKey('title', $newValues);
        $this->assertArrayHasKey('status', $newValues);
        $this->assertArrayHasKey('published_at', $newValues);
        $this->assertEquals('Test Post Title', $newValues['title']);
        $this->assertEquals('draft', $newValues['status']);
        $this->assertNull($newValues['published_at']);

        // These fields should NOT be logged (not in auditInclude array)
        $this->assertArrayNotHasKey(
            'content',
            $newValues,
            'Content field should not be audited because it is not in auditInclude array. Found fields: '.json_encode(array_keys($newValues))
        );

        $this->assertArrayNotHasKey(
            'user_id',
            $newValues,
            'User ID field should not be audited because it is not in auditInclude array. Found fields: '.json_encode(array_keys($newValues))
        );

        // Global exclusions should still apply (timestamps should be excluded)
        $this->assertArrayNotHasKey(
            'created_at',
            $newValues,
            'Created at timestamp should be excluded by global config. Found fields: '.json_encode(array_keys($newValues))
        );

        $this->assertArrayNotHasKey(
            'updated_at',
            $newValues,
            'Updated at timestamp should be excluded by global config. Found fields: '.json_encode(array_keys($newValues))
        );
    }

    public function test_post_model_audit_inclusions_on_update(): void
    {
        // Create a user first
        $user = User::create([
            'name' => 'Post Author',
            'email' => 'author@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        // Create a post
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Original Title',
            'content' => 'Original content that should not be audited',
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Clear the creation audit log
        DB::table('audit_posts_logs')->delete();

        // Update the post with both included and excluded fields
        $post->update([
            'title' => 'Updated Title',
            'content' => 'Updated content that should still not be audited',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Get the update audit log
        $auditLog = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($auditLog, 'Audit log should be created for post update');

        $oldValues = json_decode($auditLog->old_values, true);
        $newValues = json_decode($auditLog->new_values, true);

        // Included fields should be logged in both old and new values
        $this->assertArrayHasKey('title', $newValues);
        $this->assertArrayHasKey('status', $newValues);
        $this->assertArrayHasKey('published_at', $newValues);
        $this->assertEquals('Updated Title', $newValues['title']);
        $this->assertEquals('published', $newValues['status']);
        $this->assertNotNull($newValues['published_at']);

        $this->assertArrayHasKey('title', $oldValues);
        $this->assertArrayHasKey('status', $oldValues);
        $this->assertArrayHasKey('published_at', $oldValues);
        $this->assertEquals('Original Title', $oldValues['title']);
        $this->assertEquals('draft', $oldValues['status']);
        $this->assertNull($oldValues['published_at']);

        // Excluded fields should not be logged even if changed
        $this->assertArrayNotHasKey(
            'content',
            $newValues,
            'Content field should not be audited in new values'
        );

        $this->assertArrayNotHasKey(
            'content',
            $oldValues,
            'Content field should not be audited in old values'
        );
    }

    public function test_post_model_inclusion_with_partial_changes(): void
    {
        // Create a user first
        $user = User::create([
            'name' => 'Post Author',
            'email' => 'author@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        // Create a post
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Test Title',
            'content' => 'Test content',
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Clear the creation audit log
        DB::table('audit_posts_logs')->delete();

        // Update only non-included fields (should not create audit log if no included fields change)
        $post->content = 'Updated content that is not audited';
        $post->save();

        // Check if an audit log was created
        $auditLogCount = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'updated')
            ->count();

        // Since only non-included fields changed, no audit log should be created
        $this->assertEquals(0, $auditLogCount, 'No audit log should be created when only non-included fields change');

        // Now update an included field
        $post->status = 'published';
        $post->save();

        // Now an audit log should be created
        $auditLogCount = DB::table('audit_posts_logs')
            ->where('entity_id', $post->id)
            ->where('action', 'updated')
            ->count();

        $this->assertEquals(1, $auditLogCount, 'Audit log should be created when included fields change');
    }

    public function test_model_with_wildcard_include_respects_exclusions(): void
    {
        // Test that a model without explicit auditInclude (defaults to ['*']) still respects exclusions
        // The User model doesn't have auditInclude, so it should include all fields except excluded ones

        $user = User::create([
            'name' => 'Wildcard Test User',
            'email' => 'wildcard@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        // Get the audit log
        $auditLog = DB::table('audit_users_logs')
            ->where('entity_id', $user->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($auditLog);

        $newValues = json_decode($auditLog->new_values, true);

        // With wildcard include (['*']), all fields should be included except excluded ones
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('email', $newValues);
        $this->assertArrayHasKey('is_active', $newValues);

        // But excluded fields should still be excluded
        $this->assertArrayNotHasKey('password', $newValues);
        $this->assertArrayNotHasKey('remember_token', $newValues);
        $this->assertArrayNotHasKey('created_at', $newValues); // Global exclusion
        $this->assertArrayNotHasKey('updated_at', $newValues); // Global exclusion
    }
}
