<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Connexion : 5 tentatives / minute par email + IP (anti force brute).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')) . '|' . $request->ip());
        });

        // Application client : envois de SMS limités (coût + anti-harcèlement), PIN limité par numéro.
        RateLimiter::for('client-otp', function (Request $request) {
            $phone = preg_replace('/\D/', '', (string) $request->input('phone'));
            return [
                Limit::perMinutes(10, 3)->by('otp-phone:' . $phone),
                Limit::perHour(10)->by('otp-ip:' . $request->ip()),
            ];
        });
        RateLimiter::for('client-auth', function (Request $request) {
            $phone = preg_replace('/\D/', '', (string) $request->input('phone'));
            return [
                Limit::perMinute(10)->by('client-auth:' . $phone . '|' . $request->ip()),
                Limit::perMinute(30)->by('client-auth-ip:' . $request->ip()),
            ];
        });

        // Vérification / paiement d'un code de retrait : 10 requêtes / minute par agent.
        // (Les codes ERRONÉS sont en plus limités à TRANSFER_MAX_CODE_ATTEMPTS par 15 min.)
        RateLimiter::for('withdrawal', function (Request $request) {
            return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
