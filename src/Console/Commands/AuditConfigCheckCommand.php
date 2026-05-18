<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Services\AuditConfigValidator;
use Illuminate\Console\Command;

final class AuditConfigCheckCommand extends Command
{
    protected $signature = 'audit:config-check';

    protected $description = 'Validate audit logger configuration';

    public function handle(AuditConfigValidator $validator): int
    {
        $result = $validator->validate();

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if ($result['errors'] !== []) {
            return self::FAILURE;
        }

        $this->info('Audit configuration is valid.');

        return self::SUCCESS;
    }
}
