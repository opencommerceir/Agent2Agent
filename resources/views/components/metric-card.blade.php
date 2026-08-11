@props(['label' => null, 'value' => null, 'unit' => null])

<div {{ $attributes->class(['nexus-glass rounded-lg p-4']) }}>
    <p class="mb-1 font-mono text-xs uppercase tracking-wide text-nexus-text-muted">{{ $label }}</p>
    <p class="font-mono text-2xl font-semibold text-nexus-text">
        {{ $value }}
        @if ($unit)
            <span class="text-sm font-normal text-nexus-text-muted">{{ $unit }}</span>
        @endif
    </p>
</div>
