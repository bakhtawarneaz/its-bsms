<nav class="mb-6 flex gap-1 border-b border-line" aria-label="Templates">
    @foreach (['templates.index' => 'Templates', 'templates.otp' => 'OTP templates'] as $routeName => $label)
        @php $active = request()->routeIs($routeName); @endphp
        <a href="{{ route($routeName) }}" @class([
            'relative px-3 py-3 text-sm font-medium transition',
            'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-navy-900' => $active,
            'text-muted hover:text-ink' => ! $active,
        ])>{{ $label }}</a>
    @endforeach
</nav>