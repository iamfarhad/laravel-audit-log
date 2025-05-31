<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;

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
        protected ?string $driverName = null
    ) {
        $this->driverName = $driverName ?? config('audit-logger.default');
        $this->onQueue(config('audit-logger.queue.queue_name', 'default'));
        $this->onConnection(config('audit-logger.queue.connection', config('queue.default')));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Resolve the driver from the container
        $driver = App::make(AuditDriverInterface::class);

        // Store the log
        $driver->store($this->log);
    }
}
