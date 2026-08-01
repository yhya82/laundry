<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #64748b; }
        .header { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #e2e8f0; }
        th { font-size: 10px; text-transform: uppercase; color: #64748b; }
        .totals td { border-bottom: none; padding: 3px 4px; }
        .totals .label { text-align: right; }
        .totals .value { text-align: right; width: 100px; }
        .grand { font-size: 14px; font-weight: bold; border-top: 1px solid #1e293b; }
        .cancelled { margin-top: 16px; padding: 8px; background: #fef2f2; color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $receipt->business_name_snapshot }}</h1>
        <p class="muted">Receipt {{ $receipt->receipt_number }} &middot; {{ $receipt->generated_at->format('M j, Y g:ia') }}</p>
    </div>

    <p>
        <strong>Customer:</strong> {{ $receipt->customer->name }}<br>
        <strong>Order:</strong> {{ $receipt->laundryOrder->order_number }}<br>
        <strong>Payment method:</strong> {{ str_replace('_', ' ', $receipt->payment->payment_method) }}
    </p>

    <table>
        <thead>
            <tr><th>Package</th><th>Items</th><th style="text-align:right">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($receipt->laundryOrder->packages as $line)
                <tr>
                    <td>{{ $line->package->name }}</td>
                    <td>{{ $line->items->map(fn ($i) => $i->clothingType->name.' x'.$i->quantity)->implode(', ') ?: '—' }}</td>
                    <td style="text-align:right">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Subtotal</td><td class="value">{{ number_format($receipt->subtotal_snapshot, 2) }}</td></tr>
        @if ($receipt->discount_snapshot > 0)
            <tr><td class="label">Discount</td><td class="value">−{{ number_format($receipt->discount_snapshot, 2) }}</td></tr>
        @endif
        @if ($receipt->delivery_fee_snapshot > 0)
            <tr><td class="label">Delivery fee</td><td class="value">{{ number_format($receipt->delivery_fee_snapshot, 2) }}</td></tr>
        @endif
        @if ($receipt->store_credit_used_snapshot > 0)
            <tr><td class="label">Store credit applied</td><td class="value">−{{ number_format($receipt->store_credit_used_snapshot, 2) }}</td></tr>
        @endif
        <tr class="grand"><td class="label">Total</td><td class="value">{{ number_format($receipt->total_snapshot, 2) }}</td></tr>
    </table>

    @if ($receipt->status === 'cancelled')
        <p class="cancelled">CANCELLED — {{ $receipt->cancelled_reason }}</p>
    @endif
</body>
</html>
