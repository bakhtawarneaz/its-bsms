<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Enums\UserType;
use App\Support\Navigation;
use Illuminate\Support\Facades\View;

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
                // Share the signed-in user and their navigation with the app layout
        View::composer('layouts.app', function ($view) {
            $user = session('auth_user');

            $view->with([
                'authUser'   => $user,
                'userType'   => UserType::fromDatabase($user['user_type']),
                'navigation' => Navigation::for(UserType::fromDatabase($user['user_type'])),
            ]);
        });
    }
}
