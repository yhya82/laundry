<div class="mx-auto max-w-5xl">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Welcome back, {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        {{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role assigned' }}
    </p>

    @if (! empty($this->operationalCards))
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->operationalCards as $card)
                <a href="{{ $card['href'] }}" wire:navigate class="rounded-lg border border-slate-200 bg-white p-4 hover:border-sky-300 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-sky-700">
                    <p class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ $card['value'] }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                </a>
            @endforeach
        </div>
    @endif

    @if ($this->canViewFinancials)
        @php
            $yesterday = $this->yesterdayMetrics;
            $trend = $this->revenueTrend;
            $maxTrend = max(1, ...array_map(fn ($p) => (float) $p['value'], $trend ?: [['value' => 0]]));
        @endphp

        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Yesterday ({{ \Illuminate\Support\Carbon::yesterday()->format('M j, Y') }})</h2>

            @if (empty($yesterday))
                <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-400 dark:border-slate-700">
                    No daily_statistics rows for yesterday yet — the aggregation job runs at 00:30 and needs at least one full day of activity.
                </p>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($yesterday[\App\Services\ReportingService::METRIC_REVENUE_COLLECTED] ?? 0, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Revenue collected</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($yesterday[\App\Services\ReportingService::METRIC_NET_REVENUE] ?? 0, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Net revenue</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($yesterday[\App\Services\ReportingService::METRIC_EXPENSES_RECORDED] ?? 0, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Expenses recorded</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ (int) ($yesterday[\App\Services\ReportingService::METRIC_ORDERS_COMPLETED] ?? 0) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Orders completed</p>
                    </div>
                </div>
            @endif

            <h2 class="mb-3 mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Revenue, last 7 days</h2>
            @if (empty($trend))
                <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-400 dark:border-slate-700">No historical data yet.</p>
            @else
                <div class="flex items-end gap-2 rounded-lg border border-slate-200 p-4 dark:border-slate-700" style="height: 140px">
                    @foreach ($trend as $point)
                        <div class="flex flex-1 flex-col items-center justify-end gap-1" style="height: 100%">
                            <span class="text-[10px] tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($point['value'], 0) }}</span>
                            <div class="w-full rounded-t bg-sky-500 dark:bg-sky-600" style="height: {{ max(2, ((float) $point['value'] / $maxTrend) * 100) }}%"></div>
                            <span class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($point['stat_date'])->format('D') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @can('reports.view')
                <a href="{{ route('reports.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-sky-600 hover:underline dark:text-sky-400">View full reports &rarr;</a>
            @endcan
        </div>
    @endif
</div>
