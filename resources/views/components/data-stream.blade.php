@props(['items' => []])

<div {{ $attributes->class(['max-h-64 space-y-2 overflow-y-auto']) }}>
    @forelse ($items as $item)
        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface/60 px-3 py-2 text-sm text-slate-300">
            {{ $item }}
        </div>
    @empty
        {{ $slot }}
    @endforelse
</div>
