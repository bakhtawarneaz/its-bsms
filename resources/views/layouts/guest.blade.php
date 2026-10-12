<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-ink antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

        {{-- Brand panel --}}
        <aside class="relative hidden flex-col justify-between overflow-hidden bg-navy-900 p-12 text-white lg:flex xl:p-16">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-lg bg-brand text-navy-900">
                    <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.4 3.5A1 1 0 0 1 4 18.7V5.5Z"/></svg>
                </span>
                <span class="font-display text-lg font-semibold">{{ config('app.name') }}</span>
            </div>

            <div class="max-w-md">
                <h2 class="font-display text-[40px] leading-[1.1] font-semibold tracking-tight">
                    Reach every network in Pakistan from one portal.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-white/70">
                    Send campaigns, OTPs and alerts, then follow every message until it lands on Jazz, Zong, Ufone and Telenor.
                </p>

                @php
                    $feed = [
                        ['operator' => 'Jazz',    'text' => 'Your order #48213 is out for delivery.'],
                        ['operator' => 'Zong',    'text' => 'Your login code is valid for 5 minutes.'],
                        ['operator' => 'Ufone',   'text' => 'Payment of Rs 2,450 received. Thank you.'],
                        ['operator' => 'Telenor', 'text' => 'Your appointment is confirmed for Monday.'],
                    ];
                @endphp

                <ul class="mt-10 space-y-2.5" aria-hidden="true">
                    @foreach ($feed as $item)
                        <li class="feed-item flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3" style="--delay: {{ 0.2 + $loop->index * 0.35 }}s">
                            <span class="w-16 shrink-0 text-xs font-semibold text-brand">{{ $item['operator'] }}</span>
                            <span class="flex-1 truncate text-sm text-white/80">{{ $item['text'] }}</span>
                            <span class="feed-status inline-flex items-center gap-1 text-xs font-medium text-emerald-300">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                Delivered
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="text-sm text-white/50">&copy; {{ date('Y') }} Intellexal Solutions</p>
        </aside>

        {{-- Form panel --}}
        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-[400px]">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand text-navy-900">
                        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.4 3.5A1 1 0 0 1 4 18.7V5.5Z"/></svg>
                    </span>
                    <span class="font-display text-lg font-semibold">{{ config('app.name') }}</span>
                </div>

                <h1 class="font-display text-[28px] leading-tight font-semibold tracking-tight">@yield('heading')</h1>
                <p class="mt-2 text-[15px] leading-relaxed text-muted">@yield('subheading')</p>

                <div class="mt-8">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    @include('partials.toaster')
</body>
</html>