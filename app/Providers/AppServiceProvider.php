<?php

namespace App\Providers;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        View::composer('*', function ($view) {
            $settings = null;

            try {
                if (Schema::hasTable('school_settings')) {
                    $settings = SchoolSetting::query()->first();
                }
            } catch (\Throwable) {
                $settings = null;
            }

            $view->with('schoolSettings', $settings);
        });
    }
}
