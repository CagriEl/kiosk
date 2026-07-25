<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Alt dizin / IP ile erişimde APP_URL uyuşmazlığı CSRF 419 üretir.
        if (! $this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $root = rtrim(request()->root(), '/');
            if ($root !== '') {
                URL::forceRootUrl($root);
            }
        }
    }
}
