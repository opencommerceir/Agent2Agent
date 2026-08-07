{{-- See payments/confirmed.blade.php's own top comment — same standalone shape. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment not completed</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f9fafb; color: #111827; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 2.5rem; max-width: 28rem; text-align: center; }
        .icon { font-size: 2.5rem; margin-bottom: 1rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { color: #6b7280; margin: 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#10060;</div>
        <h1>Payment not completed</h1>
        <p>{{ $message ?? 'Something went wrong confirming your payment.' }}</p>
    </div>
</body>
</html>
