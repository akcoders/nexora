<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Nexora complaint QR · {{ $customer->name }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eef2f7;color:#14213d;font-family:Arial,sans-serif;line-height:1.5}.toolbar{max-width:600px;margin:24px auto;display:flex;justify-content:space-between;gap:12px;padding:0 16px}.toolbar a,.toolbar button{font:600 14px Arial,sans-serif;border:1px solid #cbd5e1;padding:10px 16px;text-decoration:none;cursor:pointer;background:white;color:#1e3a8a}.toolbar button{background:#1e40af;color:white;border-color:#1e40af}.qr-card{background:white;max-width:480px;margin:0 auto 32px;padding:36px;text-align:center;border-top:8px solid #1e40af;box-shadow:0 10px 35px #0f172a12}.brand{font-size:27px;letter-spacing:5px;font-weight:800;color:#1e3a8a}.overline{font-size:11px;letter-spacing:2px;color:#64748b;margin-top:5px}h1{font-size:28px;line-height:1.2;margin:28px 0 12px}.hint{font-size:15px;color:#64748b;margin:0}.qr{display:block;width:100%;max-width:300px;aspect-ratio:1;margin:16px auto;image-rendering:pixelated}.step{font-size:14px;color:#334155}.account{border-top:1px solid #e2e8f0;margin-top:24px;padding-top:18px;font-weight:600;overflow-wrap:anywhere}.account small{display:block;font-size:11px;font-weight:400;color:#64748b;margin-top:4px}.destination{font-size:10px;color:#64748b;overflow-wrap:anywhere;margin-top:14px}.preparing{max-width:480px;margin:0 auto 20px;padding:0 16px;font-size:13px;color:#64748b;text-align:center}@page{size:A5 portrait;margin:12mm}@media print{body{background:white}.toolbar,.preparing{display:none}.qr-card{max-width:none;margin:0;padding:14px;border:1px solid #dbe4f0;border-top:8px solid #1e40af;box-shadow:none;break-inside:avoid}.brand{font-size:22px;line-height:1.2;margin-top:0}.overline{font-size:10px;line-height:1.4;margin-top:3px}h1{font-size:24px;margin:18px 0 8px}.hint{font-size:13px}.qr{width:60mm;max-width:none;margin:12px auto}.step{font-size:12px}.account{margin-top:16px;padding-top:12px;font-size:14px}.account small{font-size:10px}.destination{font-size:9px;margin-top:10px}}
    </style>
</head>
<body>
    <div class="toolbar"><a href="{{ route('customers.show', $customer) }}">← Customer workspace</a><button type="button" onclick="window.print()">Print QR card</button></div>
    <main class="qr-card">
        <div class="brand">NEXORA</div><div class="overline">CUSTOMER SUPPORT</div>
        <h1>Need help with<br>your service?</h1><p class="hint">Scan to open the complaint page.</p>
        <img class="qr" src="{{ asset('images/brand/nexora-complaint-qr.png') }}" width="1128" height="1128" alt="Nexora customer complaint QR code">
        <div class="step">Open your phone camera and point it at this code.</div>
        <div class="account">{{ $customer->name }}<small>{{ $customer->code }}</small></div>
        <div class="destination">{{ config('nexora.customer_complaint_url') }}</div>
    </main>
    <p class="preparing">The complaint form is coming soon. This QR opens its reserved page and will keep working when the form is added.</p>
</body>
</html>
