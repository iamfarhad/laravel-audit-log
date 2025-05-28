<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Drivers\MongoDBDriver;

final class MongoDBDriverTest extends TestCase
{
    private ?MongoDBDriver $driver = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('mongodb')) {
            $this->markTestSkipped('MongoDB extension not available.');
        }

        $config = [
            'connection' => 'mongodb',
            'database' => 'audit_test',
            'collection_prefix' => 'test_audit_',
            'collection_suffix' => '_logs',
        ];

        $this->driver = new MongoDBDriver($config);
    }

    public function test_get_collection_name(): void
    {
        // We need to use reflection to test private method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('getCollectionName');
        $method->setAccessible(true);

        $result = $method->invoke($this->driver, 'App\\Models\\User');
        $this->assertEquals('test_audit_users_logs', $result);
    }

    public function test_is_mongo_db_available(): void
    {
        // We need to use reflection to test private method
        $reflection = new \ReflectionClass($this->driver);
        $method = $reflection->getMethod('isMongoDBAvailable');
        $method->setAccessible(true);

        $result = $method->invoke($this->driver);
        $this->assertTrue($result);
    }
}
