{{-- Expects the parent form to define Alpine state: x-data="{ loading: false }" --}}
<button type="submit" class="btn-primary" :disabled="loading">
    <svg x-show="loading" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
    </svg>
    <span x-show="!loading">{{ $label }}</span>
    <span x-show="loading" x-cloak>{{ $loadingLabel ?? 'Please wait…' }}</span>
</button>