<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

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
        Model::shouldBeStrict(! $this->app->environment('production'));

        // Refuse db:wipe, migrate:fresh, refresh, reset, and rollback in production, even with --force.
        DB::prohibitDestructiveCommands($this->app->environment('production'));

        // The activitylog model is outside App\Models, so policy auto-discovery misses it.
        Gate::policy(Activity::class, ActivityPolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Rate limits for the unauthenticated auth endpoints.
     *
     * - `login`: 5 attempts per minute for each email and IP pair, so one
     *   attacker cannot lock out other users who share the same IP.
     * - `google-login`: 10 attempts per minute per IP. The identity is unknown
     *   until Google verifies the token, so the IP is the only stable key; the
     *   limit is looser because mobile carriers put many users behind one IP.
     *   It also caps how often one client can make us call Google.
     * - `register`: 10 attempts per minute per IP, for the same reason: there
     *   is no identity yet and the email changes freely between requests.
     *   Failed attempts count too, which stops probing for registered emails.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));

        RateLimiter::for('google-login', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));

        // Every signed-in API route (RATE-2), counted per user: many mobile
        // users share one IP. Sign-in routes keep their own limiters above.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
