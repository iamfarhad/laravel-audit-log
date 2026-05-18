# v2 Major Release Infrastructure

This guide explains the v2 infrastructure layer added on top of the advanced audit APIs. It is written for package users who want production-grade audit tables, high-volume writes, multi-tenant context, secure restore workflows, and operational tooling.

The v2 work intentionally excludes these items by request:

- Filament plugin
- Nova / Backpack integrations
- New external storage drivers such as ClickHouse, OpenSearch, S3, and webhook drivers

## What v2 Adds

v2 turns the package from a model audit logger into a production audit infrastructure package.

| Area | Added support |
|---|---|
| Production storage | Migration generation, explicit audit storage creation, upgrade checks |
| High volume | Native grouped batch inserts when safe |
| SaaS | Tenant resolver and tenant columns |
| Compliance | Append-only mode, hash-chain verification, verification failure events |
| Admin UX | Timeline, diff, stats, API resources, relationship audit helpers |
| Operations | Doctor, config check, stats, timeline, diff, verify, partition guidance, upgrade commands |
| Recovery | Safer restore/rollback with authorization and fillable filtering |

## Recommended Production Setup

For production systems, avoid relying only on runtime auto-migration. A safer setup is:

```env
AUDIT_AUTO_MIGRATION=false
AUDIT_QUEUE_ENABLED=true
AUDIT_QUEUE_CONNECTION=redis
AUDIT_QUEUE_NAME=audit
AUDIT_APPEND_ONLY=true
AUDIT_HASH_CHAIN_ENABLED=false
```

Then generate and run migrations:

```bash
php artisan audit:make-migration App\Models\Order
php artisan migrate
```

Or create storage explicitly for configured entities:

```bash
php artisan audit:migrate
```

Use hash-chain mode only after `audit_hash` and `previous_hash` columns exist.

## Migration Tooling

### Generate an audit table migration

```bash
php artisan audit:make-migration App\Models\Order
```

The generated migration includes v2-ready columns:

```text
id
entity_id
action
old_values
new_values
changes
causer_type
causer_id
tenant_type
tenant_id
metadata
created_at
source
audit_hash
previous_hash
anonymized_at
```

### Create audit storage directly

```bash
php artisan audit:migrate
php artisan audit:migrate --entity=App\Models\Order
```

`audit:migrate` explicitly creates missing storage. It does not depend on runtime `AUDIT_AUTO_MIGRATION=true`.

### Check upgrade readiness

```bash
php artisan audit:upgrade v1-to-v2 --dry-run
```

The command reports missing v2 columns:

```text
audit_hash
previous_hash
tenant_type
tenant_id
changes
```

## Existing Table Migration Example

For a MySQL audit table:

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('audit_orders_logs', function (Blueprint $table): void {
    $table->json('changes')->nullable()->after('new_values');
    $table->string('tenant_type')->nullable()->after('causer_id');
    $table->string('tenant_id')->nullable()->after('tenant_type');
    $table->string('audit_hash', 128)->nullable()->after('source');
    $table->string('previous_hash', 128)->nullable()->after('audit_hash');

    $table->index('tenant_id');
    $table->index(['tenant_id', 'created_at']);
    $table->index('audit_hash');
    $table->index('previous_hash');
});
```

For PostgreSQL, prefer `jsonb` for `changes`:

```php
Schema::table('audit_orders_logs', function (Blueprint $table): void {
    $table->jsonb('changes')->nullable();
    $table->string('tenant_type')->nullable();
    $table->string('tenant_id')->nullable();
    $table->string('audit_hash', 128)->nullable();
    $table->string('previous_hash', 128)->nullable();

    $table->index('tenant_id');
    $table->index(['tenant_id', 'created_at']);
    $table->index('audit_hash');
    $table->index('previous_hash');
});
```

## Batch Inserts

Batch mode is useful for imports, backfills, ETL jobs, and high-volume internal workflows.

Enable it:

```env
AUDIT_BATCH_ENABLED=true
AUDIT_BATCH_SIZE=500
AUDIT_QUEUE_ENABLED=false
```

Use it:

```php
use iamfarhad\LaravelAuditLog\DTOs\AuditLog;
use iamfarhad\LaravelAuditLog\Facades\AuditLogger;

AuditLogger::batch([
    AuditLog::fromArray([
        'entity_type' => App\Models\Order::class,
        'entity_id' => 1,
        'action' => 'imported',
        'old_values' => null,
        'new_values' => ['status' => 'paid'],
        'metadata' => ['source' => 'legacy_import'],
        'created_at' => now(),
    ]),
]);
```

Native batch mode is intentionally disabled when hash-chain mode is enabled. Hash chains must be generated sequentially row by row.

## Multi-Tenancy

Enable tenant context:

```env
AUDIT_TENANT_ENABLED=true
```

Create a resolver:

```php
<?php

