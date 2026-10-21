<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\{User, Assessment};
use App\Policies\AssessmentPolicy;
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('pagination');
        Gate::policy(\App\Models\Classroom::class, \App\Policies\ClassroomPolicy::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::define('manage-users', fn (User $u) => $u->is_active && $u->role === 'admin');
        Gate::define('teach', fn (User $u) => $u->is_active && $u->role === 'teacher');
        Gate::define('learn', fn (User $u) => $u->is_active && $u->role === 'student');
    }
}
