<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class GuidedTourService
{
    public function visibleFor(User $user): Collection
    {
        return collect(config('guided_tours.tours', []))
            ->filter(fn (array $tour): bool => $this->canSee($user, $tour['permissions'] ?? []))
            ->map(function (array $tour, string $key) use ($user): array {
                $steps = collect($tour['steps'] ?? [])
                    ->filter(fn (array $step): bool => $this->canSee($user, $step['permissions'] ?? []))
                    ->map(fn (array $step): array => collect($step)->except('permissions')->all())
                    ->values();

                $routeName = $tour['route'] ?? null;

                return [
                    'key' => $key,
                    'title' => $tour['title'],
                    'description' => $tour['description'],
                    'route' => $routeName,
                    'url' => $routeName && Route::has($routeName)
                        ? route($routeName, ['tour' => $key])
                        : null,
                    'autoPrompt' => (bool) ($tour['auto_prompt'] ?? false),
                    'steps' => $steps->all(),
                    'stepCount' => $steps->count(),
                ];
            })
            ->filter(fn (array $tour): bool => $tour['stepCount'] > 0)
            ->values();
    }

    private function canSee(User $user, array $permissions): bool
    {
        return $permissions === []
            || collect($permissions)->contains(fn (string $permission): bool => $user->can($permission));
    }
}
