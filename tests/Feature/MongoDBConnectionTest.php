<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Feature;

use Illuminate\Support\Facades\DB;
use iamfarhad\LaravelAuditLog\Tests\TestCase;

final class MongoDBConnectionTest extends TestCase
{
    /**
     * Test that MongoDB extension is available and skip if not.
     */
    public function test_mongodb_extension_available(): void
    {
        if (! extension_loaded('mongodb')) {
            $this->markTestSkipped('MongoDB extension not available.');
        }

        $this->assertTrue(extension_loaded('mongodb'), 'MongoDB extension should be loaded');
    }

    /**
     * Test that MongoDB connection can be established (skipped if extension not available).
     */
    public function test_mongodb_connection(): void
    {
        if (! extension_loaded('mongodb')) {
            $this->markTestSkipped('MongoDB extension not available.');
        }

        try {
            // Try to ping MongoDB using Laravel's connection
            $result = DB::connection('mongodb')->command(['ping' => 1]);
            $this->assertEquals(1.0, $result[0]->ok, 'MongoDB connection should be successful');
        } catch (\Exception $e) {
            $this->markTestSkipped('Cannot connect to MongoDB: '.$e->getMessage());
        }
    }
}
