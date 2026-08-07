{{--
    Standalone, self-contained page — no @extends, no @vite() build
    dependency, no session/CSRF (this route carries neither, §7.37). This
    platform has no customer storefront, so this is deliberately the
    entire "thank you" experience for now — a future storefront can
    redirect here or replace it entirely; nothing else depends on this
    view's own markup.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment confirmed</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f9fafb; color: #111827; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2.5rem; max-width: 28rem; text-align: center; }
        .icon { font-size: 2.5rem; margin-bottom: 1rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { color: #6b7280; margin: 0 0 .25rem; }
        .order { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; font-size: .875rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#9989;</div>
        <h1>Payment confirmed</h1>
        <p>Your payment was received and your order has been placed.</p>
        @if ($order)
            @php
                // IRR (and other zero-decimal currencies) have no minor
                // unit — Money's own "amount is always /100" display
                // convention (Money.php's own docblock) doesn't hold for
                // them. A real, visible correctness issue for exactly
                // this feature's own primary Zibal/IRR use case if left
                // unhandled — not fixed platform-wide (that's the
                // pre-existing, documented gap), just handled honestly
                // right here, where a real buyer actually looks at it.
                $zeroDecimalCurrencies = ['IRR', 'JPY', 'KRW'];
                $isZeroDecimal = in_array($order->totalCurrency, $zeroDecimalCurrencies, true);
                $displayAmount = $isZeroDecimal
                    ? number_format($order->totalAmount)
                    : number_format($order->totalAmount / 100, 2);
            @endphp
            <div class="order">
                <div>Order <strong>{{ $order->orderNumber }}</strong></div>
                <div>{{ $displayAmount }} {{ $order->totalCurrency }}</div>
            </div>
        @endif
        <p style="margin-top:1.5rem;font-size:.75rem;color:#9ca3af;">You may now close this window.</p>
    </div>
</body>
</html>
