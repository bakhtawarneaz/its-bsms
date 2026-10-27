import flatpickr from 'flatpickr';

const matches = (option, query) => option.label.toLowerCase().includes(query.trim().toLowerCase());

export function registerForms(Alpine) {
    /**
     * Searchable single select. Keeps the chosen value in a hidden input.
     */
    Alpine.data('combobox', ({ options, selected = null }) => ({
        options,
        selected: selected === null || selected === '' ? '' : String(selected),
        query: '',
        open: false,
        active: 0,

        get filtered() {
            const list = this.query ? this.options.filter((o) => matches(o, this.query)) : this.options;
            return list.slice(0, 100);
        },

        get selectedLabel() {
            return this.options.find((o) => String(o.value) === this.selected)?.label ?? '';
        },

        toggle() {
            this.open ? (this.open = false) : this.show();
        },

        show() {
            this.open = true;
            this.query = '';
            this.active = 0;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        choose(option) {
            this.selected = String(option.value);
            this.open = false;
            this.$dispatch('input');
        },

        move(step) {
            const count = this.filtered.length;
            if (!count) return;

            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.$refs.list?.children[this.active]?.scrollIntoView({ block: 'nearest' }));
        },

        pick() {
            const option = this.filtered[this.active];
            if (option) this.choose(option);
        },
    }));

    /**
     * Searchable multi select with chips. Renders one hidden input per value.
     */
    Alpine.data('multiSelect', ({ options, selected = [] }) => ({
        options,
        selected: selected.map(String),
        query: '',
        open: false,

        get filtered() {
            const list = this.query ? this.options.filter((o) => matches(o, this.query)) : this.options;
            return list.slice(0, 150);
        },

        isSelected(value) {
            return this.selected.includes(String(value));
        },

        toggleOption(option) {
            const value = String(option.value);
            this.selected = this.isSelected(value) ? this.selected.filter((v) => v !== value) : [...this.selected, value];
            this.$dispatch('input');
        },

        remove(value) {
            this.selected = this.selected.filter((v) => v !== value);
            this.$dispatch('input');
        },

        labelFor(value) {
            return this.options.find((o) => String(o.value) === value)?.label ?? value;
        },

        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.search?.focus());
        },
    }));

    /**
     * Styled single date picker.
     */
    Alpine.data('datePicker', ({ value, minDate }) => ({
        picker: null,

        init() {
            this.picker = flatpickr(this.$refs.input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                defaultDate: value || null,
                minDate,
                disableMobile: true,
                onChange: () => this.$dispatch('input'),
            });
        },

        destroy() {
            this.picker?.destroy();
        },
    }));

        /**
     * Date range field for forms: dashboard-style presets + inline range calendar.
     * Writes Y-m-d values to two hidden inputs (from / to).
     */
    // Alpine.data('dateRangeField', ({ from, to, maxDate }) => ({
    //     from,
    //     to,
    //     open: false,
    //     draft: [from, to],
    //     picker: null,
    //     panelStyle: '',

    //     init() {
    //         this.picker = flatpickr(this.$refs.calendar, {
    //             mode: 'range',
    //             inline: true,
    //             dateFormat: 'Y-m-d',
    //             maxDate,
    //             defaultDate: [from, to],
    //             onChange: (dates) => {
    //                 this.draft = dates.map((date) => this.iso(date));
    //             },
    //         });
    //     },

    //     destroy() {
    //         this.picker?.destroy();
    //     },

    //     iso(date) {
    //         return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    //     },

    //     pretty(value) {
    //         return value
    //             ? new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    //             : '';
    //     },

    //     get label() {
    //         return this.from === this.to ? this.pretty(this.from) : `${this.pretty(this.from)} – ${this.pretty(this.to)}`;
    //     },

    //     get selectionLabel() {
    //         if (!this.draft.length) return 'Pick a start date';
    //         if (this.draft.length === 1) return `${this.pretty(this.draft[0])} – pick an end date`;

    //         return `${this.pretty(this.draft[0])} – ${this.pretty(this.draft[1])}`;
    //     },

    //     get canApply() {
    //         return this.draft.length > 0;
    //     },

    //     toggle() {
    //         this.open ? (this.open = false) : this.show();
    //     },

    //     show() {
    //         this.draft = [this.from, this.to];
    //         this.picker.setDate(this.draft, false);
    //         this.picker.jumpToDate(this.to);
    //         this.open = true;
    //         this.$nextTick(() => this.place());
    //     },

    //     // Fixed position so the panel is never clipped by modals or overflow-hidden cards.
    //     place() {
    //         const trigger = this.$refs.trigger.getBoundingClientRect();
    //         const panel = this.$refs.panel;
    //         const width = panel.offsetWidth;
    //         const height = panel.offsetHeight;

    //         const left = Math.max(16, Math.min(trigger.left, window.innerWidth - width - 16));
    //         let top = trigger.bottom + 8;

    //         if (top + height > window.innerHeight - 16 && trigger.top - height - 8 > 16) {
    //             top = trigger.top - height - 8;
    //         }

    //         this.panelStyle = `top: ${top}px; left: ${left}px;`;
    //     },

    //     choose(from, to) {
    //         this.from = from;
    //         this.to = to;
    //         this.open = false;
    //         this.$dispatch('input');
    //     },

    //     apply() {
    //         const [from, to = from] = this.draft;
    //         this.choose(from, to);
    //     },
    // }));
    /**
     * Styled single date picker.
     */
    Alpine.data('datePicker', ({ value, minDate, maxDate }) => ({
        picker: null,

        init() {
            this.picker = flatpickr(this.$refs.input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                defaultDate: value || null,
                minDate,
                maxDate,
                disableMobile: true,
                onChange: () => this.$dispatch('input'),
            });
        },

        destroy() {
            this.picker?.destroy();
        },
    }));
    /**
     * Two-step add user wizard.
     */
    Alpine.data('userWizard', ({ step, accountType, userType, typesWithAdmin }) => ({
        step,
        accountType,
        userType,
        loading: false,

        get needsAdmin() {
            return typesWithAdmin.includes(this.userType);
        },

        get needsReportUser() {
            return this.userType === 'reporting';
        },

        value(name) {
            return this.$root.querySelector(`[name="${name}"]`)?.value ?? '';
        },

        next() {
            for (const field of this.$refs.stepOne.querySelectorAll('input:not([type=hidden]), select')) {
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return;
                }
            }

            const missing = [];
            if (!this.value('mobiles')) missing.push('a mobile number');
            if (!this.value('emails')) missing.push('an email');
            if (!this.$root.querySelectorAll('[name="maskings[]"]').length) missing.push('a masking');
            if (!this.value('expiry')) missing.push('an expiry date');
            if (!this.value('customer_id')) missing.push('a master customer');

            if (missing.length) {
                window.toast.error(`Add ${missing.join(', ')} to continue.`);
                return;
            }

            this.step = 2;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        submit(event) {
            const missing = [];
            if (!this.accountType) missing.push('an account type');
            if (this.accountType === 'prepaid' && !this.value('balance')) missing.push('opening credits');
            if (!this.userType) missing.push('a user type');
            if (this.needsAdmin && !this.value('reseller_id')) missing.push('an admin');
            if (this.needsReportUser && !this.value('reporting_user_id')) missing.push('a user for reporting');

            if (missing.length) {
                event.preventDefault();
                window.toast.error(`Choose ${missing.join(', ')}.`);
                return;
            }

            this.loading = true;
        },
    }));
}