declare(strict_types=1);

namespace App\Audit;

use iamfarhad\LaravelAuditLog\Contracts\TenantResolverInterface;

final class CurrentTenantResolver implements TenantResolverInterface
{
    public function resolve(): array
    {
        $tenant = tenant();

        return [
            'type' => $tenant?->getMorphClass(),
            'id' => $tenant?->getKey(),
        ];
    }
}
```

Configure it:

```php
'tenant' => [
    'enabled' => true,
    'resolver' => App\Audit\CurrentTenantResolver::class,
    'columns' => [
        'type' => 'tenant_type',
        'id' => 'tenant_id',
    ],
],
```

Query tenant-specific logs:

```php
use iamfarhad\LaravelAuditLog\Models\EloquentAuditLog;

$logs = EloquentAuditLog::forEntity(App\Models\Order::class)
    ->forTenant($tenant->id, $tenant::class)
    ->latest()
    ->paginate();
```

## Append-Only Mode

Enable append-only protection:

```env
AUDIT_APPEND_ONLY=true
```

When enabled, `EloquentAuditLog` prevents Eloquent updates and deletes. This protects application-level writes. Database administrators can still modify rows directly, so combine this with hash-chain verification for stronger tamper detection.

## Hash-Chain Verification

Hash-chain mode remains disabled by default.

```env
AUDIT_HASH_CHAIN_ENABLED=true
AUDIT_HASH_ALGORITHM=sha256
AUDIT_HASH_KEY=your-private-audit-key
```

Each row stores:

- `previous_hash`: hash from the previous audit row in the same audit table;
- `audit_hash`: HMAC of the canonical audit payload plus `previous_hash`.

Verify through code:

```php
$result = AuditLogger::verifyHashChain(App\Models\Order::class);

if (! $result['valid']) {
    report($result['failures']);
}
```

Verify through Artisan:

```bash
php artisan audit:verify App\Models\Order
php artisan audit:verify App\Models\Order --entity-id=123
php artisan audit:verify App\Models\Order --fail-on-missing-hash
```

When verification fails, the package dispatches `AuditVerificationFailed`.

## Relationship Auditing

Relationship auditing is explicit. The package does not monkey-patch Eloquent relation methods.

```php
use iamfarhad\LaravelAuditLog\Services\RelationshipAuditor;

app(RelationshipAuditor::class)->attached($user, 'roles', [$roleId]);
app(RelationshipAuditor::class)->detached($user, 'roles', [$roleId]);
app(RelationshipAuditor::class)->synced($user, 'roles', $roleIds);
```

This writes actions like:

```text
roles_attached
roles_detached
roles_synced
```

You can add metadata:

```php
app(RelationshipAuditor::class)->attached(
    $user,
    'roles',
    [$roleId],
    ['reason' => 'admin_grant']
);
```

## Restore and Rollback Safety

Enable authorization:

```env
AUDIT_AUTHORIZATION_ENABLED=true
```

Configure gates:

```php
Gate::define('viewAuditLogs', fn ($user, $model) => $user->can('view', $model));
Gate::define('restoreFromAudit', fn ($user, $model) => $user->can('update', $model));
```

Configure restore behavior:

```env
AUDIT_RESTORE_VALIDATE_FILLABLE=true
AUDIT_RESTORE_AUDIT=true
```

Options:

- `AUDIT_RESTORE_VALIDATE_FILLABLE=true`: only apply fields from the model's `$fillable` array.
- `AUDIT_RESTORE_AUDIT=false`: save restored models without creating a new audit row.

Usage:

```php
$order->restoreFromAudit($auditLogId);
$order->rollbackToAudit($auditLogId);
$preview = $order->previewRestore($auditLogId, 'rollback');
```

## Computed Change Sets

Enable stored field-level changes:

```env
AUDIT_STORE_CHANGES=true
```

The package writes a `changes` array when the column exists:

```php
[
    [
        'field' => 'status',
        'old' => 'pending',
        'new' => 'approved',
        'type' => 'string',
        'label' => 'Status',
    ],
]
```

This makes dashboards and APIs faster because they do not need to recompute diffs repeatedly.

## Snapshots

Snapshots periodically capture the full model state.

```env
AUDIT_SNAPSHOTS_ENABLED=true
AUDIT_SNAPSHOT_EVERY=20
```

Manual snapshot:

```php
app(\iamfarhad\LaravelAuditLog\Services\AuditSnapshotService::class)->snapshot($order);
```

Automatic helper:

```php
app(\iamfarhad\LaravelAuditLog\Services\AuditSnapshotService::class)->maybeSnapshot($order);
```

Snapshot rows use the action `snapshot` and store the model attributes in `new_values`.

## API Resources

Use package resources in your own controllers:

```php
use iamfarhad\LaravelAuditLog\Http\Resources\AuditLogResource;
use iamfarhad\LaravelAuditLog\Http\Resources\AuditTimelineResource;
use iamfarhad\LaravelAuditLog\Http\Resources\AuditDiffResource;

