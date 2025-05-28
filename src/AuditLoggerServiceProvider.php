<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog;

use Illuminate\Support\ServiceProvider;
use iamfarhad\LaravelAuditLog\Events\ModelAudited;
use iamfarhad\LaravelAuditLog\Services\AuditLogger;
use Illuminate\Support\Facades\Event as EventFacade;
use iamfarhad\LaravelAuditLog\Services\CauserResolver;
use iamfarhad\LaravelAuditLog\Listeners\AuditModelChanges;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;

final class AuditLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/audit-logger.php',
            'audit-logger'
        );

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

        // Register the main audit logger service
        $this->app->singleton('audit-logger', fn ($app) => new AuditLogger(
            config: $app['config']['audit-logger']
        ));

        $this->app->alias('audit-logger', alias: AuditLogger::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Config/audit-logger.php' => config_path('audit-logger.php'),
            ], 'audit-logger-config');
        }

        // Register event listeners
        EventFacade::listen(ModelAudited::class, [AuditModelChanges::class, 'handle']);
    }
}
