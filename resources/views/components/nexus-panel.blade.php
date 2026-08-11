@props(['title' => null])

<div {{ $attributes->class(['nexus-glass rounded-xl p-4 sm:p-6']) }}>
    @if ($title)
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-nexus-cyan">{{ $title }}</h3>
    @endif

    {{ $slot }}
</div>
