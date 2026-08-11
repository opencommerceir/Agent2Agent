@props(['label' => null, 'status' => 'online'])

@php
    $color = match ($status) {
        'online' => 'bg-nexus-cyan',
        'busy' => 'bg-nexus-purple',
        default => 'bg-slate-500',
    };
@endphp

<div {{ $attributes->class(['inline-flex items-center gap-2 text-xs text-slate-300']) }}>
    <span class="relative flex size-2.5">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $color }} opacity-75"></span>
        <span class="relative inline-flex size-2.5 rounded-full {{ $color }}"></span>
    </span>

    @if ($label)
        <span>{{ $label }}</span>
    @endif
</div>
