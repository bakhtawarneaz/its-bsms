{{--
    Branding inputs shared by create and edit.
    Needs: $domainEditable (bool), $currentDomain (?string), $logoRequired (bool)
    and an Alpine scope with company, slogan, domain, colors, dark, logo, logoName, pickLogo().
--}}
@php
    $colorFields = [
        'first'  => ['first_color', 'Primary color', 'Header background'],
        'second' => ['second_color', 'Primary text', 'Text on the header'],
        'third'  => ['third_color', 'Secondary color', 'Buttons and highlights'],
        'fourth' => ['fourth_color', 'Secondary text', 'Text on buttons'],
    ];
@endphp

<div class="grid gap-5 p-6 sm:grid-cols-2">
    <div>
        <label for="company_name" class="field-label">Company name</label>
        <input id="company_name" name="company_name" type="text" x-model="company" class="field-input" required maxlength="100" @error('company_name') aria-invalid="true" @enderror>
    </div>

    <div>
        <label for="domain" class="field-label">SMS domain</label>
        @if ($domainEditable)
            <div class="flex overflow-hidden rounded-lg border border-line bg-white focus-within:border-navy-700 focus-within:ring-4 focus-within:ring-navy-700/10" @error('domain') aria-invalid="true" @enderror>
                <span class="grid shrink-0 place-items-center border-r border-line bg-surface px-3 text-sm text-muted">https://</span>
                <input id="domain" name="domain" type="text" x-model="domain" placeholder="sms.company.com" required maxlength="150"
                       class="min-w-0 flex-1 border-0 bg-transparent px-3.5 py-2.5 text-[15px] outline-none">
            </div>
            <p class="field-hint">The reseller and its users sign in from this domain.</p>
        @else
            <div class="flex overflow-hidden rounded-lg border border-line bg-surface">
                <span class="grid shrink-0 place-items-center border-r border-line px-3 text-sm text-muted">https://</span>
                <span class="flex min-w-0 flex-1 items-center gap-2 px-3.5 py-2.5 text-[15px] text-muted">
                    <span class="truncate">{{ $currentDomain ?: '—' }}</span>
                    <x-heroicon-o-lock-closed class="ml-auto size-4 shrink-0" />
                </span>
            </div>
            <p class="field-hint">The domain is linked to every user's sign-in and can't be changed.</p>
        @endif
    </div>

    <div class="sm:col-span-2">
        <label for="slogan" class="field-label">Slogan</label>
        <input id="slogan" name="slogan" type="text" x-model="slogan" class="field-input" required maxlength="200" @error('slogan') aria-invalid="true" @enderror>
    </div>

    {{-- Logo --}}
    <div class="sm:col-span-2">
        <span class="field-label">Logo</span>
        <label class="flex cursor-pointer items-center gap-4 rounded-xl border border-dashed border-line p-4 transition hover:border-navy-700/40 hover:bg-surface/50" @error('logo') aria-invalid="true" @enderror>
            <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-lg border border-line bg-white">
                <img x-show="logo" x-cloak :src="logo" alt="" class="size-full object-contain p-1">
                <x-heroicon-o-photo x-show="!logo" class="size-6 text-muted" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-medium text-ink" x-text="logoName || @js($logoRequired ? 'Upload PNG logo' : 'Replace logo (optional)')"></span>
                <span class="block text-xs text-muted">PNG only, up to 2 MB. A square image works best.</span>
            </span>
            <input type="file" name="logo" accept="image/png" class="sr-only" @if ($logoRequired) required @endif @change="pickLogo($event)">
        </label>
    </div>

    {{-- Colors --}}
    @foreach ($colorFields as $key => [$name, $label, $hint])
        <div>
            <label for="{{ $name }}" class="field-label">{{ $label }}</label>
            <div class="flex items-center gap-2">
                <input id="{{ $name }}" name="{{ $name }}" type="color" x-model="colors.{{ $key }}"
                       class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-line bg-white p-1">
                <input type="text" x-model="colors.{{ $key }}" maxlength="7" class="field-input font-mono text-sm uppercase" aria-label="{{ $label }} hex">
            </div>
            <p class="field-hint">{{ $hint }}</p>
        </div>
    @endforeach

    <div class="sm:col-span-2">
        <span class="field-label">Theme</span>
        <label class="inline-flex cursor-pointer items-center gap-3">
            <input type="checkbox" name="dark_theme" value="1" class="peer sr-only" x-model="dark">
            <span class="relative h-6 w-11 rounded-full bg-line transition peer-checked:bg-success peer-focus-visible:ring-4 peer-focus-visible:ring-navy-700/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
            <span class="text-sm text-muted">Dark sidebar</span>
        </label>
    </div>
</div>