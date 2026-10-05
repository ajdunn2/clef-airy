<?php

namespace App\Providers;

use App\Models\SavedCall;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

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
        View::composer('layouts.app', function (ViewContract $view): void {
            $view->with('savedCalls', SavedCall::query()->orderBy('id')->get(['id', 'name']));
        });
    }
}
