const MOBILE = /^(00923|009203|09203|9203|923|03|3)([0-7]\d{8})$/;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function registerProfile(Alpine) {
    /**
     * Chip-style input for comma-separated lists (mobile numbers or emails).
     * The hidden input keeps the comma-separated value the server expects.
     */
    Alpine.data('tagInput', ({ values, type, max, hint, placeholder }) => ({
        items: [...values],
        draft: '',
        error: '',
        hint,
        placeholder,

        normalize(raw) {
            const value = raw.trim();

            if (type === 'mobile') {
                const match = value.replace(/[\s\-.+()'";/\\]/g, '').match(MOBILE);
                return match ? `923${match[2]}` : false;
            }

            return EMAIL.test(value) ? value.toLowerCase() : false;
        },

        display(item) {
            return type === 'mobile' ? `+92 ${item.slice(2, 5)} ${item.slice(5)}` : item;
        },

        add() {
            const parts = this.draft.split(/[,;\s]+/).filter(Boolean);

            if (!parts.length) {
                return;
            }

            const rejected = [];
            this.error = '';

            for (const part of parts) {
                const value = this.normalize(part);

                if (value === false) {
                    rejected.push(part);
                    continue;
                }

                if (this.items.includes(value)) {
                    continue;
                }

                if (this.items.length >= max) {
                    this.error = `You can add up to ${max}.`;
                    break;
                }

                this.items.push(value);
                this.$dispatch('input');
            }

            if (rejected.length) {
                this.error = `Not valid: ${rejected.join(', ')}`;
            }

            // Keep invalid entries in the field so they can be corrected
            this.draft = rejected.join(', ');
        },

        remove(index) {
            this.items.splice(index, 1);
            this.error = '';
            this.$dispatch('input');
        },

        onKeydown(event) {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                this.add();
            } else if (event.key === 'Backspace' && this.draft === '' && this.items.length) {
                this.remove(this.items.length - 1);
            }
        },

        get value() {
            return this.items.join(',');
        },
    }));

    /**
     * Password form with a live strength meter.
     */
    Alpine.data('passwordForm', () => ({
        loading: false,
        reveal: false,
        password: '',

        get checks() {
            const p = this.password;

            return [
                { label: 'At least 8 characters', ok: p.length >= 8 },
                { label: 'Upper and lower case letters', ok: /[a-z]/.test(p) && /[A-Z]/.test(p) },
                { label: 'A number', ok: /\d/.test(p) },
                { label: 'A symbol', ok: /[^A-Za-z0-9]/.test(p) },
            ];
        },

        get score() {
            return this.checks.filter((check) => check.ok).length;
        },

        get strength() {
            return ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'][this.score];
        },

        get color() {
            return ['#d03b3b', '#d03b3b', '#fab219', '#2a78d6', '#0ca30c'][this.score];
        },
    }));
}