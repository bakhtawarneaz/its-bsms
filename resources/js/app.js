import './bootstrap';
import Alpine from 'alpinejs';
import { registerCharts } from './charts';
import { registerProfile } from './profile';
import { registerForms } from './forms';

/**
 * Global toast store. Use from anywhere:
 *   window.toast.success('Saved'), window.toast.error('Something failed')
 */
Alpine.store('toasts', {
    items: [],
    nextId: 1,

    push(type, message, timeout = 5000) {
        const id = this.nextId++;
        this.items.push({ id, type, message, timeout, visible: true });

        if (timeout > 0) {
            setTimeout(() => this.dismiss(id), timeout);
        }
    },

    dismiss(id) {
        const toast = this.items.find((item) => item.id === id);

        if (!toast) {
            return;
        }

        // Let the leave transition finish before removing the element
        toast.visible = false;
        setTimeout(() => {
            this.items = this.items.filter((item) => item.id !== id);
        }, 250);
    },
});

/**
 * Countdown to a Unix timestamp (seconds), shown as m:ss.
 */
Alpine.data('countdown', (expiresAt) => ({
    remaining: 0,

    init() {
        const tick = () => {
            this.remaining = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
        };

        tick();
        const timer = setInterval(() => {
            tick();
            if (this.remaining === 0) {
                clearInterval(timer);
            }
        }, 1000);
    },

    get label() {
        const minutes = Math.floor(this.remaining / 60);
        const seconds = String(this.remaining % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },
}));

window.Alpine = Alpine;
window.toast = {
    success: (message) => Alpine.store('toasts').push('success', message),
    error: (message) => Alpine.store('toasts').push('error', message, 7000),
    info: (message) => Alpine.store('toasts').push('info', message),
    warning: (message) => Alpine.store('toasts').push('warning', message, 6000),
};

registerCharts(Alpine);
registerProfile(Alpine);
registerForms(Alpine);

Alpine.start();

// Show server-side flash messages after Alpine is ready so they animate in
requestAnimationFrame(() => {
    (window.__flashToasts || []).forEach(({ type, message }) => window.toast[type]?.(message));
});