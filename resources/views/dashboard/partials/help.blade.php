{{--
    Reusable page-level guide box. Pass $title / $description (both required)
    and an optional $example string — rendered as an "Example:" line.
    Purely presentational (HANDOFF §3 pattern #19 — Dashboard views never
    hold business logic), included at the top of a page's own @section('content').
--}}
<div class="mb-6 flex gap-3 rounded-lg border border-sky-200 bg-sky-50 p-4">
    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-bold text-white" aria-hidden="true">؟</span>
    <div>
        <p class="text-sm font-semibold text-sky-900">{{ $title }}</p>
        <p class="mt-1 text-sm leading-6 text-sky-800">{{ $description }}</p>
        @isset($example)
            <p class="mt-2 text-sm leading-6 text-sky-700">
                <span class="font-medium text-sky-900">{{ t('messages.help.example_label') }}:</span>
                <span>{{ $example }}</span>
            </p>
        @endisset
    </div>
</div>
