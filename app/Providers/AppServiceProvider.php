<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
    }

    /**
     * Eloquent strict mode: a relation that was not eager-loaded is an N+1
     * waiting for election night. Locally and in tests it throws, so the
     * query is fixed before it ships; in production it is logged and served,
     * because a slow page beats a broken one while the count is running.
     */
    private function configureModels(): void
    {
        Model::preventLazyLoading();
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Lazy loading violation', ['model' => $model::class, 'relation' => $relation]);
            });
        }
    }
}
