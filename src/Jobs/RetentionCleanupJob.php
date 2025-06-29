<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Jobs;

use iamfarhad\LaravelAuditLog\Contracts\RetentionServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class RetentionCleanupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private ?string $entityType = null
    ) {
        // Configure queue settings
        $this->onQueue(config('audit-logger.queue.queue_name', 'audit'));
        $this->onConnection(config('audit-logger.queue.connection', null));
    }

    public function handle(RetentionServiceInterface $retentionService): void
    {
        if (! $retentionService->isRetentionEnabled()) {
            Log::info('Audit retention cleanup skipped - retention is disabled');

            return;
        }

        try {
            if ($this->entityType !== null) {
                $result = $retentionService->runCleanupForEntity($this->entityType);
                Log::info("Audit retention cleanup completed for {$this->entityType}", [
                    'entity' => $this->entityType,
                    'processed' => $result->totalProcessed,
                    'execution_time' => $result->executionTime,
                    'errors' => $result->errors,
                ]);
            } else {
                $result = $retentionService->runCleanup();
                Log::info('Audit retention cleanup completed for all entities', [
                    'total_processed' => $result->totalProcessed,
                    'entities_processed' => $result->entitiesProcessed,
                    'execution_time' => $result->executionTime,
                    'errors' => $result->errors,
                ]);
            }

            if ($result->hasErrors()) {
                Log::warning('Audit retention cleanup completed with errors', [
                    'errors' => $result->errors,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Audit retention cleanup failed', [
                'entity' => $this->entityType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a job for cleaning up a specific entity.
     */
    public static function forEntity(string $entityType): self
    {
        return new self($entityType);
    }

    /**
     * Create a job for cleaning up all entities.
     */
    public static function forAllEntities(): self
    {
        return new self;
    }
}
