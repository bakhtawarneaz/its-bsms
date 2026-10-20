<!-- {{-- Network route pickers. Needs $networks, $routeOptions and an Alpine scope with routes + setAll(). --}}
<section class="rounded-2xl border border-line bg-white">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-6 py-4">
        <div>
            <h3 class="font-semibold">Routes</h3>
            <p class="mt-0.5 text-sm text-muted">Route used on each mobile network</p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:border-navy-700/40 hover:text-ink" @click="setAll(100)">All via ITS Gateway</button>
            <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:border-danger/40 hover:text-danger" @click="setAll(0)">Block all</button>
        </div>
    </header>

    <div class="divide-y divide-line">
        @foreach ($networks as $key => $network)
            <div class="grid gap-3 px-6 py-4 sm:grid-cols-5 sm:items-center">
                <div class="sm:col-span-2">
                    <p class="flex items-center gap-2 font-medium">
                        <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                        {{ $network['label'] }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted">{{ $network['hint'] }}</p>
                </div>
                <div class="sm:col-span-3">
                    <label for="route-{{ $key }}" class="sr-only">{{ $network['label'] }} route</label>
                    <select id="route-{{ $key }}" name="routes[{{ $key }}]" x-model="routes.{{ $key }}" class="field-input"
                            :class="routes.{{ $key }} === '0' ? 'text-danger' : ''" @error("routes.{$key}") aria-invalid="true" @enderror>
                        @foreach ($routeOptions[$key] as $option)
                            <option value="{{ $option['value'] }}">
                                {{ $option['value'] === 0 ? '(0) Block' : '(' . $option['value'] . ') ' . $option['operator'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endforeach
    </div>
</section> -->

{{-- Network route pickers. Needs $networks, $routeOptions and an Alpine scope with routes + setAll(). --}}
<section class="rounded-2xl border border-line bg-white">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-6 py-4">
        <div>
            <h3 class="font-semibold">Routes</h3>
            <p class="mt-0.5 text-sm text-muted">
                Route used on each mobile network ·
                <span class="font-medium text-ink" x-text="Object.values(routes).filter(v => v !== '').length + ' of {{ count($networks) }} set'"></span>
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:border-navy-700/40 hover:text-ink" @click="setAll(100)">All via ITS Gateway</button>
            <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition hover:border-danger/40 hover:text-danger" @click="setAll(0)">Block all</button>
        </div>
    </header>

    <div class="divide-y divide-line">
        @foreach ($networks as $key => $network)
            <div class="grid gap-3 px-6 py-4 transition-colors sm:grid-cols-5 sm:items-center"
                 :class="routes.{{ $key }} === '' ? '' : (routes.{{ $key }} === '0' ? 'bg-rose-50/60' : 'bg-emerald-50/50')">
                <div class="sm:col-span-2">
                    <p class="flex items-center gap-2 font-medium">
                        <span class="size-2.5 rounded-full {{ $network['dot'] }}"></span>
                        {{ $network['label'] }}
                        <span x-show="routes.{{ $key }} !== ''" x-cloak
                              class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                              :class="routes.{{ $key }} === '0' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                              x-text="routes.{{ $key }} === '0' ? 'Blocked' : 'Set'"></span>
                    </p>
                    <p class="mt-0.5 text-xs text-muted">{{ $network['hint'] }}</p>
                </div>
                <div class="flex items-center gap-2 sm:col-span-3">
                    <label for="route-{{ $key }}" class="sr-only">{{ $network['label'] }} route</label>
                    <select id="route-{{ $key }}" name="routes[{{ $key }}]" x-model="routes.{{ $key }}"
                            class="field-input bg-white" :class="routes.{{ $key }} === '' ? 'text-muted' : (routes.{{ $key }} === '0' ? 'text-danger' : 'text-ink')"
                            @error("routes.{$key}") aria-invalid="true" @enderror>
                        <option value="">No route</option>
                        @foreach ($routeOptions[$key] as $option)
                            <option value="{{ $option['value'] }}">
                                {{ $option['value'] === 0 ? '(0) Block' : '(' . $option['value'] . ') ' . $option['operator'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" x-show="routes.{{ $key }} !== ''" x-cloak @click="routes.{{ $key }} = ''"
                            class="grid size-10 shrink-0 place-items-center rounded-lg text-muted transition hover:bg-surface hover:text-ink"
                            title="Clear {{ $network['label'] }} route">
                        <x-heroicon-m-x-mark class="size-4" />
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</section>