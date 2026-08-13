@extends('nexus::layouts.app')

@section('title', 'Foundation')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-4 sm:grid-cols-2">
        <x-nexus-panel title="Platform Status">
            <div class="space-y-2">
                <x-agent-pulse label="Core" status="active" />
                <x-agent-pulse label="MCP Gateway" status="active" />
                <x-agent-pulse label="Agent Orchestrator" status="active" />
            </div>
        </x-nexus-panel>

        <x-nexus-panel title="Live Feed">
            <x-data-stream :items="[]">
                <p class="text-sm text-nexus-text-muted">No events yet.</p>
            </x-data-stream>
        </x-nexus-panel>

        <x-nexus-panel title="Design System" corner="cut" glow="purple">
            <div class="flex flex-wrap items-center gap-3">
                <x-status-badge status="active" label="Verified" />
                <x-status-badge status="thinking" label="Negotiating" />
                <x-status-badge status="warning" label="Review needed" />
                <x-status-badge status="error" label="Failed" />
            </div>
        </x-nexus-panel>

        <x-nexus-panel title="Metrics">
            <div class="grid grid-cols-2 gap-3">
                <x-metric-card label="Businesses" value="0" />
                <x-metric-card label="Agents" value="0" />
            </div>
        </x-nexus-panel>

        <x-nexus-panel title="Phase" class="sm:col-span-2">
            <p class="text-sm text-nexus-text-muted">
                Phase 1 — Business &amp; Agent Core. Businesses can register, get verified, and receive an
                auto-provisioned negotiating Agent with real
                <code class="rounded bg-nexus-surface-1 px-1.5 py-0.5 text-nexus-cyan">MCP</code>
                credentials. This page proves the Jarvis design system (tokens, components, RTL) still boots
                correctly after the M5 reconciliation pass against
                <code class="rounded bg-nexus-surface-1 px-1.5 py-0.5 text-nexus-cyan">docs/claude/ui-system-design.md</code>.
            </p>
        </x-nexus-panel>

        <x-nexus-panel title="Build on Nexus" class="sm:col-span-2">
            <p class="text-sm text-nexus-text-muted">
                Public REST API, Webhooks, and API key management for third-party developers —
                <a href="{{ route('nexus.developer.docs.index') }}" class="text-nexus-cyan hover:underline">read the API docs</a>.
            </p>
        </x-nexus-panel>
    </div>
@endsection
