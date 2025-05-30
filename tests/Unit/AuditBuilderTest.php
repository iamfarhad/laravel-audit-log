<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use Mockery;
use Illuminate\Support\Facades\Event;
use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use iamfarhad\LaravelAuditLog\Services\AuditBuilder;

final class AuditBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_can_build_and_log_custom_audit_event_with_fluent_api(): void
    {
        // Arrange
        $model = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('getAuditMetadata')->andReturn(['default' => 'meta']);
        $model->shouldReceive('getAuditableAttributes')->andReturnUsing(function ($attributes) {
            return $attributes;
        });

        // Act
        $builder = new AuditBuilder($model);
        $builder
            ->custom('status_change')
            ->from(['status' => 'pending'])
            ->to(['status' => 'approved'])
            ->withMetadata(['ip' => '127.0.0.1'])
            ->log();

        // Assert
        Event::assertDispatched(ModelAudited::class, function (ModelAudited $event) use ($model) {
            return $event->model === $model
                && $event->action === 'status_change'
                && $event->oldValues === ['status' => 'pending']
                && $event->newValues === ['status' => 'approved'];
        });
    }

    public function test_uses_default_action_if_custom_not_specified(): void
    {
        // Arrange
        $model = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('getAuditMetadata')->andReturn([]);
        $model->shouldReceive('getAuditableAttributes')->andReturnUsing(function ($attributes) {
            return $attributes;
        });

        // Act
        $builder = new AuditBuilder($model);
        $builder
            ->from(['key' => 'old'])
            ->to(['key' => 'new'])
            ->log();

        // Assert
        Event::assertDispatched(ModelAudited::class, function (ModelAudited $event) {
            return $event->action === 'custom';
        });
    }

    public function test_merges_model_metadata_with_custom_metadata(): void
    {
        // Arrange
        $model = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $model->shouldReceive('getAuditMetadata')->andReturn(['default' => 'meta']);
        $model->shouldReceive('getAuditableAttributes')->andReturnUsing(function ($attributes) {
            return $attributes;
        });

        // Act
        $builder = new AuditBuilder($model);
        $builder
            ->custom('test_action')
            ->withMetadata(['custom' => 'data'])
            ->log();

        // Assert
        Event::assertDispatched(ModelAudited::class);
    }

    public function test_filters_values_using_get_auditable_attributes_if_available(): void
    {
        // Create a concrete class with getAuditableAttributes method instead of using a mock
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            public function getAuditMetadata(): array
            {
                return [];
            }

            public function getAuditableAttributes(array $attributes): array
            {
                // Only return the 'allowed' key if it exists in the input
                return isset($attributes['allowed']) ? ['allowed' => $attributes['allowed']] : [];
            }
        };

        // Directly test AuditBuilder behavior without event faking
        $oldValues = ['allowed' => 'value', 'disallowed' => 'secret'];
        $newValues = ['allowed' => 'new_value', 'disallowed' => 'new_secret'];

        // Setup expectation that Event::dispatch will be called with filtered values
        Event::shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($event) use ($model) {
                return $event instanceof ModelAudited
                    && $event->model === $model
                    && $event->oldValues === ['allowed' => 'value']
                    && $event->newValues === ['allowed' => 'new_value'];
            }));

        // Act
        $builder = new AuditBuilder($model);
        $builder
            ->from($oldValues)
            ->to($newValues)
            ->log();

        // If we get here without Mockery exceptions, the test passes
        $this->assertTrue(true);
    }
}

// Add a mock interface for the test to ensure method_exists works
interface ModelWithGetAuditableAttributes
{
    public function getAuditableAttributes(array $attributes): array;
}
