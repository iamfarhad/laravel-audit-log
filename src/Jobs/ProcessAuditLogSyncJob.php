<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

final class ProcessAuditLogSyncJob
{
    use Dispatchable;

    /**
     * @param  string|null  $driverName  The name of the driver to use (default is the configured default)
     */
    public function __construct(
        public AuditLogInterface $log,
        protected AuditDriverInterface $driver
    ) {
    }

    /**
     * Execute the job synchronously.
     */
    public function handle(): void
    {
        // Store the log
        $this->driver->store($this->log);
    }
}
