# Advanced Audit Features

This document covers the advanced APIs added for search, analytics, timelines, field-level diffs, tamper-evident audit chains, restore/replay workflows, and field redaction/transformers.

## Audit search

```php
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;

$logs = AuditLogger::search(App\Models\Order::class, 'approved')
    ->forAction('updated')
    ->between(now()->subMonth(), now())
    ->latest()
    ->paginate(50);
```

You can also build a query without a search term:

```php
$logs = AuditLogger::query(App\Models\Order::class)
    ->forEntityId($order->id)
    ->forCauser(App\Models\User::class, auth()->id())
    ->get();
```

## Analytics

```php
$summary = AuditLogger::analytics()->summary(App\Models\Order::class);
$topActions = AuditLogger::analytics()->topActions(App\Models\Order::class);
$topCausers = AuditLogger::analytics()->topCausers(App\Models\Order::class);
$changesPerDay = AuditLogger::analytics()->changesPerDay(App\Models\Order::class, 30);
```

## Timeline and diff API

Audited models expose a presentation-friendly timeline and field-level diffs:

```php
$timeline = $order->auditTimeline();
$latestDiff = $order->auditDiff();
$specificDiff = $order->auditDiff($auditLogId);
```

Each timeline entry contains the action, entity id, causer, source, timestamp, metadata, hash values, and normalized field changes.

## Restore, replay, and rollback

```php
// Apply the audit log's new values to the model.
$order->restoreFromAudit($auditLogId);

// Apply the audit log's old values to the model.
$order->rollbackToAudit($auditLogId);

// Preview without saving or mutating persistent state.
$preview = $order->previewRestore($auditLogId, 'rollback');
```

Use `restoreFromAudit()` for replaying a previous audited state and `rollbackToAudit()` for reverting to the previous values captured by an update log.

## Tamper-evident hash chain

Hash-chain storage is disabled by default for backward compatibility. Enable it only after your existing audit tables have the required nullable columns.

```env
AUDIT_HASH_CHAIN_ENABLED=false
```

Add these columns to every existing audit table before enabling hash-chain storage:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'audit_users_logs',
        'audit_orders_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'audit_hash')) {
                    $table->string('audit_hash', 128)->nullable()->after('source')->index();
                }

                if (! Schema::hasColumn($tableName, 'previous_hash')) {
                    $table->string('previous_hash', 128)->nullable()->after('audit_hash')->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'previous_hash')) {
                    $table->dropColumn('previous_hash');
                }

                if (Schema::hasColumn($tableName, 'audit_hash')) {
                    $table->dropColumn('audit_hash');
                }
            });
        }
    }
};
```

Then enable hash-chain storage in `config/audit-logger.php` or `.env`:

```env
AUDIT_HASH_CHAIN_ENABLED=true
AUDIT_HASH_ALGORITHM=sha256
AUDIT_HASH_KEY=base64-or-secret-key
```

When enabled, each new audit row stores:

- `previous_hash`: the prior audit row hash for that audited entity table
- `audit_hash`: an HMAC of the canonical audit payload plus `previous_hash`

Rows created before hash-chain mode is enabled will not have historical hash values. Treat the first hashed row after enabling as the start of the verifiable chain.

Verify a chain:

```php
$result = AuditLogger::verifyHashChain(App\Models\Order::class);

if (! $result['valid']) {
    report($result['failures']);
}
```

If the audit table or hash columns are missing, verification returns a structured failure with a `missing_audit_table` or `missing_hash_columns` code instead of throwing a database exception.

## Field redaction and transformers

Use redaction to replace sensitive values with a fixed placeholder:

```php
'fields' => [
    'redact' => ['national_id', 'card_number'],
    'redaction_replacement' => '[REDACTED]',
],
```

Use transformers when the value should be masked, hashed, normalized, or formatted before it is stored:

```php
'fields' => [
    'transformers' => [
        'email' => \iamfarhad\LaravelAuditLog\Transformers\MaskEmailTransformer::class,
        'phone' => \iamfarhad\LaravelAuditLog\Transformers\MaskValueTransformer::class,
        'api_token' => \iamfarhad\LaravelAuditLog\Transformers\HashValueTransformer::class,
    ],
],
```

Custom transformers implement `FieldTransformerInterface`:

```php
use iamfarhad\LaravelAuditLog\Contracts\FieldTransformerInterface;
use Illuminate\Database\Eloquent\Model;

final class MoneyTransformer implements FieldTransformerInterface
{
    public function transform(string $field, mixed $value, string $direction, Model $model): mixed
    {
        return number_format((float) $value, 2, '.', '');
    }
}
```

## PostgreSQL support

The package now resolves PostgreSQL consistently through both the service provider and `AuditLogger::getDriver()`:

```php
$logger = \iamfarhad\LaravelAuditLog\Services\AuditLogger::getDriver('postgresql');
$logger = \iamfarhad\LaravelAuditLog\Services\AuditLogger::getDriver('pgsql');
```

PostgreSQL audit tables use `jsonb` for `old_values`, `new_values`, and `metadata`.

## Comparison

| Capability | Laravel Audit Logger | Spatie Activitylog | Laravel Auditing |
|---|---:|---:|---:|
| Entity-specific audit tables | Yes | No | No |
| MySQL storage | Yes | Yes | Yes |
| PostgreSQL storage | Yes | Via database support | Via database support |
| Queue support | Yes | App-managed | App-managed |
| Source tracking | Yes | Partial/manual | Resolver-based |
| Audit search API | Yes | Query activity model | Query audit model |
| Analytics helpers | Yes | No built-in analytics helper | No built-in analytics helper |
| Timeline/diff API | Yes | Manual formatting | Auditable transition data |
| Restore/replay API | Yes | No built-in model restore | Limited/manual workflows |
| Tamper-evident hash chain | Yes | No | No |
| Field redactors/transformers | Yes | Custom pipes/manual | Attribute modifiers/resolvers |
| Retention strategies | Delete, anonymize, archive | Manual cleanup | Manual cleanup |

The main positioning for this package is high-performance, compliance-ready model auditing with per-entity tables, source tracking, retention policies, and tamper-evident history.
