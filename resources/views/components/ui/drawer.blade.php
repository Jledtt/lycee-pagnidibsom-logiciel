@props([
    'id',
    'title',
    'description' => null,
    'wide' => false,
    'dismissible' => true,
    'open' => false,
])

@php
    $titleId = $id.'-title';
    $descriptionId = $id.'-description';
@endphp

<dialog
    id="{{ $id }}"
    {{ $attributes->class(['ui-drawer', 'ui-drawer--wide' => $wide]) }}
    aria-labelledby="{{ $titleId }}"
    @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    data-dismissible="{{ $dismissible ? 'true' : 'false' }}"
    @if ($open) open data-dialog-open-on-load @endif
>
    <div class="ui-drawer__surface">
        <header class="ui-drawer__header">
            <div class="ui-drawer__heading">
                <h2 class="ui-drawer__title" id="{{ $titleId }}">{{ $title }}</h2>
                @if ($description)
                    <p class="ui-drawer__description" id="{{ $descriptionId }}">{{ $description }}</p>
                @endif
            </div>

            @if ($dismissible)
                <button class="ui-drawer__close" type="button" aria-label="Fermer" title="Fermer" data-dialog-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            @endif
        </header>

        <div class="ui-drawer__body">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="ui-drawer__footer">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</dialog>
