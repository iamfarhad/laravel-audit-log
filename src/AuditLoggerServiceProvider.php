<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use iamfarhad\LaravelAuditLog\Services\CauserResolver;
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
        $this->app->singleton(\iamfarhad\LaravelAuditLog\Services\AuditLogger::class, function ($app) {
            $driver = match ($app['config']['audit-logger.default']) {
                'mysql' => new MySQLDriver,
                default => new MySQLDriver,
            };

            return new AuditLogger($driver);
        });
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
        }

        // Event listeners removed - using direct logging approach
    }
}
