<?php

namespace App\Livewire\Reports;

use App\Services\ReportingService;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $reportType = 'revenue';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->subDays(7)->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function render()
    {
        $service = app(ReportingService::class);
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $data = match ($this->reportType) {
            'expenses' => $service->expensesReport($from, $to),
            'orders' => $service->ordersReport($from, $to),
            default => $service->revenueReport($from, $to),
        };

        return view('livewire.reports.index', ['data' => $data])->title('Reports');
    }
}
