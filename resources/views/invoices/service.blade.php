<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $title }} – {{ $invoice_no }}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 11px; color: #1f2937; padding: 24px 28px; max-width: 800px; margin: 0 auto; }
.print-bar { text-align: right; margin-bottom: 12px; }
.print-bar button { background: #ff6b35; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-weight: bold; cursor: pointer; }
@media print { .print-bar { display: none; } }
table { width: 100%; border-collapse: collapse; }
.head td { vertical-align: middle; border: 0; padding: 0; }
.brand { font-size: 18px; font-weight: bold; color: #e65100; }
.muted { color: #6b7280; }
.title { text-align: right; font-size: 20px; font-weight: bold; color: #2e7d32; }
.bar { height: 3px; background: #ff6b35; margin: 12px 0 16px; }
.box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; }
.box h4 { font-size: 10px; text-transform: uppercase; color: #6b7280; margin-bottom: 5px; letter-spacing: .5px; }
.two td { vertical-align: top; border: 0; padding: 0; width: 50%; }
.two td.l { padding-right: 8px; } .two td.r { padding-left: 8px; }
.items { margin-top: 16px; }
.items th { background: #fff3ed; color: #9a3412; text-align: left; font-size: 10px; padding: 7px 8px; border: 1px solid #fed7aa; }
.items td { padding: 7px 8px; border: 1px solid #e5e7eb; }
.r-align { text-align: right; }
.total td { font-weight: bold; font-size: 13px; background: #f0fdf4; border: 1px solid #bbf7d0; }
.details { margin-top: 16px; }
.details td { padding: 4px 0; vertical-align: top; border: 0; }
.details td.k { width: 120px; color: #6b7280; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; background: #dcfce7; color: #166534; }
.foot { margin-top: 28px; text-align: center; color: #9ca3af; font-size: 9px; }
</style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button></div>
<table class="head">
    <tr>
        <td>
            @if ($logo_src)<img src="{{ $logo_src }}" style="height:38px;margin-bottom:4px;"><br>@endif
            <span class="brand">{{ $company_name }}</span><br>
            @if ($company_address)<span class="muted">{{ $company_address }}</span><br>@endif
            @if ($company_gstin)<span class="muted">GSTIN: {{ $company_gstin }}</span>@endif
        </td>
        <td class="title">{{ $title }}<br>
            <span class="muted" style="font-size:10px;font-weight:normal;">{{ $invoice_no }}</span>
        </td>
    </tr>
</table>
<div class="bar"></div>

<table class="two">
    <tr>
        <td class="l">
            <div class="box">
                <h4>Billed to</h4>
                <b>{{ $bill_to_name }}</b><br>
                @if ($bill_to_mobile)<span>{{ $bill_to_mobile }}</span><br>@endif
                <span class="muted">{{ $bill_to_address }}</span>
            </div>
        </td>
        <td class="r">
            <div class="box">
                <h4>Invoice details</h4>
                <table style="width:100%;">
                    <tr><td class="muted" style="border:0;padding:1px 0;">Invoice date</td><td class="r-align" style="border:0;padding:1px 0;">{{ $invoice_date }}</td></tr>
                    <tr><td class="muted" style="border:0;padding:1px 0;">Booking no.</td><td class="r-align" style="border:0;padding:1px 0;">{{ $booking_number }}</td></tr>
                    <tr><td class="muted" style="border:0;padding:1px 0;">Booked on</td><td class="r-align" style="border:0;padding:1px 0;">{{ $booked_on }}</td></tr>
                    <tr><td class="muted" style="border:0;padding:1px 0;">Payment</td><td class="r-align" style="border:0;padding:1px 0;"><span class="badge">{{ strtoupper($payment_status) }}</span></td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr><th style="width:36px;">#</th><th>Description</th><th class="r-align" style="width:110px;">Amount (INR)</th></tr>
    </thead>
    <tbody>
        @foreach($lines as $i => $line)
            <tr><td>{{ $i + 1 }}</td><td>{{ $line['description'] }}</td><td class="r-align">{{ number_format($line['amount'], 2) }}</td></tr>
        @endforeach
        <tr class="total"><td colspan="2" class="r-align">Total paid</td><td class="r-align">{{ number_format($total, 2) }}</td></tr>
    </tbody>
</table>

<table class="details">
    @foreach($details as $d)
        <tr><td class="k">{{ $d['label'] }}</td><td>{{ $d['value'] }}</td></tr>
    @endforeach
    @if ($payment_method)<tr><td class="k">Payment method</td><td>{{ strtoupper($payment_method) }}</td></tr>@endif
    @if ($transaction_id)<tr><td class="k">Transaction ID</td><td>{{ $transaction_id }}</td></tr>@endif
    @if ($paid_at)<tr><td class="k">Paid at</td><td>{{ $paid_at }}</td></tr>@endif
</table>

<div class="foot">This is a computer generated invoice and does not require a signature.</div>
</body>
</html>
