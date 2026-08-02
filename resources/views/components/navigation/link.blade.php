@props([
    'href',
    'active' => false,
])

<a href="{{ $href }}" {{ $attributes->class(['active' => $active]) }} @if ($active) aria-current="page" @endif>
    <span class="nav-dot" aria-hidden="true"></span>
    <span>{{ $slot }}</span>
</a>
