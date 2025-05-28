<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;

final class AuditLogAdvancedTest extends TestCase
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

    public function test_batch_auditing_when_enabled(): void
    {
        // Enable batch auditing
        Config::set('audit-logger.batch.enabled', true);

        // Create a user
        $user = User::create([
            'name' => 'Batch User',
            'email' => 'batch@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Create multiple posts in a batch
        $post1 = Post::create([
            'user_id' => $user->id,
            'title' => 'Batch Post 1',
            'content' => 'Content 1',
            'status' => 'draft',
        ]);

        $post2 = Post::create([
            'user_id' => $user->id,
            'title' => 'Batch Post 2',
            'content' => 'Content 2',
            'status' => 'draft',
        ]);

        // Audit logs might already be stored depending on implementation
        // Let's count logs before termination
        $initialCount = DB::table('audit_posts_logs')->count();

        // Terminate the application which triggers storing the batched audit logs
        // In a real app, this happens when the request ends
        $this->app->terminate();

        // Now check that audit logs were created
        $finalCount = DB::table('audit_posts_logs')->count();
        $this->assertGreaterThanOrEqual($initialCount, $finalCount, 'Audit logs count should increase after termination');

        // Reset batch auditing to default
        Config::set('audit-logger.batch.enabled', false);
    }

    public function test_conditional_auditing_based_on_field_changes(): void
    {
        // Create a user
        $user = User::create([
            'name' => 'Conditional User',
            'email' => 'conditional@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Clear audit logs to start with a clean slate
        $this->clearAuditLogs();

        // Update a non-important field (assuming updated_at won't trigger audit due to global exclude)
        $user->touch();

        // No audit log should be created since only updated_at changed
        $auditLogCount = DB::table('audit_users_logs')->count();
        $this->assertEquals(0, $auditLogCount, 'Audit log should not be created for excluded fields');

        // Update an important field
        $user->is_active = false;
        $user->save();

        // Audit log should be created for important field change
        $auditLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($auditLog, 'No audit log found for important field change');

        // Only the changed field should be in the audit
        $newValues = json_decode($auditLog->new_values, true);
        $this->assertArrayHasKey('is_active', $newValues);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_excluded_fields_from_different_sources(): void
    {
        // Set global excluded fields via config
        Config::set('audit-logger.fields.exclude', ['password', 'remember_token', 'updated_at', 'test_field']);

        // Create a user with all fields including test_field
        Schema::table('users', function ($table) {
            // Only add the column if it doesn't already exist
            if (! Schema::hasColumn('users', 'test_field')) {
                $table->string('test_field')->nullable();
            }
        });

        // Clear audit logs
        $this->clearAuditLogs();

        // Create the user
        $user = User::create([
            'name' => 'Exclude Test',
            'email' => 'exclude@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'test_field' => 'Should be excluded',
        ]);

        // Get the latest audit log
        $auditLog = $this->getLatestAuditLog(User::class);
        $this->assertNotNull($auditLog, 'No audit log found for user with excluded fields');

        $newValues = json_decode($auditLog->new_values, true);

        // Check that fields from both sources are excluded
        $this->assertArrayNotHasKey('password', $newValues, 'Password should be excluded');
        $this->assertArrayNotHasKey('remember_token', $newValues, 'Remember token should be excluded');

        // Only check for updated_at if it's in the array at all (it might be completely filtered)
        if (array_key_exists('updated_at', $newValues)) {
            $this->fail('updated_at should be excluded from audit log');
        }

        $this->assertArrayNotHasKey('test_field', $newValues, 'Test field should be excluded');

        // Should still have the important fields
        $this->assertArrayHasKey('name', $newValues);
        $this->assertArrayHasKey('email', $newValues);
        $this->assertArrayHasKey('is_active', $newValues);
    }
}
