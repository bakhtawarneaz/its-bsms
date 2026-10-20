<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-sans text-ink antialiased" x-data="{ sidebarOpen: false }">
    @php
        $displayName = $authUser['fullname'] ?: $authUser['username'];
        $initials = collect(preg_split('/\s+/', trim($displayName)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    @endphp

    {{-- Mobile backdrop --}}
    <div
        x-show="sidebarOpen" x-cloak
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-30 bg-navy-950/50 lg:hidden"
        @click="sidebarOpen = false"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-navy-900 text-white transition-transform duration-300 ease-out lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-16 shrink-0 items-center gap-3 px-5">
            <span class="grid size-8 place-items-center rounded-lg bg-brand text-navy-900">
                <svg class="size-4.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.4 3.5A1 1 0 0 1 4 18.7V5.5Z"/></svg>
            </span>
            <span class="font-display text-base font-semibold">{{ config('app.name') }}</span>
        </div>

        <nav class="sidebar-scroll flex-1 space-y-5 overflow-y-auto px-3 pt-2 pb-6" aria-label="Main">
            @foreach ($navigation as $section)
                <div>
                    <p class="px-3 pb-2 text-xs font-medium text-white/40">{{ $section['label'] }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            <li>
                                @if ($item['url'])
                                    <a
                                        href="{{ $item['url'] }}"
                                        @class([
                                            'group relative flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm transition',
                                            'bg-white/10 font-medium text-white' => $item['active'],
                                            'text-white/70 hover:bg-white/5 hover:text-white' => ! $item['active'],
                                        ])
                                        @if ($item['active']) aria-current="page" @endif
                                    >
                                        @if ($item['active'])
                                            <span class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-brand"></span>
                                        @endif
                                        <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="size-5 shrink-0" />
                                        <span class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                @else
                                    <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-1.5 text-sm text-white/35">
                                        <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="size-5 shrink-0" />
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[11px] text-white/50">Soon</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- Main column --}}
    <div class="flex min-h-screen flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button
                type="button"
                class="-ml-1 rounded-lg p-2 text-muted transition hover:bg-surface hover:text-ink lg:hidden"
                @click="sidebarOpen = true"
                aria-label="Open navigation"
            >
                <x-heroicon-o-bars-3 class="size-6" />
            </button>

            <h1 class="min-w-0 flex-1 truncate font-display text-lg font-semibold">@yield('title')</h1>

            {{-- User menu --}}
            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button
                    type="button"
                    class="flex items-center gap-3 rounded-lg py-1.5 pr-2 pl-1.5 transition hover:bg-surface"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                >
                    <span class="grid size-9 place-items-center rounded-full bg-brand text-sm font-semibold text-navy-900">{{ strtoupper($initials) }}</span>
                    <span class="hidden text-left sm:block">
                        <span class="block max-w-40 truncate text-sm font-semibold">{{ $displayName }}</span>
                        <span class="block text-xs text-muted">{{ $userType->label() }}</span>
                    </span>
                    <x-heroicon-m-chevron-down class="hidden size-4 text-muted sm:block" />
                </button>

                <div
                    x-show="open" x-cloak
                    @click.outside="open = false"
                    x-transition:enter="transition duration-150 ease-out"
                    x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition duration-100 ease-in"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-line bg-white p-1.5 shadow-lg shadow-navy-950/10"
                    role="menu"
                >
                    <div class="px-3 py-2.5">
                        <p class="truncate text-sm font-semibold">{{ $displayName }}</p>
                        <p class="truncate text-xs text-muted">{{ explode(',', $authUser['email'])[0] }}</p>
                    </div>
                    <div class="my-1 border-t border-line"></div>
                    <a href="{{ route('profile.edit') }}" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-surface" role="menuitem">
                        <x-heroicon-o-user-circle class="size-5 text-muted" />
                        My profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-surface" role="menuitem">
                            <x-heroicon-o-arrow-right-start-on-rectangle class="size-5 text-muted" />
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>

    @include('partials.toaster')
</body>
</html>