<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ ucfirst($type) }} report</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 5px 4px; border-bottom: 1px solid #e2e8f0; }
        th { font-size: 10px; text-transform: uppercase; color: #64748b; }
        .totals { margin-top: 12px; font-size: 12px; }
        .totals p { margin: 2px 0; }
    </style>
</head>
<body>
    <h1>{{ ucfirst($type) }} report</h1>
    <p class="muted">{{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }}</p>

    @if ($type === 'expenses')
        <table>
            <thead><tr><th>Date</th><th>Title</th><th>Category</th><th style="text-align:right">Amount</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($data['rows'] as $expense)
                    <tr>
                        <td>{{ $expense->expense_date->format('M j, Y') }}</td>
                        <td>{{ $expense->title }}</td>
                        <td>{{ $expense->category->name }}</td>
                        <td style="text-align:right">{{ number_format($expense->amount, 2) }}</td>
                        <td>{{ $expense->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            @foreach ($data['by_category'] as $category => $amount)
                <p>{{ $category }}: {{ number_format($amount, 2) }}</p>
            @endforeach
            <p><strong>Total: {{ number_format($data['total'], 2) }}</strong></p>
        </div>
    @elseif ($type === 'orders')
        <table>
            <thead><tr><th>Order #</th><th>Customer</th><th>Status</th><th style="text-align:right">Total</th><th>Created</th></tr></thead>
            <tbody>
                @foreach ($data['rows'] as $order)
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->customer->name }}</td>
                        <td>{{ $order->status }}</td>
                        <td style="text-align:right">{{ number_format($order->total_amount, 2) }}</td>
                        <td>{{ $order->created_at->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            @foreach ($data['by_status'] as $status => $count)
                <p>{{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}</p>
            @endforeach
        </div>
    @else
        <table>
            <thead><tr><th>Payment #</th><th>Customer</th><th>Order #</th><th style="text-align:right">Amount</th><th>Method</th><th>Date</th></tr></thead>
            <tbody>
                @foreach ($data['rows'] as $payment)
                    <tr>
                        <td>{{ $payment->payment_number }}</td>
                        <td>{{ $payment->customer->name }}</td>
                        <td>{{ $payment->laundryOrder->order_number }}</td>
                        <td style="text-align:right">{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                        <td>{{ $payment->created_at->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="totals">
            <p>Total revenue: {{ number_format($data['total_revenue'], 2) }}</p>
            <p>Total refunds: {{ number_format($data['total_refunds'], 2) }}</p>
            <p><strong>Net revenue: {{ number_format($data['net_revenue'], 2) }}</strong></p>
        </div>
    @endif
</body>
</html>
