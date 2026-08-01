<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use UbayedTanvir\LaravelTenancy\Database\TenantColumn;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature(signature: 'tenancy:install
    {--model= : The tenant model class}
    {--force : Overwrite the published config}'
)]
#[Description(description: 'Install and configure strict tenancy')]
final class InstallCommand extends Command
{
    public function handle(): int
    {
        $model = $this->option('model');
        $model = \is_string($model) && $model !== '' ? $model : $this->chooseTenantModel();

        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            $this->components->error(\sprintf('[%s] is not an Eloquent model.', $model));

            return self::FAILURE;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'tenancy-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->reportConventions($model);
        $this->printNextSteps($model);

        return self::SUCCESS;
    }

    private function chooseTenantModel(): string
    {
        $candidates = array_values(array_filter(
            array_map(
                fn (string $name): string => 'App\\Models\\'.$name,
                ['Tenant', 'Team', 'Organization', 'Workspace', 'Account', 'User'],
            ),
            class_exists(...),
        ));

        if ($candidates === []) {
            return text('Fully-qualified tenant model class', default: 'App\\Models\\Tenant');
        }

        $candidates[] = 'Other…';

        $choice = select('Which model is the tenant?', $candidates);

        return \is_string($choice) && $choice !== 'Other…'
            ? $choice
            : text('Fully-qualified tenant model class', default: 'App\\Models\\Tenant');
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function reportConventions(string $model): void
    {
        $instance = new $model;

        $this->newLine();
        $this->components->twoColumnDetail('Tenant model', $model);
        $this->components->twoColumnDetail('Tenant table', $instance->getTable());
        $this->components->twoColumnDetail('Foreign key', $instance->getForeignKey());
        $this->components->twoColumnDetail('Route key', $instance->getRouteKeyName());
        $this->components->twoColumnDetail('Column type', TenantColumn::describe($instance));
        $this->newLine();
    }

    private function printNextSteps(string $model): void
    {
        $this->components->bulletList([
            \sprintf('Set TENANCY_MODEL=%s (or tenancy.tenant.model in config).', $model),
            'implements IsTenant   on your tenant model.',
            'use BelongsToTenant   on every model that belongs to a tenant.',
            '$table->tenant();     in those models\' migrations.',
            'Verify any time with: php artisan tenancy:audit',
        ]);
    }
}
