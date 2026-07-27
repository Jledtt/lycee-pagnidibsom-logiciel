@extends('layouts.app', [
    'title' => $article['title'].' - Documentation LPP',
    'active' => 'help',
    'pageTitle' => $article['title'],
    'pageSubtitle' => $category['title'].' · '.implode(' · ', $article['roles']),
])

@section('content')
    <nav class="doc-breadcrumb" aria-label="Fil d’Ariane">
        <a href="{{ route('help.index') }}">Documentation</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('help.index', ['category' => $article['category']]) }}">{{ $category['title'] }}</a>
        <span aria-hidden="true">/</span>
        <strong>{{ $article['title'] }}</strong>
    </nav>

    <div class="doc-layout">
        <article class="doc-article">
            <header class="doc-article-head">
                <span class="doc-kicker">{{ $category['title'] }}</span>
                <h2>{{ $article['title'] }}</h2>
                <p>{{ $article['summary'] }}</p>
                @if ($article['url'])
                    <a class="button" href="{{ $article['url'] }}">Ouvrir le module</a>
                @endif
            </header>

            <section class="doc-steps" aria-labelledby="doc-steps-title">
                <h3 id="doc-steps-title">Étapes à suivre</h3>
                <ol>
                    @foreach ($article['steps'] as $step)
                        <li>
                            <span class="doc-step-number">{{ $loop->iteration }}</span>
                            <p>{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if (! empty($article['tips']))
                <section class="doc-callout" aria-labelledby="doc-tips-title">
                    <h3 id="doc-tips-title">Bon à savoir</h3>
                    <ul>
                        @foreach ($article['tips'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="doc-article-actions">
                <a class="button button-secondary" href="{{ route('help.index', ['category' => $article['category']]) }}">
                    Retour à {{ $category['title'] }}
                </a>
                @if ($article['url'])
                    <a class="button" href="{{ $article['url'] }}">Commencer maintenant</a>
                @endif
            </div>
        </article>

        <aside class="doc-related" aria-labelledby="doc-related-title">
            <h3 id="doc-related-title">Guides du même thème</h3>
            @forelse ($relatedTopics as $relatedTopic)
                <a href="{{ route('help.show', $relatedTopic['slug']) }}">
                    <strong>{{ $relatedTopic['title'] }}</strong>
                    <span>{{ $relatedTopic['summary'] }}</span>
                </a>
            @empty
                <p>Aucun autre guide n’est disponible pour votre rôle dans ce thème.</p>
            @endforelse
        </aside>
    </div>
@endsection
