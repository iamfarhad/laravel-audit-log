<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Services\AuditMigrationGenerator;
use Illuminate\Console\Command;

final class MakeAuditMigrationCommand extends Command
{
    protected $signature = 'audit:make-migration {entity : Audited model class}';

    protected $description = 'Create a production-ready migration for an audit table';

    public function handle(AuditMigrationGenerator $generator): int
    {
        $entity = (string) $this->argument('entity');

        if (! class_exists($entity)) {
            $this->error("Entity class [{$entity}] does not exist.");

            return self::FAILURE;
        }

        $path = $generator->create($entity);
        $this->info("Created audit migration: {$path}");

        return self::SUCCESS;
    }
}
