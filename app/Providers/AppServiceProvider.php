<?php

namespace App\Providers;

use App\Models\Call;
use App\Models\CriterionResult;
use App\Models\Template;
use App\Policies\CallPolicy;
use App\Policies\OverridePolicy;
use App\Policies\TemplatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Template::class, TemplatePolicy::class);
        Gate::policy(Call::class, CallPolicy::class);
        Gate::policy(CriterionResult::class, OverridePolicy::class);
    }
}
