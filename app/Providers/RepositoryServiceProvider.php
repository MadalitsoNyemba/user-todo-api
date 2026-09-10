<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Every repository binding lives here, so swapping an implementation is a
     * one line change and a test can rebind without touching the container
     * anywhere else.
     */
    public array $bindings = [
        UserRepositoryInterface::class => EloquentUserRepository::class,
    ];
}
