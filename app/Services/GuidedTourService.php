<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * @phpstan-type TourStep non-empty-array<array-key, mixed>
 * @phpstan-type VisibleTour array{
 *     key: string,
 *     title: string,
 *     description: string,
 *     route: string|null,
 *     url: string|null,
 *     autoPrompt: bool,
 *     steps: non-empty-list<TourStep>,
 *     stepCount: int
 * }
 */
class GuidedTourService
{
    /** @return list<VisibleTour> */
    public function visibleFor(User $user): array
    {
        $visibleTours = [];

        foreach ($this->tourDefinitions() as $key => $tour) {
            if (! $this->canSee($user, $this->permissions($tour['permissions'] ?? []))) {
                continue;
            }

            $steps = [];

            foreach ($this->steps($tour['steps'] ?? []) as $step) {
                if ($this->canSee($user, $this->permissions($step['permissions'] ?? []))) {
                    $visibleStep = collect($step)->except('permissions')->all();

                    if ($visibleStep !== []) {
                        $steps[] = $visibleStep;
                    }
                }
            }

            if ($steps === []) {
                continue;
            }

            $routeName = is_string($tour['route'] ?? null) ? $tour['route'] : null;
            $title = is_string($tour['title'] ?? null) ? $tour['title'] : $key;
            $description = is_string($tour['description'] ?? null) ? $tour['description'] : '';

            $visibleTours[] = [
                'key' => $key,
                'title' => $title,
                'description' => $description,
                'route' => $routeName,
                'url' => $routeName && Route::has($routeName)
                    ? route($routeName, ['tour' => $key])
                    : null,
                'autoPrompt' => (bool) ($tour['auto_prompt'] ?? false),
                'steps' => $steps,
                'stepCount' => count($steps),
            ];
        }

        return $visibleTours;
    }

    /** @return array<string, array<string, mixed>> */
    private function tourDefinitions(): array
    {
        $configuredTours = config('guided_tours.tours', []);

        if (! is_array($configuredTours)) {
            return [];
        }

        $definitions = [];

        foreach ($configuredTours as $key => $tour) {
            if (is_string($key) && is_array($tour)) {
                $definitions[$key] = $tour;
            }
        }

        return $definitions;
    }

    /** @return list<TourStep> */
    private function steps(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /** @return list<string> */
    private function permissions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /** @param list<string> $permissions */
    private function canSee(User $user, array $permissions): bool
    {
        return $permissions === []
            || collect($permissions)->contains(fn (string $permission): bool => $user->can($permission));
    }
}
