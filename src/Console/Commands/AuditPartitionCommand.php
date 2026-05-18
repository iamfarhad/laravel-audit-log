<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use Illuminate\Console\Command;

final class AuditPartitionCommand extends Command
{
    protected $signature = 'audit:partition {table : Audit table name} {--monthly : Show monthly partition guidance}';

    protected $description = 'Show database partitioning guidance for large audit tables';

    public function handle(): int
    {
        $table = (string) $this->argument('table');

        $this->info("Partitioning guidance for [{$table}]");
        $this->line('Use native database partitioning for production tables with very large audit volume.');
        $this->line('PostgreSQL: create range partitions by created_at.');
        $this->line('MySQL: use RANGE partitioning on a generated date key or TO_DAYS(created_at).');

        if ($this->option('monthly')) {
            $month = now()->format('Y_m');
            $this->newLine();
            $this->line("Example partition name: {$table}_{$month}");
        }

        return self::SUCCESS;
    }
}
