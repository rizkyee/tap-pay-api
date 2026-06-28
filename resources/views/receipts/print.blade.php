<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt['title'] ?? 'Receipt' }} - {{ $receipt['transaction_code'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; background: #f3f4f6; margin: 0; padding: 24px; }
        .receipt { max-width: 360px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .brand { text-align: center; font-size: 22px; font-weight: bold; letter-spacing: 2px; color: #4f46e5; }
        .title { text-align: center; margin: 4px 0 16px; color: #374151; font-size: 13px; text-transform: uppercase; }
        .divider { border: none; border-top: 1px dashed #cbd5e1; margin: 12px 0; }
        .row { display: flex; justify-content: space-between; font-size: 13px; margin: 6px 0; }
        .row .label { color: #6b7280; }
        .row .value { color: #111827; font-weight: 600; text-align: right; }
        .total { font-size: 16px; }
        .status { text-align: center; margin-top: 12px; padding: 6px; border-radius: 6px; background: #dcfce7; color: #166534; font-weight: bold; text-transform: uppercase; font-size: 12px; }
        .hash { word-break: break-all; font-size: 10px; color: #9ca3af; margin-top: 12px; text-align: center; }
        .actions { text-align: center; margin-top: 16px; }
        button { background: #4f46e5; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .receipt { box-shadow: none; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="brand">TapPay</div>
        <div class="title">{{ $receipt['title'] ?? 'Bukti Transaksi' }}</div>
        <hr class="divider">

        <div class="row"><span class="label">Kode</span><span class="value">{{ $receipt['transaction_code'] }}</span></div>
        <div class="row"><span class="label">Ref</span><span class="value">{{ $receipt['reference_number'] }}</span></div>
        <div class="row"><span class="label">Tanggal</span><span class="value">{{ $receipt['completed_at'] ?? $receipt['date'] }}</span></div>

        <hr class="divider">

        <div class="row"><span class="label">Pengirim</span><span class="value">{{ $receipt['sender_name'] ?? '-' }}</span></div>
        <div class="row"><span class="label">Penerima</span><span class="value">{{ $receipt['receiver_name'] ?? '-' }}</span></div>

        <hr class="divider">

        <div class="row"><span class="label">Nominal</span><span class="value">{{ number_format($receipt['amount'], 2) }}</span></div>
        <div class="row"><span class="label">Fee</span><span class="value">{{ number_format($receipt['fee'], 2) }}</span></div>
        <div class="row total"><span class="label">Total</span><span class="value">{{ number_format($receipt['total'], 2) }}</span></div>

        @if(!empty($receipt['notes']))
            <hr class="divider">
            <div class="row"><span class="label">Catatan</span><span class="value">{{ $receipt['notes'] }}</span></div>
        @endif

        <div class="status">{{ $receipt['status'] }}</div>
        <div class="hash">Verify: {{ $receipt['receipt_hash'] }}</div>

        <div class="actions">
            <button onclick="window.print()">Cetak / Print</button>
        </div>
    </div>
</body>
</html>
