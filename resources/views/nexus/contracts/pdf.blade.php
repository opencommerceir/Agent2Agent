@php
    $terms = $contract->terms();
    $priceDisplay = number_format($terms['priceAmount'] / 100, 2).' '.$terms['priceCurrency'];
    $agreedAt = \Illuminate\Support\Carbon::parse($terms['agreedAt'])->format('Y-m-d H:i');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 4px 6px; vertical-align: top; }
        .label { width: 35%; color: #444; }
        .rtl { direction: rtl; text-align: right; }
        .ltr { direction: ltr; text-align: left; }
        .hash { font-size: 9px; color: #666; word-break: break-all; margin-top: 24px; }
    </style>
</head>
<body>
    <h1>Nexus — Bilingual Contract / قرارداد دوزبانه</h1>
    <p style="text-align: center; color: #666;">Contract #{{ $contract->id() }} — Negotiation #{{ $terms['negotiationId'] }}</p>

    <div class="ltr">
        <h2>English</h2>
        <table>
            <tr><td class="label">First Party</td><td>{{ $terms['initiator']['nameEn'] }}</td></tr>
            <tr><td class="label">Second Party</td><td>{{ $terms['counterparty']['nameEn'] }}</td></tr>
            <tr><td class="label">Item</td><td>{{ ucfirst($terms['catalogItemType']) }} #{{ $terms['catalogItemId'] }}</td></tr>
            <tr><td class="label">Quantity</td><td>{{ $terms['quantity'] }}</td></tr>
            <tr><td class="label">Price</td><td>{{ $priceDisplay }}</td></tr>
            <tr><td class="label">Agreed At</td><td>{{ $agreedAt }}</td></tr>
        </table>
        <p>Both parties agree to the terms above, as concluded through their respective Nexus Agents.</p>
    </div>

    <div class="rtl">
        <h2>فارسی</h2>
        <table>
            <tr><td class="label">طرف اول</td><td>{{ $terms['initiator']['nameFa'] }}</td></tr>
            <tr><td class="label">طرف دوم</td><td>{{ $terms['counterparty']['nameFa'] }}</td></tr>
            <tr><td class="label">کالا/خدمت</td><td>{{ $terms['catalogItemType'] === 'product' ? 'محصول' : 'خدمت' }} #{{ $terms['catalogItemId'] }}</td></tr>
            <tr><td class="label">تعداد</td><td>{{ $terms['quantity'] }}</td></tr>
            <tr><td class="label">مبلغ</td><td>{{ $priceDisplay }}</td></tr>
            <tr><td class="label">تاریخ توافق</td><td>{{ $agreedAt }}</td></tr>
        </table>
        <p>هر دو طرف با شرایط بالا، از طریق Agent‌های نمایندهٔ خود در Nexus، موافقت کرده‌اند.</p>
    </div>

    <p class="hash">SHA-256: {{ $contract->contentHash() }}</p>
</body>
</html>
