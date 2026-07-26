<?php

declare(strict_types=1);

namespace UbayedTanvir\LaravelTenancy\Contracts;

/**
 * Implemented by whatever model is the tenant — Team, User, Store, Client.
 *
 * Deliberately empty. This is a marker interface, and that is the point: the
 * cost of adopting this package on an existing model is `implements IsTenant`
 * and nothing else.
 *
 * The @method annotations below exist only so static analysis knows the
 * Eloquent methods the package calls on a tenant are present. They declare
 * nothing new; every implementer already inherits these from Model.
 *
 * @method int|string getKey()
 * @method string getRouteKey()
 * @method string getRouteKeyName()
 */
interface IsTenant {}
