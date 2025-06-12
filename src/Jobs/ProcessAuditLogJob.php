<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Jobs;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessAuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string|null  $driverName  The name of the driver to use (default is the configured default)
     */
    public function __construct(
        public AuditLogInterface $log,
        protected AuditDriverInterface $driver
    ) {

        // Configure queue settings
        $this->onQueue(config('audit-logger.queue.queue_name', 'audit'));
        $this->onConnection(config('audit-logger.queue.connection', null));

        // Set delay if configured
        $delay = config('audit-logger.queue.delay', 0);
        if ($delay > 0) {
            $this->delay($delay);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Store the log
        $this->driver->store($this->log);
    }
}
