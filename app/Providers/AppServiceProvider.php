<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\NaiveBayes;

class AppServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton(NaiveBayes::class, function () {
            return new NaiveBayes();
        });
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
