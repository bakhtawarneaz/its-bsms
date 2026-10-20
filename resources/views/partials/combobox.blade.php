{{-- Searchable select. Params: $name, $options, $selected, $placeholder, $id --}}
<div class="relative" x-data="combobox(@js(['options' => $options, 'selected' => $selected]))" @click.outside="open = false" @keydown.escape="open = false">
    <input type="hidden" name="{{ $name }}" :value="selected">

    <button
        type="button" id="{{ $id }}"
        class="field-input flex items-center justify-between gap-2 text-left"
        @click="toggle()"
        :aria-expanded="open"
        @error($name) aria-invalid="true" @enderror
    >
        <span class="truncate" :class="selected ? 'text-ink' : 'text-muted/70'" x-text="selectedLabel || @js($placeholder)">{{ $placeholder }}</span>
        <x-heroicon-m-chevron-up-down class="size-5 shrink-0 text-muted" />
    </button>

    <div
        x-show="open" x-cloak
        x-transition:enter="transition duration-100 ease-out"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute z-30 mt-1.5 w-full overflow-hidden rounded-xl border border-line bg-white shadow-xl shadow-navy-950/10"
    >
        <div class="border-b border-line p-2">
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                <input
                    x-ref="search" x-model="query" type="text" placeholder="Search…"
                    class="w-full rounded-lg bg-surface py-2 pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-navy-700/20"
                    @input="active = 0"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="pick()"
                >
            </div>
        </div>

        <ul x-ref="list" class="max-h-64 overflow-y-auto p-1" role="listbox">
            <template x-for="(option, index) in filtered" :key="option.value">
                <li
                    role="option"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm"
                    :class="index === active ? 'bg-surface' : ''"
                    :aria-selected="String(option.value) === selected"
                    @mouseenter="active = index"
                    @click="choose(option)"
                >
                    <span class="truncate" x-text="option.label"></span>
                    <span x-show="String(option.value) === selected"><x-heroicon-m-check class="size-4 text-navy-900" /></span>
                </li>
            </template>
            <li x-show="!filtered.length" class="px-3 py-6 text-center text-sm text-muted">No matches</li>
        </ul>
    </div>
</div>