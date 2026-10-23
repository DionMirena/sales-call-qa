<?php

namespace App\Providers;

use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Policies\CallPolicy;
use App\Policies\OverridePolicy;
use App\Policies\TemplatePolicy;
use App\Services\DeepgramTranscriptionService;
use App\Services\FakeScoringService;
use App\Services\FakeTranscriptionService;
use App\Services\ScoringService;
use App\Services\TranscriptionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('qa.fake_ai')) {
            $this->app->bind(TranscriptionService::class, FakeTranscriptionService::class);
            $this->app->bind(ScoringService::class, FakeScoringService::class);
        } else {
            $this->app->bind(TranscriptionService::class, DeepgramTranscriptionService::class);
            // ClaudeScoringService bound in Phase 4; for now fake is used when real not ready
            $this->app->bind(ScoringService::class, FakeScoringService::class);
        }
    }

    public function boot(): void
    {
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(Call::class, CallPolicy::class);
        Gate::policy(CriterionResult::class, OverridePolicy::class);
    }
}
