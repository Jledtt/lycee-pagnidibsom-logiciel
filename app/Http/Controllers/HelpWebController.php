<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GuidedTourService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpWebController extends Controller
{
    public function __construct(
        private readonly GuidedTourService $guidedTourService,
    ) {}

    public function index(Request $request): View
    {
        $topics = $this->visibleTopics($request->user());
        $categories = collect(config('user_documentation.categories', []));
        $search = trim($request->string('search')->toString());
        $category = $request->string('category')->toString();

        if ($category !== '' && ! $categories->has($category)) {
            $category = '';
        }

        $filteredTopics = $topics
            ->when($category !== '', fn (Collection $items) => $items->where('category', $category))
            ->when($search !== '', fn (Collection $items) => $items->filter(
                fn (array $topic) => str_contains($this->searchableText($topic), $this->normalize($search)),
            ))
            ->values();

        return view('help.index', [
            'categories' => $categories,
            'topics' => $topics,
            'filteredTopics' => $filteredTopics,
            'search' => $search,
            'selectedCategory' => $category,
            'guidedTours' => $this->guidedTourService->visibleFor($request->user()),
        ]);
    }

    public function show(Request $request, string $topic): View
    {
        $topics = $this->visibleTopics($request->user());
        $article = $topics->firstWhere('slug', $topic);

        abort_unless($article, 404);

        $categories = collect(config('user_documentation.categories', []));

        return view('help.show', [
            'article' => $article,
            'category' => $categories->get($article['category']),
            'relatedTopics' => $topics
                ->where('category', $article['category'])
                ->where('slug', '!=', $article['slug'])
                ->take(4)
                ->values(),
        ]);
    }

    private function visibleTopics(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return collect(config('user_documentation.topics', []))
            ->map(fn (array $topic, string $slug) => $topic + ['slug' => $slug])
            ->filter(function (array $topic) use ($user) {
                $permissions = $topic['permissions'] ?? [];

                return $permissions === []
                    || collect($permissions)->contains(fn (string $permission) => $user->can($permission));
            })
            ->map(function (array $topic) {
                $routeName = $topic['route'] ?? null;
                $topic['url'] = $routeName && Route::has($routeName) ? route($routeName) : null;

                return $topic;
            })
            ->values();
    }

    private function searchableText(array $topic): string
    {
        $parts = [
            $topic['title'] ?? '',
            $topic['summary'] ?? '',
            implode(' ', $topic['roles'] ?? []),
            implode(' ', $topic['keywords'] ?? []),
            implode(' ', $topic['steps'] ?? []),
            implode(' ', $topic['tips'] ?? []),
        ];

        return $this->normalize(implode(' ', $parts));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
