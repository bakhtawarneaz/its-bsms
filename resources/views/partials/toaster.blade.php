@php
    $flashToasts = [];

    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (session()->has($type)) {
            $flashToasts[] = ['type' => $type, 'message' => session($type)];
        }
    }

    if (session()->has('status')) {
        $flashToasts[] = ['type' => 'success', 'message' => session('status')];
    }

    foreach (array_unique($errors->all()) as $message) {
        $flashToasts[] = ['type' => 'error', 'message' => $message];
    }
@endphp

<script>
    window.__flashToasts = @json($flashToasts);
</script>

<div
    x-data
    class="pointer-events-none fixed inset-x-0 top-0 z-50 flex flex-col items-center gap-3 p-4 sm:items-end sm:p-6"
    role="status"
    aria-live="polite"
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            x-data="{ shown: false }"
            x-init="$nextTick(() => shown = true)"
            x-show="shown && toast.visible"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="opacity-0 -translate-y-2 sm:translate-y-0 sm:translate-x-8"
            x-transition:enter-end="opacity-100 translate-x-0 translate-y-0"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-xl border border-line bg-white shadow-lg shadow-navy-950/10"
        >
            <div class="flex items-start gap-3 p-4">
                <span
                    class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full"
                    :class="{
                        'bg-success/10 text-success': toast.type === 'success',
                        'bg-danger/10 text-danger': toast.type === 'error',
                        'bg-warning/10 text-warning': toast.type === 'warning',
                        'bg-info/10 text-info': toast.type === 'info',
                    }"
                >
                    <svg x-show="toast.type === 'success'" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    <svg x-show="toast.type === 'error'" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16ZM9 6a1 1 0 1 1 2 0v4a1 1 0 1 1-2 0V6Zm1 8.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z" clip-rule="evenodd"/></svg>
                    <svg x-show="toast.type === 'warning'" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.3 2.9a2 2 0 0 1 3.4 0l6.1 10.6A2 2 0 0 1 16.1 16.5H3.9a2 2 0 0 1-1.7-3L8.3 2.9ZM9 7a1 1 0 1 1 2 0v3a1 1 0 1 1-2 0V7Zm1 7a1.1 1.1 0 1 0 0-2.2A1.1 1.1 0 0 0 10 14Z" clip-rule="evenodd"/></svg>
                    <svg x-show="toast.type === 'info'" class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm1 12a1 1 0 1 1-2 0V9a1 1 0 1 1 2 0v5Zm-1-7a1.1 1.1 0 1 0 0-2.2A1.1 1.1 0 0 0 10 7Z" clip-rule="evenodd"/></svg>
                </span>

                <p class="flex-1 pt-0.5 text-sm leading-relaxed text-ink" x-text="toast.message"></p>

                <button
                    type="button"
                    class="-m-1 rounded-md p-1 text-muted transition hover:bg-surface hover:text-ink focus-visible:ring-2 focus-visible:ring-navy-700/30 focus-visible:outline-none"
                    @click="$store.toasts.dismiss(toast.id)"
                    aria-label="Dismiss notification"
                >
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/></svg>
                </button>
            </div>

            <div
                x-show="toast.timeout > 0"
                class="toast-progress absolute inset-x-0 bottom-0 h-0.5"
                :class="{
                    'bg-success': toast.type === 'success',
                    'bg-danger': toast.type === 'error',
                    'bg-warning': toast.type === 'warning',
                    'bg-info': toast.type === 'info',
                }"
                :style="`animation-duration: ${toast.timeout}ms`"
            ></div>
        </div>
    </template>
</div>