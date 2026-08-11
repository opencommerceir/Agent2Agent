@props(['title' => null, 'glow' => 'cyan', 'corner' => 'round', 'interactive' => false])

@php
    $glowBorder = match ($glow) {
        'cyan' => 'border-nexus-cyan/35',
        'purple' => 'border-nexus-purple/35',
        default => 'border-nexus-border',
    };

    $cornerClip = $corner === 'cut'
        ? 'clip-path:polygon(0 0,calc(100% - 18px) 0,100% 18px,100% 100%,0 100%);'
        : '';
@endphp

<div
    {{ $attributes->class([
        'nexus-glass p-6',
        $glowBorder,
        'rounded-lg' => $corner === 'round',
        'rounded-none' => $corner === 'cut',
        'transition hover:border-nexus-cyan/50 hover:-translate-y-0.5' => $interactive,
    ]) }}
    @if ($cornerClip) style="{{ $cornerClip }}" @endif
>
    @if ($title)
        <h3 class="mb-3 font-mono text-sm font-semibold uppercase tracking-wide text-nexus-cyan">{{ $title }}</h3>
    @endif

    {{ $slot }}
</div>
