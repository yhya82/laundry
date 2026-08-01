<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every export streams the file directly (report_exports has no
 * file-path/storage column — it's a fire-and-forget audit log of what was
 * generated, when, and by whom, not a job queue) and logs exactly one
 * report_exports row per download, matching chk_report_exports_format's
 * three allowed values.
 *
 * 'excel' has no real .xlsx writer available in this environment (no
 * PhpSpreadsheet/Maatwebsite installed, and adding a new Composer
 * dependency wasn't part of this phase's scope) — it's served as an
 * HTML table with an .xls extension and the legacy application/vnd.ms-excel
 * content type instead. Excel opens this correctly; it just isn't OOXML.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $format, ReportingService $service): Response|StreamedResponse
    {
        return match ($format) {
            'pdf' => $this->pdf($request, $service),
            'csv' => $this->csv($request, $service),
            'excel' => $this->excel($request, $service),
        };
    }

    public function pdf(Request $request, ReportingService $service): Response
    {
        [$type, $from, $to, $data] = $this->resolve($request, $service);

        $this->logExport($type, 'pdf', $from, $to);

        $pdf = Pdf::loadView('pdfs.report', ['type' => $type, 'from' => $from, 'to' => $to, 'data' => $data]);

        return $pdf->stream("{$type}-report-{$from->toDateString()}-to-{$to->toDateString()}.pdf");
    }

    public function csv(Request $request, ReportingService $service): StreamedResponse
    {
        [$type, $from, $to, $data] = $this->resolve($request, $service);

        $this->logExport($type, 'csv', $from, $to);

        $filename = "{$type}-report-{$from->toDateString()}-to-{$to->toDateString()}.csv";
        $rows = $this->tableRows($type, $data);

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function excel(Request $request, ReportingService $service): Response
    {
        [$type, $from, $to, $data] = $this->resolve($request, $service);

        $this->logExport($type, 'excel', $from, $to);

        $filename = "{$type}-report-{$from->toDateString()}-to-{$to->toDateString()}.xls";
        $rows = $this->tableRows($type, $data);

        $html = '<table border="1"><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>'.collect($row)->map(fn ($cell) => '<td>'.e($cell).'</td>')->implode('').'</tr>';
        }
        $html .= '</tbody></table>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon, 3: array}
     */
    private function resolve(Request $request, ReportingService $service): array
    {
        $type = $request->string('report_type', 'revenue')->value();
        $from = Carbon::parse($request->string('from', now()->subDays(7)->toDateString())->value());
        $to = Carbon::parse($request->string('to', now()->toDateString())->value());

        $data = match ($type) {
            'expenses' => $service->expensesReport($from, $to),
            'orders' => $service->ordersReport($from, $to),
            default => $service->revenueReport($from, $to),
        };

        return [$type, $from, $to, $data];
    }

    private function tableRows(string $type, array $data): array
    {
        return match ($type) {
            'expenses' => [
                ['Date', 'Title', 'Category', 'Amount', 'Status'],
                ...$data['rows']->map(fn ($e) => [$e->expense_date->toDateString(), $e->title, $e->category->name, (string) $e->amount, $e->status])->all(),
            ],
            'orders' => [
                ['Order #', 'Customer', 'Status', 'Total', 'Created'],
                ...$data['rows']->map(fn ($o) => [$o->order_number, $o->customer->name, $o->status, (string) $o->total_amount, $o->created_at->toDateString()])->all(),
            ],
            default => [
                ['Payment #', 'Customer', 'Order #', 'Amount', 'Method', 'Date'],
                ...$data['rows']->map(fn ($p) => [$p->payment_number, $p->customer->name, $p->laundryOrder->order_number, (string) $p->amount, $p->payment_method, $p->created_at->toDateString()])->all(),
            ],
        };
    }

    private function logExport(string $type, string $format, Carbon $from, Carbon $to): void
    {
        ReportExport::create([
            'report_type' => $type,
            'export_format' => $format,
            'date_range_start' => $from->toDateString(),
            'date_range_end' => $to->toDateString(),
            'generated_by' => Auth::id(),
            'generated_at' => now(),
        ]);
    }
}
