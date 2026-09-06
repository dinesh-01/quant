<?php

namespace App\Providers;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Listeners\NotifyAdministratorsOfRegistration;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();

        Event::listen(Registered::class, NotifyAdministratorsOfRegistration::class);
    }

    /**
     * Personal-access-token calls are limited per user, after Sanctum has
     * identified them. Guests never reach this limiter — they 401 first.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->user()?->getKey());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register a gate for every ability, scoped by an optional test project or
     * test plan, for example `$user->can('view_test_cases', $project)`.
     *
     * These gates are keyed by ability name rather than by model, so a policy
     * registered for TestProject or TestPlan would take precedence over them
     * for scoped checks.
     */
    protected function configureAuthorization(): void
    {
        $resolver = $this->app->make(RoleResolver::class);

        foreach (Ability::cases() as $ability) {
            Gate::define(
                $ability->value,
                fn (User $user, TestProject|TestPlan|null $scope = null): bool => $resolver->allows($user, $ability, $scope),
            );
        }
    }
}
