<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use Carbon\Carbon;
use iamfarhad\LaravelAuditLog\Contracts\RetentionServiceInterface;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class RetentionIntegrationTest extends TestCase
{
    private RetentionServiceInterface $retentionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->retentionService = app(RetentionServiceInterface::class);

        $this->createTestAuditTable();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_users_logs');
        parent::tearDown();
    }

    #[Test]
    public function it_deletes_old_audit_logs(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.retention.strategy', 'delete');
        Config::set('audit-logger.retention.days', 30);
        Config::set('audit-logger.entities', [
            User::class => ['table' => 'audit_users_logs'],
        ]);

        // Verify initial data
        $this->assertEquals(4, DB::table('audit_users_logs')->count());

        // Run cleanup
        $result = $this->retentionService->runCleanupForEntity(User::class);

        // Should delete 2 old records (> 30 days)
        $this->assertEquals(2, $result->totalProcessed);
        $this->assertEquals(2, DB::table('audit_users_logs')->count());

        // Verify only recent records remain
        $remainingRecords = DB::table('audit_users_logs')->get();
        foreach ($remainingRecords as $record) {
            $this->assertTrue(Carbon::parse($record->created_at)->isAfter(now()->subDays(30)));
        }
    }

    #[Test]
    public function it_anonymizes_old_audit_logs(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.retention.strategy', 'anonymize');
        Config::set('audit-logger.retention.days', 30);
        Config::set('audit-logger.entities', [
            User::class => ['table' => 'audit_users_logs'],
        ]);

        // Verify initial data has sensitive information
        $oldRecord = DB::table('audit_users_logs')
            ->where('created_at', '<', now()->subDays(30))
            ->whereNotNull('old_values')
            ->first();

        $this->assertNotNull($oldRecord);
        $oldValues = json_decode($oldRecord->old_values, true);
        $this->assertEquals('john@example.com', $oldValues['email']);

        // Run cleanup
        $result = $this->retentionService->runCleanupForEntity(User::class);

        // Should anonymize 2 old records (> 30 days)
        $this->assertEquals(2, $result->totalProcessed);
        $this->assertEquals(4, DB::table('audit_users_logs')->count()); // No records deleted

        // Verify old records are anonymized
        $anonymizedRecord = DB::table('audit_users_logs')
            ->where('id', $oldRecord->id)
            ->first();

        $anonymizedValues = json_decode($anonymizedRecord->old_values, true);
        $this->assertEquals('[ANONYMIZED]', $anonymizedValues['email']);
        $this->assertNotNull($anonymizedRecord->anonymized_at);
    }

    #[Test]
    public function it_processes_records_in_batches(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.retention.strategy', 'delete');
        Config::set('audit-logger.retention.days', 30);
        Config::set('audit-logger.retention.batch_size', 1); // Small batch size
        Config::set('audit-logger.entities', [
            User::class => ['table' => 'audit_users_logs'],
        ]);

        $result = $this->retentionService->runCleanupForEntity(User::class);

        // Should still process all qualifying records despite small batch size
        $this->assertEquals(2, $result->totalProcessed);
    }

    #[Test]
    public function it_handles_per_model_retention_configuration(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.retention.strategy', 'delete');
        Config::set('audit-logger.retention.days', 365); // Global default

        Config::set('audit-logger.entities', [
            User::class => [
                'table' => 'audit_users_logs',
                'retention' => [
                    'strategy' => 'anonymize',
                    'days' => 30, // Override global setting
                ],
            ],
        ]);

        $result = $this->retentionService->runCleanupForEntity(User::class);

        // Should use entity-specific configuration (30 days, anonymize)
        $this->assertEquals(2, $result->totalProcessed);
        $this->assertEquals(4, DB::table('audit_users_logs')->count()); // Anonymized, not deleted
    }

    #[Test]
    public function it_executes_cleanup_command_successfully(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.entities', [
            User::class => ['table' => 'audit_users_logs'],
        ]);

        $this->artisan('audit:cleanup', ['--force' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_dry_run_without_changes(): void
    {
        Config::set('audit-logger.retention.enabled', true);
        Config::set('audit-logger.entities', [
            User::class => ['table' => 'audit_users_logs'],
        ]);

        $initialCount = DB::table('audit_users_logs')->count();

        $this->artisan('audit:cleanup', ['--dry-run' => true])
            ->assertExitCode(0);

        // No changes should be made
        $this->assertEquals($initialCount, DB::table('audit_users_logs')->count());
    }

    private function createTestAuditTable(): void
    {
        Schema::dropIfExists('audit_users_logs');

        Schema::create('audit_users_logs', function ($table) {
            $table->id();
            $table->string('entity_id');
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->string('source')->nullable();
            $table->timestamp('anonymized_at')->nullable();
        });
    }

    private function seedTestData(): void
    {
        $testData = [
            [
                'entity_id' => '1',
                'action' => 'created',
                'old_values' => null,
                'new_values' => json_encode(['name' => 'John Doe', 'email' => 'john@example.com']),
                'causer_type' => 'App\\Models\\User',
                'causer_id' => '1',
                'metadata' => json_encode([]),
                'created_at' => now()->subDays(60), // Old record
                'source' => 'web',
            ],
            [
                'entity_id' => '1',
                'action' => 'updated',
                'old_values' => json_encode(['email' => 'john@example.com']),
                'new_values' => json_encode(['email' => 'john.doe@example.com']),
                'causer_type' => 'App\\Models\\User',
                'causer_id' => '1',
                'metadata' => json_encode([]),
                'created_at' => now()->subDays(45), // Old record
                'source' => 'web',
            ],
            [
                'entity_id' => '2',
                'action' => 'created',
                'old_values' => null,
                'new_values' => json_encode(['name' => 'Jane Smith', 'email' => 'jane@example.com']),
                'causer_type' => 'App\\Models\\User',
                'causer_id' => '2',
                'metadata' => json_encode([]),
                'created_at' => now()->subDays(15), // Recent record
                'source' => 'api',
            ],
            [
                'entity_id' => '2',
                'action' => 'updated',
                'old_values' => json_encode(['name' => 'Jane Smith']),
                'new_values' => json_encode(['name' => 'Jane Wilson']),
                'causer_type' => 'App\\Models\\User',
                'causer_id' => '2',
                'metadata' => json_encode([]),
                'created_at' => now()->subDays(5), // Recent record
                'source' => 'api',
            ],
        ];

        DB::table('audit_users_logs')->insert($testData);
    }
}
