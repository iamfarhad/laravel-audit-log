<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Drivers\PostgreSQLDriver;
use iamfarhad\LaravelAuditLog\Events\AuditCreated;
use iamfarhad\LaravelAuditLog\Events\AuditCreating;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogJob;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogSyncJob;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;

final class AuditLogger
{
    public function __construct(private AuditDriverInterface $driver) {}

    public function log(AuditLogInterface $log): void
    {
        if (! (bool) config('audit-logger.enabled', true)) {
            return;
        }

        Event::dispatch(new AuditCreating($log));

        if ((bool) config('audit-logger.queue.enabled', false)) {
            ProcessAuditLogJob::dispatch($log, $this->driver);
        } else {
            ProcessAuditLogSyncJob::dispatchSync($log, $this->driver);
        }

        Event::dispatch(new AuditCreated($log));
    }

    /** @param array<AuditLogInterface> $logs */
    public function batch(array $logs): void
    {
        if (! (bool) config('audit-logger.enabled', true) || $logs === []) {
            return;
        }

        if ((bool) config('audit-logger.batch.enabled', false) && ! (bool) config('audit-logger.queue.enabled', false)) {
            foreach ($logs as $log) {
                Event::dispatch(new AuditCreating($log));
            }

            $this->driver->storeBatch($logs);

            foreach ($logs as $log) {
                Event::dispatch(new AuditCreated($log));
            }

            return;
        }

        foreach ($logs as $log) {
            $this->log($log);
        }
    }

    public static function getDriver(string $driverName, ?string $connection = null): static
    {
        $connection = $connection
            ?? config("audit-logger.drivers.{$driverName}.connection")
            ?? config('database.default');

        $driver = match ($driverName) {
            'mysql' => new MySQLDriver($connection),
            'postgresql', 'pgsql' => new PostgreSQLDriver($connection),
            default => throw new \InvalidArgumentException("Driver {$driverName} not found"),
        };

        return new self($driver);
    }

    public function query(string $entityClass): AuditQuery
    {
        return new AuditQuery($entityClass);
    }

    public function search(string $entityClass, string $term): AuditQuery
    {
        return $this->query($entityClass)->search($term);
    }

    public function analytics(): AuditAnalytics
    {
        return app(AuditAnalytics::class);
    }

    public function timeline(): AuditTimeline
    {
        return app(AuditTimeline::class);
    }

    public function restorer(): AuditRestorer
    {
        return app(AuditRestorer::class);
    }

    /** @return array{valid: bool, checked: int, failures: array<int, array<string, mixed>>} */
    public function verifyHashChain(string $entityClass, string|int|null $entityId = null): array
    {
        return app(AuditHashVerifier::class)->verify($entityClass, $entityId);
    }

    public function getSource(): ?string
    {
        if (App::runningInConsole()) {
            $command = request()->server('argv')[1] ?? null;

            if ($command) {
                return $command;
            }
        }

        $route = Request::route();
        if ($route !== null && is_object($route) && method_exists($route, 'getActionName')) {
            $controller = $route->getActionName();

            return is_string($controller) ? $controller : 'http';
        }

        return null;
    }
}
