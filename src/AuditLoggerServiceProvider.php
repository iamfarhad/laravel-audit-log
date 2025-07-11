<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog;

use iamfarhad\LaravelAuditLog\Console\Commands\CleanupAuditLogsCommand;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\Contracts\RetentionServiceInterface;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Services\CauserResolver;
use iamfarhad\LaravelAuditLog\Services\RetentionService;
use Illuminate\Support\ServiceProvider;

final class AuditLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/audit-logger.php',
            'audit-logger'
        );

        $this->app->bind(AuditLogInterface::class, AuditLog::class);

        // Register the causer resolver
        $this->app->singleton(
            CauserResolverInterface::class,
            fn ($app) => isset($app['config']['audit-logger.causer']['resolver']) && $app['config']['audit-logger.causer']['resolver']
                ? $app->make($app['config']['audit-logger.causer']['resolver'])
                : new CauserResolver(
                    guard: $app['config']['audit-logger.causer']['guard'] ?? null,
                    modelClass: $app['config']['audit-logger.causer']['model'] ?? null
                )
        );

        // Register the main audit logger service - use fully qualified namespace
        $this->app->singleton(AuditLogger::class, function ($app) {
            $connection = $app['config']['audit-logger.drivers.mysql.connection'] ?? config('database.default');

            $driver = match ($app['config']['audit-logger.default']) {
                'mysql' => new MySQLDriver($connection),
                default => new MySQLDriver($connection),
            };

            return new AuditLogger($driver);
        });

        // Register the retention service
        $this->app->singleton(RetentionServiceInterface::class, RetentionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/audit-logger.php' => config_path('audit-logger.php'),
            ], 'audit-logger-config');

            // Register commands
            $this->commands([
                CleanupAuditLogsCommand::class,
            ]);
        }

        // Event listeners removed - using direct logging approach
    }
}