return AuditLogResource::collection(
    AuditLogger::query(App\Models\Order::class)->latest()->paginate()
);
```

Timeline:

```php
return AuditTimelineResource::collection($order->auditTimeline());
```

Diff:

```php
return AuditDiffResource::collection($order->auditDiff($auditLogId));
```

## Operational Commands

| Command | Description |
|---|---|
| `audit:make-migration App\Models\Order` | Generate an audit table migration |
| `audit:migrate` | Create audit storage for configured entities |
| `audit:migrate --entity=App\Models\Order` | Create storage for one entity |
| `audit:config-check` | Validate config, resolver, transformers, and hash settings |
| `audit:doctor` | Check entity config and audit table existence |
| `audit:stats App\Models\Order` | Show audit summary statistics |
| `audit:timeline App\Models\Order 123` | Show timeline for one entity id |
| `audit:diff App\Models\Order 456` | Show field changes for one audit row |
| `audit:verify App\Models\Order` | Verify hash-chain integrity |
| `audit:partition audit_orders_logs --monthly` | Show safe partitioning guidance |
| `audit:upgrade v1-to-v2 --dry-run` | Report missing v2 columns |
| `audit:cleanup --dry-run` | Preview retention cleanup |

## Partitioning Guidance

`audit:partition` is guidance-only. It does not run DDL because partitioning is database- and workload-specific.

For large tables:

- PostgreSQL: consider range partitioning by `created_at`.
- MySQL: consider RANGE partitioning on a generated date key or `TO_DAYS(created_at)`.
- Keep indexes aligned with common queries: `entity_id`, `tenant_id`, `created_at`, `action`.

## Events

Available events:

```php
use iamfarhad\LaravelAuditLog\Events\AuditCreating;
use iamfarhad\LaravelAuditLog\Events\AuditCreated;
use iamfarhad\LaravelAuditLog\Events\AuditVerificationFailed;
```

Example listener:

```php
Event::listen(AuditVerificationFailed::class, function (AuditVerificationFailed $event): void {
    report([
        'entity' => $event->entityClass,
        'failures' => $event->failures,
    ]);
});
```

## Presets

The config includes a `preset` value for future package-level defaults:

```env
AUDIT_PRESET=basic
```

Recommended semantic presets for downstream apps:

| Preset | Suggested behavior |
|---|---|
| `basic` | default auditing with safe exclusions |
| `compliance` | append-only, hash-chain-ready, redaction-heavy |
| `high_performance` | queueing, batch where safe, larger chunk sizes |
| `saas` | tenant resolver, tenant indexes, stricter authorization |

## Production Checklist

Before enabling v2 features in production:

- [ ] Publish config.
- [ ] Generate audit migrations.
- [ ] Add nullable v2 columns to existing audit tables.
- [ ] Run `audit:upgrade v1-to-v2 --dry-run`.
- [ ] Run `audit:config-check`.
- [ ] Run `audit:doctor`.
- [ ] Decide whether runtime auto-migration should be disabled.
- [ ] Configure queue workers if queueing is enabled.
- [ ] Configure gates if audit authorization is enabled.
- [ ] Enable hash-chain mode only after hash columns exist.
- [ ] Verify restore workflows in staging.
- [ ] Add monitoring for `AuditVerificationFailed`.

## Troubleshooting

### `audit:verify` says hash columns are missing

Add nullable `audit_hash` and `previous_hash` columns before verifying or enabling hash-chain mode.

### Batch inserts are slower than expected

Native batch mode is skipped when queueing or hash-chain mode is enabled. This is intentional.

### Tenant columns remain null

Check that:

- `AUDIT_TENANT_ENABLED=true`;
- resolver implements `TenantResolverInterface`;
- audit tables have `tenant_type` and `tenant_id` columns;
- the resolver returns a non-null tenant in the current execution context.

### Restore applies too many fields

Set:

```env
AUDIT_RESTORE_VALIDATE_FILLABLE=true
```

Then review the model's `$fillable` array.

### Audit rows can still be edited in the database

Append-only mode blocks Eloquent updates/deletes. It does not prevent direct database changes. Use database permissions and hash-chain verification for stronger protection.

## Upgrade Order

Recommended order from v1/advanced branch to v2:

1. Merge the advanced audit API work.
2. Deploy v2 code with new features disabled.
3. Run `audit:upgrade v1-to-v2 --dry-run`.
4. Add nullable v2 columns to existing audit tables.
5. Enable tenancy/change/snapshot features one at a time.
6. Enable append-only mode.
7. Enable hash-chain mode only after verifying hash columns exist.
8. Run `audit:doctor`, `audit:config-check`, and `audit:verify`.
9. Monitor queue workers and verification events.
