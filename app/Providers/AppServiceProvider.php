<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\SubmissionRepositoryInterface::class,
            \App\Repositories\EloquentSubmissionRepository::class
        );
        $this->app->bind(
            \App\Repositories\RewardRepositoryInterface::class,
            \App\Repositories\EloquentRewardRepository::class
        );
        $this->app->bind(
            \App\Repositories\AgentRepositoryInterface::class,
            \App\Repositories\EloquentAgentRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
