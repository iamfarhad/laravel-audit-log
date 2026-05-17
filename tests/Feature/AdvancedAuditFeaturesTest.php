<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use iamfarhad\LaravelAuditLog\Facades\AuditLogger as AuditLoggerFacade;
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use iamfarhad\LaravelAuditLog\Transformers\MaskEmailTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AdvancedAuditFeaturesTest extends TestCase
{
    public function test_can_search_timeline_diff_and_analytics(): void
    {
        $user = User::create([
            'name' => 'Farhad',
            'email' => 'farhad@example.com',
            'password' => 'secret',
        ]);

        $user->update(['name' => 'Farhad Zand']);

        $searchResults = AuditLoggerFacade::search(User::class, 'Farhad Zand')->get();
        $timeline = $user->auditTimeline();
        $diff = $user->auditDiff();
        $summary = AuditLoggerFacade::analytics()->summary(User::class);

        $this->assertCount(1, $searchResults);
        $this->assertCount(2, $timeline);
        $this->assertSame('updated', $timeline->last()['action']);
        $this->assertSame('name', $diff[0]['field']);
        $this->assertSame('Farhad', $diff[0]['old']);
        $this->assertSame('Farhad Zand', $diff[0]['new']);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['actions']['created']);
        $this->assertSame(1, $summary['actions']['updated']);
    }

    public function test_can_restore_and_rollback_without_saving(): void
    {
        $user = User::create([
            'name' => 'Original',
            'email' => 'restore@example.com',
            'password' => 'secret',
        ]);

        $user->update(['name' => 'Changed']);

        /** @var EloquentAuditLog $updateLog */
        $updateLog = $user->auditLogs()->where('action', 'updated')->firstOrFail();

        $preview = $user->previewRestore($updateLog, 'rollback');
        $user->rollbackToAudit($updateLog, false);

        $this->assertSame('name', $preview[0]['field']);
        $this->assertSame('Original', $preview[0]['target']);
        $this->assertSame('Original', $user->name);

        $user->restoreFromAudit($updateLog, false);

        $this->assertSame('Changed', $user->name);
    }

    public function test_field_transformers_and_redactors_are_applied(): void
    {
        config([
            'audit-logger.fields.redact' => ['name'],
            'audit-logger.fields.transformers' => [
                'email' => MaskEmailTransformer::class,
            ],
        ]);

        $user = User::create([
            'name' => 'Sensitive Name',
            'email' => 'private@example.com',
            'password' => 'secret',
        ]);

        /** @var EloquentAuditLog $log */
        $log = $user->auditLogs()->where('action', 'created')->firstOrFail();

        $this->assertSame('[REDACTED]', $log->new_values['name']);
        $this->assertSame('p******@example.com', $log->new_values['email']);
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_tamper_evident_hash_chain_can_be_verified(): void
    {
        config([
            'audit-logger.security.hashing.enabled' => true,
            'audit-logger.security.hashing.key' => 'test-key',
        ]);

        $user = User::create([
            'name' => 'Hash One',
            'email' => 'hash@example.com',
            'password' => 'secret',
        ]);
        $user->update(['name' => 'Hash Two']);

        $validResult = AuditLoggerFacade::verifyHashChain(User::class);

        $this->assertTrue($validResult['valid']);
        $this->assertSame(2, $validResult['checked']);
        $this->assertNotNull($user->auditLogs()->where('action', 'updated')->first()->audit_hash);

        DB::connection('testbench')
            ->table('audit_users_logs')
            ->where('action', 'updated')
            ->update(['new_values' => json_encode(['name' => 'Tampered'], JSON_THROW_ON_ERROR)]);

        $invalidResult = AuditLoggerFacade::verifyHashChain(User::class);

        $this->assertFalse($invalidResult['valid']);
        $this->assertCount(1, $invalidResult['failures']);
    }

    public function test_postgresql_driver_can_be_resolved(): void
    {
        $logger = \iamfarhad\LaravelAuditLog\Services\AuditLogger::getDriver('postgresql', 'testbench');

        $this->assertInstanceOf(\iamfarhad\LaravelAuditLog\Services\AuditLogger::class, $logger);
    }
}

final class User extends Model
{
    use Auditable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
