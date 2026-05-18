<?php

declare(strict_types=1);

namespace iamfarhad\LaravelAuditLog\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class AuditMigrationGenerator
{
    public function __construct(private readonly Filesystem $files) {}

    public function create(string $entityClass): string
    {
        $tableName = app(AuditTableNameResolver::class)->resolve($entityClass);
        $className = 'Create'.Str::studly($tableName).'Table';
        $timestamp = now()->format('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$tableName}_table.php");

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $this->stub($className, $tableName));

        return $path;
    }

    private function stub(string $className, string $tableName): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table): void {
            \$table->id();
            \$table->string('entity_id');
            \$table->string('action');
            \$table->json('old_values')->nullable();
            \$table->json('new_values')->nullable();
            \$table->json('changes')->nullable();
            \$table->string('causer_type')->nullable();
            \$table->string('causer_id')->nullable();
            \$table->string('tenant_type')->nullable();
            \$table->string('tenant_id')->nullable();
            \$table->json('metadata')->nullable();
            \$table->timestamp('created_at');
            \$table->string('source')->nullable();
            \$table->string('audit_hash', 128)->nullable();
            \$table->string('previous_hash', 128)->nullable();
            \$table->timestamp('anonymized_at')->nullable();
            \$table->index('entity_id');
            \$table->index('causer_id');
            \$table->index('tenant_id');
            \$table->index('created_at');
            \$table->index('action');
            \$table->index('source');
            \$table->index(['entity_id', 'action']);
            \$table->index(['entity_id', 'created_at']);
            \$table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }
}
