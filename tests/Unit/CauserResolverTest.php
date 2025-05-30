<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use Mockery;
use Illuminate\Support\Facades\Auth;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Tests\Mocks\User;
use iamfarhad\LaravelAuditLog\Services\CauserResolver;

final class CauserResolverTest extends TestCase
{
    public function test_resolves_null_when_not_authenticated(): void
    {
        // Mock Auth facade
        Auth::shouldReceive('guard')
            ->once()
            ->andReturnSelf();

        Auth::shouldReceive('check')
            ->once()
            ->andReturn(false);

        $resolver = new CauserResolver;
        $result = $resolver->resolve();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertNull($result['type']);
        $this->assertNull($result['id']);
    }

    public function test_resolves_user_when_authenticated(): void
    {
        $user = new User;
        $user->id = 123;

        // Mock Auth facade
        Auth::shouldReceive('guard')
            ->once()
            ->andReturnSelf();

        Auth::shouldReceive('check')
            ->once()
            ->andReturn(true);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $resolver = new CauserResolver;
        $result = $resolver->resolve();

        $this->assertIsArray($result);
        $this->assertEquals(User::class, $result['type']);
        $this->assertEquals(123, $result['id']);
    }

    public function test_resolves_with_custom_guard(): void
    {
        $user = new User;
        $user->id = 456;

        // Mock Auth facade with custom guard
        Auth::shouldReceive('guard')
            ->once()
            ->with('api')
            ->andReturnSelf();

        Auth::shouldReceive('check')
            ->once()
            ->andReturn(true);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $resolver = new CauserResolver('api');
        $result = $resolver->resolve();

        $this->assertEquals(User::class, $result['type']);
        $this->assertEquals(456, $result['id']);
    }

    public function test_resolves_with_custom_model_class(): void
    {
        $user = new User;
        $user->id = 789;

        // Mock Auth facade
        Auth::shouldReceive('guard')
            ->once()
            ->andReturnSelf();

        Auth::shouldReceive('check')
            ->once()
            ->andReturn(true);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $customClass = 'App\\Models\\CustomUser';
        $resolver = new CauserResolver(null, $customClass);
        $result = $resolver->resolve();

        $this->assertEquals($customClass, $result['type']);
        $this->assertEquals(789, $result['id']);
    }

    public function test_resolves_null_when_user_is_null(): void
    {
        // Mock Auth facade
        Auth::shouldReceive('guard')
            ->once()
            ->andReturnSelf();

        Auth::shouldReceive('check')
            ->once()
            ->andReturn(true);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn(null);

        $resolver = new CauserResolver;
        $result = $resolver->resolve();

        $this->assertNull($result['type']);
        $this->assertNull($result['id']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
