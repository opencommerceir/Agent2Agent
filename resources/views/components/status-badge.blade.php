@props(['status' => 'idle', 'label' => null])

@php
    $styles = match ($status) {
        'active', 'success' => ['text-nexus-success', 'border-nexus-success/30', 'bg-nexus-success/10'],
        'thinking' => ['text-nexus-purple', 'border-nexus-purple/30', 'bg-nexus-purple/10'],
        'warning' => ['text-nexus-warning', 'border-nexus-warning/30', 'bg-nexus-warning/10'],
        'error' => ['text-nexus-error', 'border-nexus-error/30', 'bg-nexus-error/10'],
        default => ['text-nexus-text-muted', 'border-nexus-border', 'bg-nexus-glass'],
    };
    [$text, $border, $bg] = $styles;
@endphp

<span {{ $attributes->class(["inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-mono text-xs {$text} {$border} {$bg}"]) }}>
    <span class="size-1.5 rounded-full bg-current"></span>
    {{ $label ?? $slot }}
</span>
