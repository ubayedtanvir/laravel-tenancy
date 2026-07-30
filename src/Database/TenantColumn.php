<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Database;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use UbayedTanvir\LaravelTenancy\Exceptions\TenancyException;

/**
 * @internal
 */
final class TenantColumn
{
    public const string UUID = 'uuid';

    public const string ULID = 'ulid';

    public const string ID = 'id';

    public const string BIGINT = 'bigInteger';

    public const string STRING = 'string';

    /**
     * Add a tenant foreign key column to the given blueprint.
     */
    public static function add(Blueprint $blueprint, ?string $column = null): ColumnDefinition
    {
        $model = self::model();
        $column ??= self::foreignKey($model);

        return match (self::keyType($model)) {
            self::UUID => $blueprint->uuid($column),
            self::ULID => $blueprint->ulid($column),
            self::ID, self::BIGINT => $blueprint->unsignedBigInteger($column),
            self::STRING => $blueprint->string($column, self::keyLength()),
            default => throw self::cannotInfer($model),
        };
    }

    /**
     * Determine the tenant model's key type.
     */
    public static function keyType(?Model $model = null): string
    {
        $configured = config('tenancy.tenant.key_type');

        if (\is_string($configured) && $configured !== '') {
            return $configured;
        }

        $model ??= self::model();
        $traits = class_uses_recursive($model);

        if (\in_array(HasUuids::class, $traits, strict: true)) {
            return self::UUID;
        }

        if (\in_array(HasUlids::class, $traits, strict: true)) {
            return self::ULID;
        }

        return match (true) {
            $model->getKeyType() === 'int' && $model->getIncrementing() => self::ID,
            $model->getKeyType() === 'int' => self::BIGINT,
            $model->getKeyType() === 'string' => self::STRING,
            default => throw self::cannotInfer($model),
        };
    }

    /**
     * Get a human-readable description of the tenant key type.
     */
    public static function describe(?Model $model = null): string
    {
        $type = self::keyType($model ?? self::model());

        if ($type === self::STRING) {
            return \sprintf('string(%d)', self::keyLength());
        }

        if ($type === self::ID) {
            return 'unsignedBigInteger (foreignId)';
        }

        return $type;
    }

    public static function foreignKey(?Model $model = null): string
    {
        $configured = config('tenancy.tenant.foreign_key');

        if (\is_string($configured) && $configured !== '') {
            return $configured;
        }

        return ($model ?? self::model())->getForeignKey();
    }

    public static function table(?Model $model = null): string
    {
        return ($model ?? self::model())->getTable();
    }

    public static function parentKey(?Model $model = null): string
    {
        return ($model ?? self::model())->getKeyName();
    }

    public static function model(): Model
    {
        $class = config('tenancy.tenant.model');

        throw_if(
            ! \is_string($class) || ! is_subclass_of($class, Model::class),
            TenancyException::class,
            'No tenant model configured. Run `php artisan tenancy:install`.'
        );

        return new $class;
    }

    private static function keyLength(): int
    {
        $length = config('tenancy.tenant.key_length', 40);

        return \is_int($length) ? $length : 40;
    }

    private static function cannotInfer(Model $model): TenancyException
    {
        return new TenancyException(\sprintf(
            'Cannot infer the column type for tenant [%s] (key type "%s"). '.
            'Set tenancy.tenant.key_type to one of: uuid, ulid, id, bigInteger, string.',
            $model::class,
            $model->getKeyType(),
        ));
    }
}
