<!DOCTYPE html>
<html lang="{{ dashboard_language()->value }}" dir="{{ dashboard_language()->value === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ t('showcase.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes showcase-delegate-arrow {
            0%, 100% { transform: translateX(-4px); opacity: .5; }
            50% { transform: translateX(4px); opacity: 1; }
        }
        [dir="rtl"] .showcase-delegation-arrow { transform: scaleX(-1); }
        .showcase-delegation-arrow { display: inline-block; animation: showcase-delegate-arrow 1s ease-in-out infinite; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

@if ($demoMissing)
    <div class="mx-auto flex min-h-screen max-w-xl flex-col items-center justify-center px-6 text-center">
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-8">
            <h1 class="text-lg font-semibold text-amber-900">{{ t('showcase.demo_missing_title') }}</h1>
            <p class="mt-2 text-sm text-amber-800">{{ t('showcase.demo_missing_body') }}</p>
            <code class="mt-4 block rounded-md bg-amber-900/10 px-3 py-2 text-xs text-amber-900">php artisan demo:reset</code>
        </div>
    </div>
@else
    @php
        // Suggested Goals — each `goal` string must contain the exact
        // real keyword its target persona's own config/agents/{type}.php
        // planning_rules actually checks for (docs/agent-profiles.md's
        // own first-match-wins order), never a made-up phrase that would
        // silently fall through to a different rule than the one being
        // demonstrated. `label` is the only translated/user-facing part —
        // the `goal` text itself is what's actually sent to
        // POST /showcase/chat.
        //
        // The `delegate` goal's own text deliberately avoids every word
        // in PatternExtractor::KEYWORDS ('sales'/'revenue'/'inventory'/
        // 'customer'/'report') — DemoShowcaseSeeder pre-seeds a real CEO
        // execution for "Increase sales by 15% this week" (a learned
        // ExecutionPattern keyed on 'sales'), and ExecuteGoalAction
        // consults LearningServiceInterface::suggestPlan() *before*
        // either PlannerInterface implementation (§7.29) — a goal merely
        // *containing* "sales" (e.g. "...to the Sales team") matches that
        // already-learned pattern via ExecutionPattern::matches()'s own
        // substring check and reuses its old 4-step plan wholesale,
        // silently skipping config/agents/ceo.php's own new `delegate`
        // rule entirely. Caught live (not by an automated test — the
        // fresh-tenant fixtures Feature tests use never pre-seed a
        // colliding pattern) while smoke-testing this stage's own work
        // against a real `php artisan demo:reset` tenant.
        $suggestions = [
            'ceo' => [
                ['key' => 'sales', 'goal' => 'Increase sales by 15% this week', 'label' => t('showcase.suggestions.ceo.sales')],
                ['key' => 'revenue', 'goal' => 'Review our revenue this quarter', 'label' => t('showcase.suggestions.ceo.revenue')],
                ['key' => 'inventory', 'goal' => 'Check our inventory levels', 'label' => t('showcase.suggestions.ceo.inventory')],
                ['key' => 'delegate', 'goal' => 'Delegate this promotional campaign to another agent', 'label' => t('showcase.suggestions.ceo.delegate')],
            ],
            'sales' => [
                ['key' => 'promotion', 'goal' => 'Launch a promotional campaign this week', 'label' => t('showcase.suggestions.sales.promotion')],
                ['key' => 'performance', 'goal' => "Review this week's sales performance", 'label' => t('showcase.suggestions.sales.performance')],
                ['key' => 'default', 'goal' => 'Give me a quick status check', 'label' => t('showcase.suggestions.sales.default')],
            ],
            'support' => [
                ['key' => 'tickets', 'goal' => 'Review open support tickets', 'label' => t('showcase.suggestions.support.tickets')],
                ['key' => 'queue', 'goal' => 'Check the current ticket queue', 'label' => t('showcase.suggestions.support.queue')],
            ],
            'finance' => [
                ['key' => 'revenue', 'goal' => 'Review finance and revenue', 'label' => t('showcase.suggestions.finance.revenue')],
                ['key' => 'invoices', 'goal' => 'Check outstanding invoices', 'label' => t('showcase.suggestions.finance.invoices')],
            ],
        ];
        $panelUrls = [
            'products' => route('showcase.panel.products'),
            'orders' => route('showcase.panel.orders'),
            'kpis' => route('showcase.panel.kpis'),
        ];
        $historyUrls = [
            'list' => route('showcase.history'),
            'showBase' => url('/showcase/history'),
        ];
    @endphp
    <div
        x-data="showcaseChat(@js($suggestions), @js($panelUrls), @js($historyUrls))"
        class="mx-auto flex min-h-screen max-w-6xl flex-col gap-4 px-4 py-8 lg:flex-row"
    >
        {{-- Chat column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="relative mb-6 text-center">
                <button
                    type="button"
                    @click="toggleHistory()"
                    class="absolute end-0 top-0 rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:border-blue-400 hover:text-blue-700"
                >
                    🕘 {{ t('showcase.history.button') }}
                </button>
                <h1 class="text-2xl font-bold">{{ t('showcase.title') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('showcase.subtitle') }}</p>
            </header>

            {{-- Persona selector --}}
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button type="button" @click="selectedPersona = 'ceo'"
                    :class="selectedPersona === 'ceo' ? 'ring-2 ring-blue-500 bg-blue-50' : 'bg-white'"
                    class="flex flex-col items-center gap-1 rounded-xl border border-gray-200 p-4 text-sm transition hover:bg-blue-50/50">
                    <span class="text-2xl">🧑‍💼</span>
                    <span class="font-medium">{{ t('showcase.personas.ceo') }}</span>
                </button>
                <button type="button" @click="selectedPersona = 'sales'"
                    :class="selectedPersona === 'sales' ? 'ring-2 ring-emerald-500 bg-emerald-50' : 'bg-white'"
                    class="flex flex-col items-center gap-1 rounded-xl border border-gray-200 p-4 text-sm transition hover:bg-emerald-50/50">
                    <span class="text-2xl">📈</span>
                    <span class="font-medium">{{ t('showcase.personas.sales') }}</span>
                </button>
                <button type="button" @click="selectedPersona = 'support'"
                    :class="selectedPersona === 'support' ? 'ring-2 ring-amber-500 bg-amber-50' : 'bg-white'"
                    class="flex flex-col items-center gap-1 rounded-xl border border-gray-200 p-4 text-sm transition hover:bg-amber-50/50">
                    <span class="text-2xl">🎧</span>
                    <span class="font-medium">{{ t('showcase.personas.support') }}</span>
                </button>
                <button type="button" @click="selectedPersona = 'finance'"
                    :class="selectedPersona === 'finance' ? 'ring-2 ring-purple-500 bg-purple-50' : 'bg-white'"
                    class="flex flex-col items-center gap-1 rounded-xl border border-gray-200 p-4 text-sm transition hover:bg-purple-50/50">
                    <span class="text-2xl">💰</span>
                    <span class="font-medium">{{ t('showcase.personas.finance') }}</span>
                </button>
            </div>

            {{-- Suggested Goals --}}
            <div class="mb-4 flex flex-wrap gap-2">
                <template x-for="suggestion in suggestions[selectedPersona]" :key="suggestion.key">
                    <button
                        type="button"
                        @click="sendGoal(suggestion.goal)"
                        :disabled="sending"
                        class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:border-blue-400 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        x-text="suggestion.label"
                    ></button>
                </template>
            </div>

            {{-- Message list --}}
            <div class="flex-1 space-y-6 overflow-y-auto pb-6" x-ref="messageList">
                <template x-for="(message, index) in messages" :key="index">
                    <div class="space-y-3">
                        <div class="flex justify-end">
                            <div class="max-w-xl rounded-2xl bg-blue-600 px-4 py-2 text-sm text-white" x-text="message.goal"></div>
                        </div>

                        <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <template x-if="message.historical">
                                <p class="mb-2 text-xs font-medium text-gray-400">🕘 {{ t('showcase.history.replayed') }}</p>
                            </template>

                            <template x-if="message.loading">
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <span class="inline-block h-2 w-2 animate-ping rounded-full bg-blue-500"></span>
                                    <span>{{ t('showcase.thinking') }}</span>
                                </div>
                            </template>

                            <template x-if="message.error">
                                <div class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="message.error"></div>
                            </template>

                            <template x-if="message.result">
                                <div class="space-y-4">
                                    {{-- Pre-execution reasoning --}}
                                    <template x-if="message.result.pre_reasoning && message.revealStage >= 0">
                                        <div x-show="message.revealStage >= 0" x-transition.opacity>
                                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">🤔 {{ t('showcase.pre_reasoning_title') }}</h3>
                                            <ul class="mb-2 list-inside list-disc text-sm text-gray-700">
                                                <template x-for="thought in message.result.pre_reasoning.thoughts" :key="thought">
                                                    <li x-text="thought"></li>
                                                </template>
                                            </ul>
                                            <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                                                <span>{{ t('showcase.confidence_label') }}</span>
                                                <span x-text="Math.round(message.result.pre_reasoning.confidence_score * 100) + '%'"></span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-gray-200">
                                                <div class="h-2 rounded-full bg-blue-500 transition-all" :style="'width: ' + (message.result.pre_reasoning.confidence_score * 100) + '%'"></div>
                                            </div>
                                            <template x-if="message.result.pre_reasoning.alternatives.length > 0">
                                                <div class="mt-2 text-xs text-gray-500">
                                                    <span class="font-medium">{{ t('showcase.alternatives_title') }}:</span>
                                                    <template x-for="alt in message.result.pre_reasoning.alternatives" :key="alt.plan">
                                                        <div x-text="alt.plan + ' (' + Math.round(alt.confidence * 100) + '%) — ' + alt.reason"></div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Step checklist (delegation steps render as a rich sub-card) --}}
                                    <div x-show="message.revealStage >= 1" x-transition.opacity>
                                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">⚙️ {{ t('showcase.steps_title') }}</h3>
                                        <ul class="space-y-1">
                                            <template x-for="step in message.result.steps" :key="step.capability">
                                                <li>
                                                    <template x-if="step.capability !== 'agent.collaboration.delegate'">
                                                        <div class="flex items-center gap-2 py-0.5 text-sm">
                                                            <span x-show="step.status === 'completed'" class="text-emerald-600">✅</span>
                                                            <span x-show="step.status === 'failed'" class="text-red-600">❌</span>
                                                            <span x-show="step.status !== 'completed' && step.status !== 'failed'" class="text-gray-400">◌</span>
                                                            <span class="font-mono text-xs text-gray-700" x-text="step.capability"></span>
                                                        </div>
                                                    </template>

                                                    {{-- Delegation visual: animated arrow between personas + nested sub-execution card --}}
                                                    <template x-if="step.capability === 'agent.collaboration.delegate'">
                                                        <div class="my-2 rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                                                            <div class="mb-1 flex items-center justify-center gap-3">
                                                                <span class="text-2xl" x-text="personaEmoji(step.input.from_agent)"></span>
                                                                <span class="showcase-delegation-arrow text-xl text-indigo-500" aria-hidden="true">➜</span>
                                                                <span class="text-2xl" x-text="personaEmoji(step.input.to_agent)"></span>
                                                            </div>
                                                            <p class="mb-2 text-center text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                                                <span x-text="step.input.from_agent"></span> {{ t('showcase.delegation.delegated_to') }} <span x-text="step.input.to_agent"></span>
                                                            </p>
                                                            <template x-if="step.output && step.output.result">
                                                                <div class="rounded-lg border border-indigo-100 bg-white p-2">
                                                                    <p class="mb-1 text-xs font-semibold text-gray-500" x-text="t_executed_by(step.output.result.agent_type)"></p>
                                                                    <ul class="space-y-0.5">
                                                                        <template x-for="subStep in step.output.result.steps" :key="subStep.capability">
                                                                            <li class="flex items-center gap-2 text-xs">
                                                                                <span x-show="subStep.status === 'completed'" class="text-emerald-600">✅</span>
                                                                                <span x-show="subStep.status === 'failed'" class="text-red-600">❌</span>
                                                                                <span class="font-mono text-gray-700" x-text="subStep.capability"></span>
                                                                            </li>
                                                                        </template>
                                                                    </ul>
                                                                    <p class="mt-1 text-xs text-gray-600" x-text="step.output.result.summary"></p>
                                                                </div>
                                                            </template>
                                                            <template x-if="step.status === 'failed'">
                                                                <p class="mt-2 text-xs text-red-700" x-text="step.error"></p>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    {{-- Summary --}}
                                    <div x-show="message.revealStage >= 2" x-transition.opacity>
                                        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('showcase.summary_title') }}</h3>
                                        <p class="text-sm text-gray-800" x-text="message.result.summary"></p>
                                        <span
                                            class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800': message.result.status === 'completed',
                                                'bg-amber-100 text-amber-800': message.result.status === 'partial',
                                                'bg-red-100 text-red-800': message.result.status === 'failed',
                                            }"
                                            x-text="message.result.status"
                                        ></span>
                                    </div>

                                    {{-- Post-execution reflection --}}
                                    <template x-if="message.result.post_reasoning">
                                        <div x-show="message.revealStage >= 3" x-transition.opacity>
                                            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">✅ {{ t('showcase.post_reasoning_title') }}</h3>
                                            <ul class="mb-2 list-inside list-disc text-sm text-gray-700">
                                                <template x-for="thought in message.result.post_reasoning.thoughts" :key="thought">
                                                    <li x-text="thought"></li>
                                                </template>
                                            </ul>
                                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                                <span>{{ t('showcase.confidence_label') }}</span>
                                                <span x-text="Math.round(message.result.post_reasoning.confidence_score * 100) + '%'"></span>
                                            </div>
                                            <p class="mt-2 text-xs italic text-gray-500" x-text="message.result.explanation"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Real-AI toggle --}}
            <label class="mb-2 flex items-center gap-2 text-xs text-gray-500" title="{{ t('showcase.ai_toggle.hint') }}">
                <input type="checkbox" x-model="useRealAi" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>🧠 {{ t('showcase.ai_toggle.label') }}</span>
            </label>

            {{-- Input --}}
            <form @submit.prevent="sendGoal()" class="sticky bottom-4 flex gap-2 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm">
                <input
                    type="text"
                    x-model="goalText"
                    :placeholder="'{{ t('showcase.input_placeholder') }}'"
                    :disabled="sending"
                    class="flex-1 rounded-xl border-0 px-3 py-2 text-sm focus:outline-none focus:ring-0"
                >
                <button
                    type="submit"
                    :disabled="sending || goalText.trim() === ''"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ t('showcase.send') }}
                </button>
            </form>
        </div>

        {{-- Live data panel --}}
        <aside class="flex w-full flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm lg:w-80 lg:shrink-0">
            <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ t('showcase.panel.title') }}</h2>

            <div class="mb-3 flex gap-1 rounded-lg bg-gray-100 p-1 text-xs">
                <button type="button" @click="selectPanelTab('kpis')"
                    :class="activePanelTab === 'kpis' ? 'bg-white shadow-sm font-medium' : 'text-gray-500'"
                    class="flex-1 rounded-md px-2 py-1.5 transition">{{ t('showcase.panel.tabs.kpis') }}</button>
                <button type="button" @click="selectPanelTab('products')"
                    :class="activePanelTab === 'products' ? 'bg-white shadow-sm font-medium' : 'text-gray-500'"
                    class="flex-1 rounded-md px-2 py-1.5 transition">{{ t('showcase.panel.tabs.products') }}</button>
                <button type="button" @click="selectPanelTab('orders')"
                    :class="activePanelTab === 'orders' ? 'bg-white shadow-sm font-medium' : 'text-gray-500'"
                    class="flex-1 rounded-md px-2 py-1.5 transition">{{ t('showcase.panel.tabs.orders') }}</button>
            </div>

            <div class="min-h-[12rem] flex-1 overflow-y-auto" :class="panelLoading[activePanelTab] ? 'opacity-50' : ''">
                <div x-html="panelHtml[activePanelTab]"></div>
            </div>
        </aside>

        {{-- History sidebar (Phase 3, §7.33) — a slide-in drawer rather
             than a permanent third column, so the chat/live-panel layout
             stays the same on every screen size; opened via the 🕘 button
             in the header. --}}
        <div x-show="historyOpen" x-cloak class="fixed inset-0 z-40" style="display: none;">
            <div class="absolute inset-0 bg-black/30" @click="historyOpen = false"></div>
            <aside
                x-show="historyOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full rtl:-translate-x-full"
                x-transition:enter-end="translate-x-0"
                class="absolute inset-y-0 end-0 flex w-full max-w-sm flex-col bg-white p-4 shadow-xl"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">{{ t('showcase.history.title') }}</h2>
                    <button type="button" @click="historyOpen = false" class="text-gray-400 hover:text-gray-600" aria-label="Close">✕</button>
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto">
                    <template x-if="historyLoading">
                        <p class="text-sm text-gray-400">{{ t('showcase.thinking') }}</p>
                    </template>
                    <template x-if="!historyLoading && historyItems.length === 0">
                        <p class="text-sm text-gray-400">{{ t('showcase.history.empty') }}</p>
                    </template>
                    <template x-for="item in historyItems" :key="item.id">
                        <button
                            type="button"
                            @click="openHistoryItem(item.id)"
                            class="block w-full rounded-lg border border-gray-100 px-3 py-2 text-start text-sm transition hover:border-blue-300 hover:bg-blue-50/50"
                        >
                            <span class="mb-0.5 flex items-center gap-1.5 text-xs text-gray-400">
                                <span x-text="personaEmoji(item.agent_type)"></span>
                                <span x-text="item.agent_type"></span>
                                <span
                                    class="ms-auto rounded-full px-1.5 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800': item.status === 'completed',
                                        'bg-amber-100 text-amber-800': item.status === 'partial',
                                        'bg-red-100 text-red-800': item.status === 'failed',
                                    }"
                                    x-text="item.status"
                                ></span>
                            </span>
                            <span class="line-clamp-2 text-gray-700" x-text="item.goal"></span>
                        </button>
                    </template>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('showcaseChat', (suggestions, panelUrls, historyUrls) => ({
                selectedPersona: 'ceo',
                goalText: '',
                sending: false,
                useRealAi: false,
                messages: [],
                suggestions: suggestions,
                panelUrls: panelUrls,
                historyUrls: historyUrls,
                activePanelTab: 'kpis',
                panelHtml: { products: '', orders: '', kpis: '' },
                panelLoading: { products: false, orders: false, kpis: false },
                historyOpen: false,
                historyItems: [],
                historyLoading: false,

                init() {
                    this.loadPanel(this.activePanelTab);
                },

                async sendGoal(overrideGoal) {
                    const goal = (typeof overrideGoal === 'string' ? overrideGoal : this.goalText).trim();
                    if (goal === '' || this.sending) {
                        return;
                    }

                    this.goalText = '';
                    this.sending = true;

                    const message = { goal, persona: this.selectedPersona, loading: true, error: null, result: null, revealStage: -1 };
                    this.messages.push(message);
                    const index = this.messages.length - 1;

                    try {
                        const response = await fetch('{{ route('showcase.chat') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ goal, agent_type: this.selectedPersona, use_real_ai: this.useRealAi }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.messages[index].error = data.error || 'Something went wrong.';
                        } else {
                            this.messages[index].result = data;
                            this.revealSteps(index);
                            // Only the currently active tab refreshes — never
                            // all three at once (avoids tripling server load
                            // on every single chat turn during a live demo).
                            this.refreshActivePanel();
                        }
                    } catch (e) {
                        this.messages[index].error = 'Network error — please try again.';
                    } finally {
                        this.messages[index].loading = false;
                        this.sending = false;
                        this.$nextTick(() => this.scrollToBottom());
                    }
                },

                revealSteps(index) {
                    let stage = 0;
                    this.messages[index].revealStage = stage;
                    const tick = () => {
                        stage++;
                        if (stage > 3) {
                            return;
                        }
                        this.messages[index].revealStage = stage;
                        this.$nextTick(() => this.scrollToBottom());
                        setTimeout(tick, 450);
                    };
                    setTimeout(tick, 450);
                },

                scrollToBottom() {
                    if (this.$refs.messageList) {
                        this.$refs.messageList.scrollTop = this.$refs.messageList.scrollHeight;
                    }
                },

                selectPanelTab(tab) {
                    this.activePanelTab = tab;
                    this.loadPanel(tab);
                },

                refreshActivePanel() {
                    this.loadPanel(this.activePanelTab);
                },

                async loadPanel(tab) {
                    this.panelLoading[tab] = true;
                    try {
                        const response = await fetch(this.panelUrls[tab]);
                        this.panelHtml[tab] = await response.text();
                    } catch (e) {
                        this.panelHtml[tab] = '<p class="text-sm text-red-500">{{ t('showcase.panel.error') }}</p>';
                    } finally {
                        this.panelLoading[tab] = false;
                    }
                },

                toggleHistory() {
                    this.historyOpen = !this.historyOpen;
                    if (this.historyOpen) {
                        this.loadHistory();
                    }
                },

                async loadHistory() {
                    this.historyLoading = true;
                    try {
                        const response = await fetch(this.historyUrls.list);
                        const data = await response.json();
                        this.historyItems = data.executions;
                    } catch (e) {
                        this.historyItems = [];
                    } finally {
                        this.historyLoading = false;
                    }
                },

                async openHistoryItem(id) {
                    this.historyOpen = false;

                    const message = { goal: '', persona: null, loading: true, error: null, result: null, revealStage: -1, historical: true };
                    this.messages.push(message);
                    const index = this.messages.length - 1;

                    try {
                        const response = await fetch(this.historyUrls.showBase + '/' + id);
                        const data = await response.json();

                        if (!response.ok) {
                            this.messages[index].error = data.error || 'Could not load this conversation.';
                        } else {
                            this.messages[index].goal = data.goal;
                            this.messages[index].result = data;
                            // Not a live run — show every section at once,
                            // no staged reveal (that animation exists to
                            // pace a *new* run, not a replay).
                            this.messages[index].revealStage = 3;
                        }
                    } catch (e) {
                        this.messages[index].error = 'Network error — please try again.';
                    } finally {
                        this.messages[index].loading = false;
                        this.$nextTick(() => this.scrollToBottom());
                    }
                },

                personaEmoji(type) {
                    return { ceo: '🧑‍💼', sales: '📈', support: '🎧', finance: '💰' }[type] || '🤖';
                },

                t_executed_by(agentType) {
                    const label = agentType.charAt(0).toUpperCase() + agentType.slice(1) + ' Agent';
                    return '{{ t('showcase.delegation.executed_by') }}'.replace(':agent', label);
                },
            }));
        });
    </script>
@endif

</body>
</html>
