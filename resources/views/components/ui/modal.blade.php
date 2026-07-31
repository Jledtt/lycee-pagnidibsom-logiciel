@props([
    'id',
    'title',
    'description' => null,
    'size' => 'medium',
    'dismissible' => true,
    'open' => false,
])

@php
    $titleId = $id.'-title';
    $descriptionId = $id.'-description';
    $sizeClass = match ($size) {
        'small' => 'ui-dialog--small',
        'large' => 'ui-dialog--large',
        default => '',
    };
@endphp

<dialog
    id="{{ $id }}"
    {{ $attributes->class(['ui-dialog', $sizeClass]) }}
    aria-labelledby="{{ $titleId }}"
    @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    data-dismissible="{{ $dismissible ? 'true' : 'false' }}"
    @if ($open) open data-dialog-open-on-load @endif
>
    <div class="ui-dialog__surface">
        <header class="ui-dialog__header">
            <div class="ui-dialog__heading">
                <h2 class="ui-dialog__title" id="{{ $titleId }}">{{ $title }}</h2>
                @if ($description)
                    <p class="ui-dialog__description" id="{{ $descriptionId }}">{{ $description }}</p>
                @endif
            </div>

            @if ($dismissible)
                <button class="ui-dialog__close" type="button" aria-label="Fermer" title="Fermer" data-dialog-close>
                    <span aria-hidden="true">&times;</span>
                </button>
            @endif
        </header>

        <div class="ui-dialog__body">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="ui-dialog__footer">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</dialog>
