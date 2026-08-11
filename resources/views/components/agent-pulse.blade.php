@props(['label' => null, 'status' => 'idle'])

@php
    [$color, $animation, $duration] = match ($status) {
        'thinking' => ['text-nexus-purple', 'animate-[breathe_2s_ease-in-out_infinite]', null],
        'active' => ['text-nexus-cyan', 'animate-[pulse-glow_1.2s_ease-in-out_infinite]', null],
        'warning' => ['text-nexus-warning', 'animate-[breathe_1s_ease-in-out_infinite]', null],
        'error' => ['text-nexus-error', 'animate-[breathe_0.6s_ease-in-out_infinite]', null],
        default => ['text-nexus-text-muted', 'animate-[breathe_4s_ease-in-out_infinite]', null],
    };
@endphp

<div {{ $attributes->class(['inline-flex items-center gap-2 text-xs text-nexus-text-muted']) }}>
    <span class="size-2.5 rounded-full bg-current {{ $color }} {{ $animation }}"></span>

    @if ($label)
        <span class="{{ $color }}">{{ $label }}</span>
    @endif
</div>
