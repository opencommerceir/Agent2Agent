@extends('nexus::layouts.app')

@section('title', 'Foundation')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-4 sm:grid-cols-2">
        <x-nexus-panel title="Platform Status">
            <div class="space-y-2">
                <x-agent-pulse label="Core" status="online" />
                <x-agent-pulse label="MCP Gateway" status="online" />
                <x-agent-pulse label="Agent Orchestrator" status="online" />
            </div>
        </x-nexus-panel>

        <x-nexus-panel title="Live Feed">
            <x-data-stream :items="[]">
                <p class="text-sm text-slate-500">No events yet.</p>
            </x-data-stream>
        </x-nexus-panel>

        <x-nexus-panel title="Phase" class="sm:col-span-2">
            <p class="text-sm text-slate-400">
                Phase 0 — Foundation. No business domains are wired up yet; this page exists to prove
                <code class="rounded bg-nexus-surface px-1.5 py-0.5 text-nexus-cyan">NexusServiceProvider</code>,
                config, routes, views, and the Jarvis design system all boot correctly.
            </p>
        </x-nexus-panel>
    </div>
@endsection
