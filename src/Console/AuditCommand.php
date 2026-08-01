<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;
use UbayedTanvir\LaravelTenancy\Database\TenantColumn;
use UbayedTanvir\LaravelTenancy\TenancyManager;

#[Signature(signature: 'tenancy:audit
    {--json : Output machine-readable JSON}
    {--fail-on=error : Fail the command on this severity or higher (error|warn)}'
)]
#[Description(description: 'Audit the schema and models for tenant-isolation problems')]
final class AuditCommand extends Command
{
    /** @var list<array{level: string, message: string, fix: ?string}> */
    private array $findings = [];

    public function handle(TenancyManager $tenancyManager): int
    {
        $model = TenantColumn::model();
        $globalForeignKey = TenantColumn::foreignKey($model);
        $scopedTables = $this->scopedTables($tenancyManager);

        $this->checkStrictMode();
        $this->checkTablesWithoutTrait($scopedTables, $globalForeignKey, $model->getTable());

        foreach ($scopedTables as $table => $meta) {
            $this->checkScopedTable($table, $meta['fk'], TenantColumn::keyType($model));
        }

        return $this->option('json')
            ? $this->renderJson($model, $globalForeignKey)
            : $this->renderConsole($model, $globalForeignKey, \count($scopedTables));
    }

    private function checkStrictMode(): void
    {
        if (config()->boolean('tenancy.strict', default: true)) {
            return;
        }

        $this->add(
            app()->isProduction() ? 'error' : 'warn',
            'Strict mode is disabled: a query with no tenant bound returns unscoped instead of throwing.',
            'Set `tenancy.strict = true` (or TENANCY_STRICT=true) once your context is always established.',
        );
    }

    /**
     * @param  array<string, array{model: class-string<Model>, fk: string}>  $scopedTables
     */
    private function checkTablesWithoutTrait(array $scopedTables, string $globalForeignKey, string $tenantTable): void
    {
        $ignore = $this->ignoredTables();

        foreach ($this->allTables() as $table) {
            if ($table === $tenantTable) {
                continue;
            }

            if (isset($scopedTables[$table])) {
                continue;
            }

            if (\in_array($table, $ignore, strict: true)) {
                continue;
            }

            if ($this->tableHasColumn($table, $globalForeignKey)) {
                $this->add(
                    'error',
                    \sprintf("Table [%s] has a [%s] column but no model uses BelongsToTenant. Every query against it returns all tenants' rows.", $table, $globalForeignKey),
                    \sprintf('Add `use UbayedTanvir\LaravelTenancy\Concerns\BelongsToTenant;` to its model, or add [%s] to tenancy.audit.ignore_tables if it is a pivot.', $table),
                );
            }
        }
    }

    private function checkScopedTable(string $table, string $foreignKey, string $keyType): void
    {
        if (! $this->tableHasColumn($table, $foreignKey)) {
            $this->add(
                'error',
                \sprintf('Table [%s] is scoped but has no [%s] column.', $table, $foreignKey),
                'Add `$table->tenant();` in a migration.'
            );

            return;
        }

        $this->checkForeignKey($table, $foreignKey);
        $this->checkLeadingIndex($table, $foreignKey);
        $this->checkCompositeUniques($table, $foreignKey);
        $this->checkColumnType($table, $foreignKey, $keyType);
    }

    private function checkForeignKey(string $table, string $foreignKey): void
    {
        foreach (Schema::getForeignKeys($table) as $constraint) {
            if (\in_array($foreignKey, $this->columnsOf($constraint), strict: true)) {
                return;
            }
        }

        $this->add(
            'warn',
            \sprintf('Table [%s] has no foreign key on [%s]; deleting a tenant can orphan rows no scoped query can reach.', $table, $foreignKey),
            'Use `$table->tenant();`, which adds the constraint.'
        );
    }

    private function checkLeadingIndex(string $table, string $foreignKey): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            $columns = $this->columnsOf($index);

