@props([
    'label' => 'Autres actions',
])

<details {{ $attributes->class('ui-action-menu') }}>
    <summary class="btn btn-subtle">
        <span>{{ $label }}</span>
        <span class="ui-action-menu__chevron" aria-hidden="true">&#9662;</span>
    </summary>
    <div class="ui-action-menu__panel">
        {{ $slot }}
    </div>
</details>
