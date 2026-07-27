@extends('layouts.app', [
    'title' => 'Documentation - Lycée Privé Pagnidibsom',
    'active' => 'help',
    'pageTitle' => 'Documentation utilisateur',
    'pageSubtitle' => 'Des guides simples, adaptés à votre rôle et aux écrans disponibles',
])

@section('content')
    <section class="doc-intro">
        <div>
            <span class="doc-kicker">Centre d’aide LPP</span>
            <h2>Que souhaitez-vous faire ?</h2>
            <p>Recherchez une opération ou parcourez les guides accessibles avec votre compte.</p>
        </div>
        <div class="doc-count">
            <strong>{{ $topics->count() }}</strong>
            <span>{{ Str::plural('guide', $topics->count()) }} disponible{{ $topics->count() > 1 ? 's' : '' }}</span>
        </div>
    </section>

    <form class="doc-toolbar" method="GET" action="{{ route('help.index') }}">
        <label class="doc-search" for="documentation-search">
            <span>Rechercher dans la documentation</span>
            <input
                id="documentation-search"
                name="search"
                type="search"
                value="{{ $search }}"
                placeholder="Ex. enregistrer un paiement, importer des élèves..."
            >
        </label>
        @if ($selectedCategory !== '')
            <input type="hidden" name="category" value="{{ $selectedCategory }}">
        @endif
        <button class="button" type="submit">Rechercher</button>
        @if ($search !== '' || $selectedCategory !== '')
            <a class="button button-secondary" href="{{ route('help.index') }}">Effacer</a>
        @endif
    </form>

    <nav class="doc-tabs" aria-label="Catégories de documentation">
        <a class="{{ $selectedCategory === '' ? 'active' : '' }}" href="{{ route('help.index', array_filter(['search' => $search])) }}">
            Tous
            <span>{{ $topics->count() }}</span>
        </a>
        @foreach ($categories as $categoryKey => $category)
            @php
                $categoryCount = $topics->where('category', $categoryKey)->count();
            @endphp
            @if ($categoryCount > 0)
                <a
                    class="{{ $selectedCategory === $categoryKey ? 'active' : '' }}"
                    href="{{ route('help.index', array_filter(['category' => $categoryKey, 'search' => $search])) }}"
                >
                    {{ $category['title'] }}
                    <span>{{ $categoryCount }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    @if ($filteredTopics->isEmpty())
        <div class="empty doc-empty">
            <strong>Aucun guide trouvé.</strong>
            <span>Essayez un autre mot comme « paiement », « élève », « bulletin » ou « sauvegarde ».</span>
        </div>
    @else
        @foreach ($categories as $categoryKey => $category)
            @php
                $categoryTopics = $filteredTopics->where('category', $categoryKey);
            @endphp
            @if ($categoryTopics->isNotEmpty())
                <section class="doc-category" aria-labelledby="doc-category-{{ $categoryKey }}">
                    <div class="doc-category-head">
                        <div>
                            <h2 id="doc-category-{{ $categoryKey }}">{{ $category['title'] }}</h2>
                            <p>{{ $category['description'] }}</p>
                        </div>
                        <span>{{ $categoryTopics->count() }} {{ Str::plural('guide', $categoryTopics->count()) }}</span>
                    </div>

                    <div class="doc-topic-grid">
                        @foreach ($categoryTopics as $topic)
                            <a class="doc-topic" href="{{ route('help.show', $topic['slug']) }}">
                                <span class="doc-topic-role">{{ implode(' · ', $topic['roles']) }}</span>
                                <strong>{{ $topic['title'] }}</strong>
                                <p>{{ $topic['summary'] }}</p>
                                <span class="doc-topic-link">Lire le guide <span aria-hidden="true">→</span></span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    @endif
@endsection
