@props([
    'title',
    'active' => false,
])

<details {{ $attributes->class(['nav-section', 'active-section' => $active]) }} @if ($active) open @endif>
    <summary class="nav-section-title">
        <span>{{ $title }}</span>
        <span class="nav-section-chevron" aria-hidden="true"></span>
    </summary>

    <div class="nav-section-links">
        {{ $slot }}
    </div>
</details>
