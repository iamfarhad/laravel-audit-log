<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Jobs;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use Illuminate\Foundation\Bus\Dispatchable;

final class ProcessAuditLogSyncJob
{
    use Dispatchable;

    public function __construct(
        public AuditLogInterface $log,
        protected AuditDriverInterface $driver
    ) {}

    /**
     * Execute the job synchronously.
     */
    public function handle(): void
    {
        // Store the log
        $this->driver->store($this->log);
    }
}
