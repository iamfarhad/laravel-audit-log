<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogJob;
use iamfarhad\LaravelAuditLog\Jobs\ProcessAuditLogSyncJob;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;

final class AuditLogger
{
    public function __construct(private AuditDriverInterface $driver) {}

    public function log(AuditLogInterface $log): void
    {
        if ((bool) config('audit-logger.queue.enabled', false)) {
            ProcessAuditLogJob::dispatch($log, $this->driver);
        } else {
            ProcessAuditLogSyncJob::dispatchSync($log, $this->driver);
        }
    }

    /**
     * @param  array<AuditLogInterface>  $logs
     *
     * @throws \Exception
     */
    public function batch(array $logs): void
    {
        if ((bool) config('audit-logger.queue.enabled', false)) {
            foreach ($logs as $log) {
                ProcessAuditLogJob::dispatch($log, $this->driver);
            }
        } else {
            foreach ($logs as $log) {
                ProcessAuditLogSyncJob::dispatchSync($log, $this->driver);
            }
        }
    }

    public static function getDriver(string $driverName, ?string $connection = null): static
    {
        $connection = $connection ?? config('audit-logger.drivers.mysql.connection') ?? config('database.default');

        $driver = match ($driverName) {
            'mysql' => new MySQLDriver($connection),
            default => throw new \InvalidArgumentException("Driver {$driverName} not found"),
        };

        return new self($driver);
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
