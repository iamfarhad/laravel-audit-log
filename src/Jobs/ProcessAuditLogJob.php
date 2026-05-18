<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Jobs;

use iamfarhad\LaravelAuditLog\Contracts\AuditDriverInterface;
use iamfarhad\LaravelAuditLog\Contracts\AuditLogInterface;
use iamfarhad\LaravelAuditLog\Events\AuditCreated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

final class ProcessAuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AuditLogInterface $log,
        protected AuditDriverInterface $driver
    ) {
        $this->onQueue(config('audit-logger.queue.queue_name', 'audit'));
        $this->onConnection(config('audit-logger.queue.connection', null));

        $delay = config('audit-logger.queue.delay', 0);
        if ($delay > 0) {
            $this->delay($delay);
        }
    }

    public function handle(): void
    {
        $this->driver->store($this->log);

        Event::dispatch(new AuditCreated($this->log));
    }
}