            if (($columns[0] ?? null) === $foreignKey) {
                return;
            }
        }

        $this->add(
            'warn',
            \sprintf('Table [%s] has no index leading with [%s]; every scoped query against it is a sequential scan.', $table, $foreignKey),
            \sprintf("Add `\$table->index(['%s', 'created_at']);`.", $foreignKey)
        );
    }

    private function checkCompositeUniques(string $table, string $foreignKey): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (! \is_array($index)) {
                continue;
            }

            $isUnique = (bool) ($index['unique'] ?? false);
            $isPrimary = (bool) ($index['primary'] ?? false);
            $columns = $this->columnsOf($index);
            if (! $isUnique) {
                continue;
            }

            if ($isPrimary) {
                continue;
            }

            if (\in_array($foreignKey, $columns, strict: true)) {
                continue;
            }

            $list = implode(', ', $columns);
            $this->add(
                'error',
                \sprintf('Unique index on [%s] (%s) is not composite with [%s]; those values are globally unique instead of per-tenant — a leak and a bug.', $table, $list, $foreignKey),
                \sprintf("Make it unique on ['%s', %s].", $foreignKey, $list)
            );
        }
    }

    private function checkColumnType(string $table, string $foreignKey, string $keyType): void
    {
        $expected = \in_array($keyType, [TenantColumn::ID, TenantColumn::BIGINT], strict: true) ? 'integer' : 'string';

        foreach (Schema::getColumns($table) as $column) {
            if (! \is_array($column)) {
                continue;
            }

            if (($column['name'] ?? null) !== $foreignKey) {
                continue;
            }

            $typeName = $column['type_name'] ?? $column['type'] ?? '';
            $typeName = \is_string($typeName) ? $typeName : '';
            $actual = str_contains(strtolower($typeName), 'int') ? 'integer' : 'string';

            if ($actual !== $expected) {
                $this->add(
                    'error',
                    \sprintf('Column [%s.%s] is %s-typed but the tenant key is %s-typed (drift after a tenant-model change).', $table, $foreignKey, $actual, $expected),
                    'Re-create the column with `$table->tenant();`.'
                );
            }

            return;
        }
    }

    /**
     * @return list<string>
     */
    private function columnsOf(mixed $item): array
    {
        if (! \is_array($item)) {
            return [];
        }

        $columns = $item['columns'] ?? null;

        return \is_array($columns)
            ? array_values(array_filter($columns, \is_string(...)))
            : [];
    }

    /**
     * @return array<string, array{model: class-string<Model>, fk: string}>
     */
    private function scopedTables(TenancyManager $tenancyManager): array
    {
        $map = [];

        foreach ($this->modelClasses() as $class) {
            $instance = new $class;
            $map[$instance->getTable()] = ['model' => $class, 'fk' => $tenancyManager->foreignKeyFor($instance)];
        }

        return $map;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function modelClasses(): array
    {
        $found = [];

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Model::class) && $this->usesTrait($class)) {
                $found[$class] = $class;
            }
        }

        $directory = app_path('Models');

        if (is_dir($directory)) {
            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);

                if (class_exists($class) && is_subclass_of($class, Model::class) && $this->usesTrait($class)) {
                    $found[$class] = $class;
                }
            }
        }

        return array_values($found);
    }

    /**
     * @param  class-string  $class
     */
    private function usesTrait(string $class): bool
    {
        return \in_array(BelongsToTenant::class, class_uses_recursive($class), strict: true);
    }

    /**
     * @return list<string>
     */
    private function allTables(): array
    {
        $names = [];

        foreach (Schema::getTables() as $table) {
            if (\is_array($table) && \is_string($table['name'] ?? null)) {
                $names[] = $table['name'];
            }
        }

        return $names;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * @return list<string>
     */
    private function ignoredTables(): array
    {
        $ignore = config('tenancy.audit.ignore_tables', []);

        return \is_array($ignore) ? array_values(array_filter($ignore, \is_string(...))) : [];
    }

    private function add(string $level, string $message, ?string $fix = null): void
    {
        $this->findings[] = [
            'level' => $level,
            'message' => $message,
            'fix' => $fix,
        ];
    }

    private function renderConsole(Model $model, string $foreignKey, int $scopedCount): int
    {
        $this->newLine();
        $this->components->twoColumnDetail('Tenant', $model::class.' ('.TenantColumn::describe($model).')');
        $this->components->twoColumnDetail('Foreign key', $foreignKey);
        $this->components->twoColumnDetail('Strict mode', (config()->boolean('tenancy.strict', default: true)) ? 'enabled' : 'disabled');
        $this->components->twoColumnDetail('Scoped models', (string) $scopedCount);
        $this->newLine();

        $errors = $this->countLevel('error');
        $warnings = $this->countLevel('warn');

        if ($this->findings === []) {
            $this->components->info('No isolation problems found.');
        }

        foreach ($this->findings as $finding) {
            $tag = $finding['level'] === 'error' ? 'ERROR' : 'WARN';
            $this->components->twoColumnDetail('<fg='.($finding['level'] === 'error' ? 'red' : 'yellow').\sprintf('>%s</>', $tag), '');
            $this->line('  '.$finding['message']);

            if ($finding['fix'] !== null) {
                $this->line('  <fg=gray>→ '.$finding['fix'].'</>');
            }

            $this->newLine();
        }

        return $this->exitCode($errors, $warnings);
    }

    private function renderJson(Model $model, string $foreignKey): int
    {
        $errors = $this->countLevel('error');
        $warnings = $this->countLevel('warn');

        $this->line((string) json_encode([
            'tenant' => $model::class,
            'foreign_key' => $foreignKey,
            'strict' => config()->boolean('tenancy.strict', default: true),
            'errors' => $errors,
            'warnings' => $warnings,
            'findings' => $this->findings,
        ], JSON_PRETTY_PRINT));

        return $this->exitCode($errors, $warnings);
    }

    private function countLevel(string $level): int
    {
        return \count(array_filter($this->findings, fn (array $f): bool => $f['level'] === $level));
    }

    private function exitCode(int $errors, int $warnings): int
    {
        $failOn = $this->option('fail-on');
        $failOnWarn = \is_string($failOn) && $failOn === 'warn';

        if ($errors > 0 || ($failOnWarn && $warnings > 0)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
