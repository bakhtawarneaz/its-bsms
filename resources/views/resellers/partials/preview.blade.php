{{-- Live portal preview. Needs the same Alpine scope as the branding fields. --}}
<section class="rounded-2xl border border-line bg-white p-5">
    <h3 class="font-semibold">Portal preview</h3>
    <p class="mt-0.5 truncate text-sm text-muted" x-text="domain ? 'https://' + domain : 'Your branding updates live'"></p>

    <div class="mt-4 overflow-hidden rounded-xl border border-line shadow-sm">
        <div class="flex items-center gap-2.5 px-3 py-2.5" :style="`background:${colors.first}; color:${colors.second}`">
            <span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-md bg-white/20">
                <img x-show="logo" x-cloak :src="logo" alt="" class="size-full object-contain">
                <span x-show="!logo" class="text-xs font-bold" x-text="(company || 'R').slice(0, 1).toUpperCase()"></span>
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold" x-text="company || 'Company name'"></p>
                <p class="truncate text-[11px] opacity-80" x-text="slogan || 'Company slogan'"></p>
            </div>
        </div>
        <div class="flex h-36" :class="dark ? 'bg-navy-950' : 'bg-surface'">
            <div class="w-14 space-y-2 p-2.5" :class="dark ? 'bg-black/30' : 'bg-white border-r border-line'">
                <span class="block h-1.5 rounded-full" :class="dark ? 'bg-white/30' : 'bg-line'"></span>
                <span class="block h-1.5 rounded-full" :class="dark ? 'bg-white/20' : 'bg-line'"></span>
                <span class="block h-1.5 rounded-full" :class="dark ? 'bg-white/20' : 'bg-line'"></span>
                <span class="block h-1.5 w-2/3 rounded-full" :class="dark ? 'bg-white/20' : 'bg-line'"></span>
            </div>
            <div class="flex-1 space-y-2 p-3">
                <span class="block h-2 w-1/2 rounded-full" :class="dark ? 'bg-white/30' : 'bg-line'"></span>
                <span class="block h-10 rounded-lg" :class="dark ? 'bg-white/5' : 'bg-white border border-line'"></span>
                <span class="inline-flex rounded-md px-2.5 py-1 text-[11px] font-semibold" :style="`background:${colors.third}; color:${colors.fourth}`">Send SMS</span>
            </div>
        </div>
    </div>
</section>