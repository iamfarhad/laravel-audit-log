<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Drivers\MySQLDriver;
use iamfarhad\LaravelAuditLog\Drivers\PostgreSQLDriver;
use Illuminate\Console\Command;

final class AuditMigrateCommand extends Command
{
    protected $signature = 'audit:migrate {--entity= : Only create storage for one audited entity class}';

    protected $description = 'Create audit storage for configured audited entities';

    public function handle(): int
    {
        $entities = $this->option('entity')
            ? [(string) $this->option('entity')]
            : array_keys((array) config('audit-logger.entities', []));

        if ($entities === []) {
            $this->warn('No audited entities configured.');

            return self::SUCCESS;
        }

        $driver = $this->driver();

        foreach ($entities as $entityClass) {
            if (! class_exists($entityClass)) {
                $this->error("Entity class [{$entityClass}] does not exist.");

                return self::FAILURE;
            }

            if ($driver->storageExistsForEntity($entityClass)) {
                $this->info("Audit storage already exists for {$entityClass}.");
                continue;
            }

            $driver->createStorageForEntity($entityClass);
            $this->info("Created audit storage for {$entityClass}.");
        }

        return self::SUCCESS;
    }

    private function driver(): MySQLDriver|PostgreSQLDriver
    {
        $driver = (string) config('audit-logger.default', 'mysql');
        $connection = config("audit-logger.drivers.{$driver}.connection", config('database.default'));

        return match ($driver) {
            'mysql' => new MySQLDriver($connection),
            'postgresql', 'pgsql' => new PostgreSQLDriver($connection),
            default => throw new \InvalidArgumentException("Unsupported audit driver [{$driver}]."),
        };
    }
}
