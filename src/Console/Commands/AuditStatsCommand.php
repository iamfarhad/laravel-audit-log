<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Facades\AuditLogger;
use Illuminate\Console\Command;

final class AuditStatsCommand extends Command
{
    protected $signature = 'audit:stats {entity : Audited model class}';

    protected $description = 'Show audit log statistics for an entity';

    public function handle(): int
    {
        $entity = (string) $this->argument('entity');
        $summary = AuditLogger::analytics()->summary($entity);

        $this->info("Total: {$summary['total']}");
        $this->line('First audit: '.($summary['first_audit_at'] ?? 'n/a'));
        $this->line('Last audit: '.($summary['last_audit_at'] ?? 'n/a'));

        foreach ($summary['actions'] as $action => $count) {
            $this->line("{$action}: {$count}");
        }

        return self::SUCCESS;
    }
}
