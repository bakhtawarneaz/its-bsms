<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: grid; place-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 32px; border-radius: 10px; width: 100%; max-width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        h1 { font-size: 20px; margin: 0 0 20px; }
        label { display: block; font-size: 14px; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px; margin-bottom: 16px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 11px; background: #f5c400; border: 0; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #dcfce7; color: #166534; }
        .link { display: block; text-align: center; margin-top: 14px; font-size: 14px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="card">
        <h1>@yield('heading', config('app.name'))</h1>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>