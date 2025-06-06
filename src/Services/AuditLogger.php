<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

final class AuditLogger
{
    public function __construct(private AuditDriverInterface $driver) {}

    public function log(AuditLogInterface $log): void
    {
        $this->driver->store($log);
    }

    /**
     * @param  array<AuditLogInterface>  $logs
     *
     * @throws \Exception
     */
    public function batch(array $logs): void
    {
        $this->driver->storeBatch($logs);
    }

    public static function getDriver(string $driverName): static
    {
        $driver = match ($driverName) {
            'mysql' => new MySQLDriver,
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
