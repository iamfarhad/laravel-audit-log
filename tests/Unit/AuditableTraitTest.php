<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Tests\Mocks\Post;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;

final class AuditableTraitTest extends TestCase
{
    public function test_can_enable_disable_auditing(): void
    {
        $user = new User;

        $this->assertTrue($user->isAuditingEnabled());

        $user->disableAuditing();
        $this->assertFalse($user->isAuditingEnabled());

        $user->enableAuditing();
        $this->assertTrue($user->isAuditingEnabled());
    }

    public function test_can_get_audit_entity_type(): void
    {
        $user = new User;
        $this->assertEquals(User::class, $user->getAuditEntityType());

        $post = new Post;
        $this->assertEquals(Post::class, $post->getAuditEntityType());
    }

    public function test_can_get_audit_metadata(): void
    {
        $user = new User;
        $metadata = $user->getAuditMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('ip_address', $metadata);
        $this->assertArrayHasKey('user_agent', $metadata);

        $post = new Post;
        $metadata = $post->getAuditMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('ip_address', $metadata);
        $this->assertArrayHasKey('related_to', $metadata);
        $this->assertEquals('blog_system', $metadata['related_to']);
    }

    public function test_can_get_auditable_attributes_with_exclude(): void
    {
        $user = new User;
        $attributes = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'hashed_password',
            'is_active' => true,
            'remember_token' => 'token',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $auditableAttributes = $user->getAuditableAttributes($attributes);

        // Should exclude password, remember_token, created_at, updated_at
        $this->assertArrayHasKey('name', $auditableAttributes);
        $this->assertArrayHasKey('email', $auditableAttributes);
        $this->assertArrayHasKey('is_active', $auditableAttributes);
        $this->assertArrayNotHasKey('password', $auditableAttributes);
        $this->assertArrayNotHasKey('remember_token', $auditableAttributes);
        $this->assertArrayNotHasKey('created_at', $auditableAttributes);
        $this->assertArrayNotHasKey('updated_at', $auditableAttributes);
    }

    public function test_can_get_auditable_attributes_with_include(): void
    {
        $post = new Post;
        $attributes = [
            'id' => 1,
            'user_id' => 1,
            'title' => 'Test Post',
            'content' => 'This is a test post content',
            'status' => 'draft',
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $auditableAttributes = $post->getAuditableAttributes($attributes);

        // Should only include title, status, published_at from the include list
        // and should exclude created_at, updated_at from the global exclude list
        $this->assertArrayHasKey('title', $auditableAttributes);
        $this->assertArrayHasKey('status', $auditableAttributes);
        $this->assertArrayHasKey('published_at', $auditableAttributes);
        $this->assertArrayNotHasKey('content', $auditableAttributes);
        $this->assertArrayNotHasKey('user_id', $auditableAttributes);
        $this->assertArrayNotHasKey('created_at', $auditableAttributes);
        $this->assertArrayNotHasKey('updated_at', $auditableAttributes);
    }

    public function test_model_events_create_audit_logs(): void
    {
        // Clear any existing logs
        DB::table('audit_users_logs')->delete();

        // Test created event
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'created',
        ]);

        // Test updated event
        $user->name = 'Updated Name';
        $user->save();

        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'updated',
        ]);

        // Test deleted event
        $user->delete();

        $this->assertDatabaseHas('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'deleted',
        ]);
    }

    public function test_disabled_auditing_does_not_create_logs(): void
    {
        // Clear any existing logs
        DB::table('audit_users_logs')->delete();

        $user = new User;
        $user->disableAuditing();
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->password = 'password';
        $user->is_active = true;
        $user->save();

        $this->assertDatabaseMissing('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'created',
        ]);

        $user->name = 'Updated Name';
        $user->save();

        $this->assertDatabaseMissing('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'updated',
        ]);

        $user->delete();

        $this->assertDatabaseMissing('audit_users_logs', [
            'entity_id' => $user->id,
            'action' => 'deleted',
        ]);
    }
}
