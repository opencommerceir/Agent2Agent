@extends('nexus::layouts.app')

@section('title', t('messages.nexus.network.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.network.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.network.how_it_works') }}</p>

            <div class="mb-4 flex flex-wrap gap-4 text-xs text-nexus-text-muted">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-nexus-cyan"></span>{{ t('messages.nexus.network.legend.self') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-nexus-success"></span>{{ t('messages.nexus.network.legend.direct') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-nexus-purple"></span>{{ t('messages.nexus.network.legend.coalition') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-nexus-warning"></span>{{ t('messages.nexus.network.legend.recommended') }}</span>
            </div>

            @if (count($network->nodes) <= 1)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.network.empty') }}</p>
            @else
                <div class="overflow-x-auto">
                    <svg id="nexus-network-graph" viewBox="0 0 640 640" class="mx-auto block w-full max-w-2xl" role="img" aria-label="{{ t('messages.nexus.network.title') }}"></svg>
                </div>
                <ul class="mt-4 grid gap-1 text-xs text-nexus-text-muted sm:grid-cols-2">
                    @foreach ($network->nodes as $node)
                        @if ($node['relation'] !== 'self')
                            <li>#{{ $node['businessId'] }} — {{ dashboard_language()->value === 'fa' ? $node['nameFa'] : $node['nameEn'] }} ({{ t('messages.nexus.network.legend.'.$node['relation']) }})</li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </x-nexus-panel>
    </div>

    <script>
        (function () {
            const network = @json($network);
            const svg = document.getElementById('nexus-network-graph');
            if (!svg || network.nodes.length <= 1) return;

            const center = { x: 320, y: 320 };
            const colors = { self: '#00F0FF', direct: '#22C55E', coalition: '#A855F7', recommended: '#F59E0B' };
            const positions = {};

            const selfNode = network.nodes.find(n => n.relation === 'self');
            positions[selfNode.businessId] = center;

            const inner = network.nodes.filter(n => n.relation === 'direct' || n.relation === 'coalition');
            inner.forEach((node, i) => {
                const angle = (2 * Math.PI * i) / Math.max(inner.length, 1);
                positions[node.businessId] = {
                    x: center.x + 180 * Math.cos(angle),
                    y: center.y + 180 * Math.sin(angle),
                    angle,
                };
            });

            const outer = network.nodes.filter(n => n.relation === 'recommended');
            const byParent = {};
            outer.forEach(n => { (byParent[n.parentBusinessId] = byParent[n.parentBusinessId] || []).push(n); });
            Object.keys(byParent).forEach(parentId => {
                const parentPos = positions[parentId] || { angle: 0 };
                const siblings = byParent[parentId];
                siblings.forEach((node, i) => {
                    const spread = 0.4;
                    const angle = parentPos.angle + (i - (siblings.length - 1) / 2) * spread;
                    positions[node.businessId] = {
                        x: center.x + 290 * Math.cos(angle),
                        y: center.y + 290 * Math.sin(angle),
                    };
                });
            });

            const ns = 'http://www.w3.org/2000/svg';
            const el = (tag, attrs) => {
                const node = document.createElementNS(ns, tag);
                Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));
                return node;
            };

            network.edges.forEach(edge => {
                const from = positions[edge.from];
                const to = positions[edge.to];
                if (!from || !to) return;
                svg.appendChild(el('line', {
                    x1: from.x, y1: from.y, x2: to.x, y2: to.y,
                    stroke: edge.type === 'recommended' ? '#F59E0B55' : '#00F0FF55',
                    'stroke-width': edge.type === 'negotiated' ? 2 : 1,
                    'stroke-dasharray': edge.type === 'recommended' ? '4,4' : 'none',
                }));
            });

            network.nodes.forEach(node => {
                const pos = positions[node.businessId];
                if (!pos) return;
                const r = node.relation === 'self' ? 22 : 14;
                svg.appendChild(el('circle', { cx: pos.x, cy: pos.y, r, fill: colors[node.relation] || '#888' }));
                const label = el('text', {
                    x: pos.x, y: pos.y + r + 14, 'text-anchor': 'middle',
                    fill: '#E5E7EB', 'font-size': '11', 'font-family': 'monospace',
                });
                label.textContent = '#' + node.businessId;
                svg.appendChild(label);
            });
        })();
    </script>
@endsection
