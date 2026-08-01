<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReceiptPdfController extends Controller
{
    public function show(Receipt $receipt): Response
    {
        $receipt->load(['laundryOrder.packages.items.clothingType', 'laundryOrder.packages.package', 'customer', 'payment']);

        $pdf = Pdf::loadView('pdfs.receipt', ['receipt' => $receipt]);

        return $pdf->stream("{$receipt->receipt_number}.pdf");
    }
}
