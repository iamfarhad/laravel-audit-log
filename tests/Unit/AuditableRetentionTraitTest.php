<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Tests\Unit;

use iamfarhad\LaravelAuditLog\Tests\TestCase;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

final class AuditableRetentionTraitTest extends TestCase
{
    #[Test]
    public function it_returns_empty_retention_config_by_default(): void
    {
        $model = new TestModelWithoutRetention;

        $config = $model->getAuditRetentionConfig();

        $this->assertEmpty($config);
    }

    #[Test]
    public function it_returns_model_specific_retention_config(): void
    {
        $model = new TestModelWithRetention;

        $config = $model->getAuditRetentionConfig();

        $this->assertEquals([
            'enabled' => true,
            'days' => 180,
            'strategy' => 'anonymize',
        ], $config);
    }

    #[Test]
    public function it_checks_retention_enabled_from_model_config(): void
    {
        Config::set('audit-logger.retention.enabled', false);

        $enabledModel = new TestModelWithRetention;
        $this->assertTrue($enabledModel->isRetentionEnabled());

        $disabledModel = new TestModelWithDisabledRetention;
        $this->assertFalse($disabledModel->isRetentionEnabled());
    }

    #[Test]
    public function it_falls_back_to_global_retention_setting(): void
    {
        Config::set('audit-logger.retention.enabled', true);

        $model = new TestModelWithoutRetention;
        $this->assertTrue($model->isRetentionEnabled());

        Config::set('audit-logger.retention.enabled', false);
        $this->assertFalse($model->isRetentionEnabled());
    }

    #[Test]
    public function it_gets_retention_strategy_from_model_or_global(): void
    {
        Config::set('audit-logger.retention.strategy', 'delete');

        $modelWithStrategy = new TestModelWithRetention;
        $this->assertEquals('anonymize', $modelWithStrategy->getRetentionStrategy());

        $modelWithoutStrategy = new TestModelWithoutRetention;
        $this->assertEquals('delete', $modelWithoutStrategy->getRetentionStrategy());
    }

    #[Test]
    public function it_gets_retention_days_from_model_or_global(): void
    {
        Config::set('audit-logger.retention.days', 365);

        $modelWithDays = new TestModelWithRetention;
        $this->assertEquals(180, $modelWithDays->getRetentionDays());

        $modelWithoutDays = new TestModelWithoutRetention;
        $this->assertEquals(365, $modelWithoutDays->getRetentionDays());
    }

    #[Test]
    public function it_gets_anonymize_days_from_model_or_global(): void
    {
        Config::set('audit-logger.retention.anonymize_after_days', 90);

        $model = new TestModelWithoutRetention;
        $this->assertEquals(90, $model->getAnonymizeDays());

        // Test with model-specific setting
        $modelWithConfig = new TestModelWithAnonymization;
        $this->assertEquals(60, $modelWithConfig->getAnonymizeDays());
    }
}

/**
 * Test model without retention configuration
 */
class TestModelWithoutRetention extends Model
{
    use Auditable;

    protected $table = 'test_models';
}

/**
 * Test model with retention configuration
 */
class TestModelWithRetention extends Model
{
    use Auditable;

    protected $table = 'test_models';

    protected array $auditRetention = [
        'enabled' => true,
        'days' => 180,
        'strategy' => 'anonymize',
    ];
}

/**
 * Test model with disabled retention
 */
class TestModelWithDisabledRetention extends Model
{
    use Auditable;

    protected $table = 'test_models';

    protected array $auditRetention = [
        'enabled' => false,
    ];
}

/**
 * Test model with anonymization configuration
 */
class TestModelWithAnonymization extends Model
{
    use Auditable;

    protected $table = 'test_models';

    protected array $auditRetention = [
        'anonymize_after_days' => 60,
    ];
}
