<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Console\Commands;

use iamfarhad\LaravelAuditLog\Contracts\RetentionServiceInterface;
use Illuminate\Console\Command;

final class CleanupAuditLogsCommand extends Command
{
    protected $signature = 'audit:cleanup 
                            {--entity= : Specific entity to clean up}
                            {--dry-run : Show what would be cleaned up without actually doing it}
                            {--force : Force cleanup without confirmation}';

    protected $description = 'Clean up old audit logs based on retention policies';

    public function handle(RetentionServiceInterface $retentionService): int
    {
        if (! $retentionService->isRetentionEnabled()) {
            $this->info('Audit log retention is disabled.');

            return self::SUCCESS;
        }

        $entity = $this->option('entity');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        if (! $force && ! $dryRun) {
            if (! $this->confirm('This will permanently modify/delete audit log data. Continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('🧹 Starting audit log cleanup...');
        $startTime = microtime(true);

        try {
            if ($entity) {
                $result = $this->cleanupSingleEntity($retentionService, $entity, $dryRun);
            } else {
                $result = $this->cleanupAllEntities($retentionService, $dryRun);
            }

            $this->displayResults($result, $dryRun);

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("✅ Cleanup completed in {$executionTime} seconds");

            return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function cleanupSingleEntity(RetentionServiceInterface $retentionService, string $entity, bool $dryRun)
    {
        if (! $retentionService->isRetentionEnabledForEntity($entity)) {
            $this->warn("Retention is not enabled for entity: {$entity}");

            return \iamfarhad\LaravelAuditLog\DTOs\RetentionResult::empty();
        }

        $this->info("Processing entity: {$entity}");

        if ($dryRun) {
            // In dry run mode, we would analyze what would be cleaned up
            $config = $retentionService->getRetentionConfig($entity);
            $this->line("  - Strategy: {$config['strategy']}");
            $this->line("  - Retention days: {$config['days']}");
            if ($config['strategy'] === 'anonymize' || isset($config['anonymize_after_days'])) {
                $this->line("  - Anonymize after: {$config['anonymize_after_days']} days");
            }

            return \iamfarhad\LaravelAuditLog\DTOs\RetentionResult::empty();
        }

        return $retentionService->runCleanupForEntity($entity);
    }

    private function cleanupAllEntities(RetentionServiceInterface $retentionService, bool $dryRun)
    {
        $entities = config('audit-logger.entities', []);

        if (empty($entities)) {
            $this->warn('No entities configured for audit logging.');

            return \iamfarhad\LaravelAuditLog\DTOs\RetentionResult::empty();
        }

        $this->info('Processing '.count($entities).' entities...');

        if ($dryRun) {
            foreach ($entities as $entityType => $config) {
                if ($retentionService->isRetentionEnabledForEntity($entityType)) {
                    $retentionConfig = $retentionService->getRetentionConfig($entityType);
                    $this->line("📋 {$entityType}:");
                    $this->line("  - Strategy: {$retentionConfig['strategy']}");
                    $this->line("  - Retention days: {$retentionConfig['days']}");
                }
            }

            return \iamfarhad\LaravelAuditLog\DTOs\RetentionResult::empty();
        }

        return $retentionService->runCleanup();
    }

    private function displayResults(\iamfarhad\LaravelAuditLog\DTOs\RetentionResult $result, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $this->newLine();
        $this->info('📊 Cleanup Results:');
        $this->info("Total records processed: {$result->totalProcessed}");

        if (! empty($result->entitiesProcessed)) {
            $this->line('Per entity breakdown:');
            foreach ($result->entitiesProcessed as $entity => $count) {
                $this->line("  - {$entity}: {$count} records");
            }
        }

        if ($result->hasErrors()) {
            $this->newLine();
            $this->error('⚠️  Errors encountered:');
            foreach ($result->errors as $error) {
                $this->error("  - {$error}");
            }
        }

        $this->line("Execution time: {$result->executionTime} seconds");
    }
}
