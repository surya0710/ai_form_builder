<?php

namespace App\Providers;

use App\Models\Form;
use App\Policies\FormPolicy;
use App\Services\AI\Providers\AIProviderInterface;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function (): AIProviderInterface {
            $provider = config('ai.default', 'openai');

            return match ($provider) {
                'openai' => $this->app->make(OpenAIProvider::class),
                default => throw new InvalidArgumentException("Unsupported AI provider [{$provider}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Form::class, FormPolicy::class);

        RateLimiter::for('ai-generate', function (Request $request) {
            return Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('form-import', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('public-submit', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
