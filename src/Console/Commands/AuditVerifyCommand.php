<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Facades\AuditLogger;
use Illuminate\Console\Command;

final class AuditVerifyCommand extends Command
{
    protected $signature = 'audit:verify {entity : Audited model class} {--entity-id= : Limit verification to one entity id} {--fail-on-missing-hash : Return failure for missing hash columns}';

    protected $description = 'Verify tamper-evident audit hash chains';

    public function handle(): int
    {
        $result = AuditLogger::verifyHashChain((string) $this->argument('entity'), $this->option('entity-id'));

        $this->line('Checked: '.$result['checked']);

        if ($result['valid']) {
            $this->info('Audit hash chain is valid.');

            return self::SUCCESS;
        }

        foreach ($result['failures'] as $failure) {
            $this->error(($failure['code'] ?? 'hash_failure').': '.($failure['message'] ?? 'Audit verification failed.'));
        }

        if (! $this->option('fail-on-missing-hash')) {
            $codes = array_column($result['failures'], 'code');
            if (in_array('missing_hash_columns', $codes, true)) {
                return self::SUCCESS;
            }
        }

        return self::FAILURE;
    }
}
