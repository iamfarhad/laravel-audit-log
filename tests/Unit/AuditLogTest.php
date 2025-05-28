<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Models\AuditLog;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use Illuminate\Support\Carbon;

class AuditLogTest extends TestCase
{
    public function test_can_create_audit_log(): void
    {
        $createdAt = Carbon::now();

        $log = new AuditLog(
            entityType: 'App\Models\User',
            entityId: 123,
            action: 'updated',
            oldValues: ['name' => 'John'],
            newValues: ['name' => 'Jane'],
            causerType: 'App\Models\Admin',
            causerId: 456,
            metadata: ['ip' => '127.0.0.1'],
            createdAt: $createdAt
        );

        $this->assertEquals('App\Models\User', $log->getEntityType());
        $this->assertEquals(123, $log->getEntityId());
        $this->assertEquals('updated', $log->getAction());
        $this->assertEquals(['name' => 'John'], $log->getOldValues());
        $this->assertEquals(['name' => 'Jane'], $log->getNewValues());
        $this->assertEquals('App\Models\Admin', $log->getCauserType());
        $this->assertEquals(456, $log->getCauserId());
        $this->assertEquals(['ip' => '127.0.0.1'], $log->getMetadata());
        $this->assertEquals($createdAt, $log->getCreatedAt());
    }

    public function test_can_convert_to_array(): void
    {
        $createdAt = Carbon::now();

        $log = new AuditLog(
            entityType: 'App\Models\Product',
            entityId: '789',
            action: 'created',
            oldValues: null,
            newValues: ['name' => 'Product A', 'price' => 100],
            causerType: null,
            causerId: null,
            metadata: [],
            createdAt: $createdAt
        );

        $array = $log->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('App\Models\Product', $array['entity_type']);
        $this->assertEquals('789', $array['entity_id']);
        $this->assertEquals('created', $array['action']);
        $this->assertNull($array['old_values']);
        $this->assertEquals(['name' => 'Product A', 'price' => 100], $array['new_values']);
        $this->assertNull($array['causer_type']);
        $this->assertNull($array['causer_id']);
        $this->assertEquals([], $array['metadata']);
        $this->assertEquals($createdAt->toIso8601String(), $array['created_at']);
    }

    public function test_can_create_from_array(): void
    {
        $data = [
            'entity_type' => 'App\Models\Order',
            'entity_id' => 999,
            'action' => 'deleted',
            'old_values' => ['status' => 'active'],
            'new_values' => null,
            'causer_type' => 'App\Models\User',
            'causer_id' => 111,
            'metadata' => ['reason' => 'cancelled'],
            'created_at' => '2024-01-01T12:00:00+00:00',
        ];

        $log = AuditLog::fromArray($data);

        $this->assertEquals('App\Models\Order', $log->getEntityType());
        $this->assertEquals(999, $log->getEntityId());
        $this->assertEquals('deleted', $log->getAction());
        $this->assertEquals(['status' => 'active'], $log->getOldValues());
        $this->assertNull($log->getNewValues());
        $this->assertEquals('App\Models\User', $log->getCauserType());
        $this->assertEquals(111, $log->getCauserId());
        $this->assertEquals(['reason' => 'cancelled'], $log->getMetadata());
        $this->assertInstanceOf(Carbon::class, $log->getCreatedAt());
    }
}
