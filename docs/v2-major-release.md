# v2 Major Release Infrastructure

This guide covers the v2 infrastructure added on top of the advanced audit APIs.

Excluded from this branch by design:

- Filament plugin
- Nova / Backpack integrations
- New external storage drivers such as ClickHouse, OpenSearch, S3, and webhook drivers

## Migration tooling

Generate a production migration for one audited model:

```bash
php artisan audit:make-migration App\\Models\\Order
```

Create audit storage for configured entities:

```bash
php artisan audit:migrate
php artisan audit:migrate --entity=App\\Models\\Order
```

Production apps should prefer generated migrations over runtime auto-migration.

## Real batch insert support

Enable native grouped insert mode:

```env
AUDIT_BATCH_ENABLED=true
AUDIT_BATCH_SIZE=500
```

Then use:

```php
AuditLogger::batch($logs);
```

When queueing is disabled, the driver groups audit rows by audit table and inserts chunks through the query builder.

## Multi-tenancy

Enable tenant context:

```env
AUDIT_TENANT_ENABLED=true
```

Configure a resolver:

```php
'tenant' => [
    'enabled' => true,
    'resolver' => App\\Audit\\CurrentTenantResolver::class,
    'columns' => [
        'type' => 'tenant_type',
        'id' => 'tenant_id',
    ],
],
```

Your resolver must implement `TenantResolverInterface`.

## Append-only mode

```env
AUDIT_APPEND_ONLY=true
```

When enabled, the `EloquentAuditLog` model prevents Eloquent updates and deletes. Pair this with hash-chain mode for stronger audit integrity.

## Relationship auditing helper

Manual relationship audit helpers are available through `RelationshipAuditor`:

```php
app(RelationshipAuditor::class)->attached($user, 'roles', [$roleId]);
app(RelationshipAuditor::class)->detached($user, 'roles', [$roleId]);
app(RelationshipAuditor::class)->synced($user, 'roles', $roleIds);
```

This keeps relation logging explicit and avoids monkey-patching Laravel relations.

## Authorization

```php
'authorization' => [
    'enabled' => true,
    'view_gate' => 'viewAuditLogs',
    'restore_gate' => 'restoreFromAudit',
],
```

Use `AuditAuthorization` in controllers/admin panels before exposing timeline or restore actions.

## Commands

```bash
php artisan audit:config-check
php artisan audit:doctor
php artisan audit:stats App\\Models\\Order
php artisan audit:timeline App\\Models\\Order 123
php artisan audit:diff App\\Models\\Order 456
php artisan audit:verify App\\Models\\Order
php artisan audit:partition audit_orders_logs --monthly
php artisan audit:upgrade v1-to-v2 --dry-run
```

## Computed changes

Enable stored change sets:

```env
AUDIT_STORE_CHANGES=true
```

The package stores field-level changes in the configured `changes` column when the column exists.

## Snapshots

```env
AUDIT_SNAPSHOTS_ENABLED=true
AUDIT_SNAPSHOT_EVERY=20
```

`AuditSnapshotService` can create periodic snapshot rows with action `snapshot`.

## API resources

`AuditLogResource` provides a consistent JSON shape for exposing audit rows through internal APIs.

## Upgrade notes

Run:

```bash
php artisan audit:upgrade v1-to-v2 --dry-run
```

The command reports missing v2 columns such as:

- `audit_hash`
- `previous_hash`
- `tenant_type`
- `tenant_id`
- `changes`

Add columns through normal Laravel migrations before enabling the related features in production.
