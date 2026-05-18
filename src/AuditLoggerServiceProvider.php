<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog;

use iamfarhad\LaravelAuditLog\Console\Commands\AuditConfigCheckCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditDiffCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditDoctorCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditPartitionCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditStatsCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditTimelineCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditUpgradeCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\AuditVerifyCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\CleanupAuditLogsCommand;
use iamfarhad\LaravelAuditLog\Console\Commands\MakeAuditMigrationCommand;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\Contracts\RetentionServiceInterface;
use iamfarhad\LaravelAuditLog\Contracts\TenantResolverInterface;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Drivers\PostgreSQLDriver;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditAnalytics;
use iamfarhad\LaravelAuditLog\Services\AuditAuthorization;
use iamfarhad\LaravelAuditLog\Services\AuditChangeSet;
use iamfarhad\LaravelAuditLog\Services\AuditConfigValidator;
use iamfarhad\LaravelAuditLog\Services\AuditFieldTransformer;
use iamfarhad\LaravelAuditLog\Services\AuditHash;
use iamfarhad\LaravelAuditLog\Services\AuditHashVerifier;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Services\AuditMigrationGenerator;
use iamfarhad\LaravelAuditLog\Services\AuditRestorer;
use iamfarhad\LaravelAuditLog\Services\AuditSnapshotService;
use iamfarhad\LaravelAuditLog\Services\AuditTableNameResolver;
use iamfarhad\LaravelAuditLog\Services\AuditTimeline;
use iamfarhad\LaravelAuditLog\Services\CauserResolver;
use iamfarhad\LaravelAuditLog\Services\RelationshipAuditor;
use iamfarhad\LaravelAuditLog\Services\RetentionService;
use iamfarhad\LaravelAuditLog\Services\TenantResolver;
use Illuminate\Support\ServiceProvider;

final class AuditLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/audit-logger.php', 'audit-logger');

        $this->app->bind(AuditLogInterface::class, AuditLog::class);

        $this->app->singleton(
            CauserResolverInterface::class,
            fn ($app) => isset($app['config']['audit-logger.causer']['resolver']) && $app['config']['audit-logger.causer']['resolver']
                ? $app->make($app['config']['audit-logger.causer']['resolver'])
                : new CauserResolver(
                    guard: $app['config']['audit-logger.causer']['guard'] ?? null,
                    modelClass: $app['config']['audit-logger.causer']['model'] ?? null
                )
        );

        $this->app->singleton(TenantResolverInterface::class, function ($app) {
            $resolver = $app['config']['audit-logger.tenant']['resolver'] ?? TenantResolver::class;

            return $resolver === TenantResolver::class ? new TenantResolver : $app->make($resolver);
        });

        $this->app->singleton(AuditLogger::class, function ($app) {
            $driverName = $app['config']['audit-logger.default'] ?? 'mysql';
            $connection = $app['config']["audit-logger.drivers.{$driverName}.connection"] ?? config('database.default');

            $driver = match ($driverName) {
                'mysql' => new MySQLDriver($connection),
                'postgresql', 'pgsql' => new PostgreSQLDriver($connection),
                default => throw new \InvalidArgumentException("Driver {$driverName} not found"),
            };

            return new AuditLogger($driver);
        });

        $this->app->alias(AuditLogger::class, 'audit-logger');
        $this->app->singleton(AuditAnalytics::class);
        $this->app->singleton(AuditAuthorization::class);
        $this->app->singleton(AuditChangeSet::class);
        $this->app->singleton(AuditConfigValidator::class);
        $this->app->singleton(AuditFieldTransformer::class);
        $this->app->singleton(AuditHash::class);
        $this->app->singleton(AuditHashVerifier::class);
        $this->app->singleton(AuditMigrationGenerator::class);
        $this->app->singleton(AuditRestorer::class);
        $this->app->singleton(AuditSnapshotService::class);
        $this->app->singleton(AuditTableNameResolver::class);
        $this->app->singleton(AuditTimeline::class);
        $this->app->singleton(RelationshipAuditor::class);
        $this->app->singleton(RetentionServiceInterface::class, RetentionService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/audit-logger.php' => config_path('audit-logger.php'),
            ], 'audit-logger-config');

            $this->commands([
                AuditConfigCheckCommand::class,
                AuditDiffCommand::class,
                AuditDoctorCommand::class,
                AuditPartitionCommand::class,
                AuditStatsCommand::class,
                AuditTimelineCommand::class,
                AuditUpgradeCommand::class,
                AuditVerifyCommand::class,
                CleanupAuditLogsCommand::class,
                MakeAuditMigrationCommand::class,
            ]);
        }
    }
}
