import ApexCharts from 'apexcharts';
import flatpickr from 'flatpickr';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const number = new Intl.NumberFormat('en-US');
const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

const ink = '#1D2433';
const muted = '#5B6475';
const grid = '#EDF0F4';
const status = { good: '#0ca30c', critical: '#d03b3b' };

const animations = {
    enabled: !reduceMotion,
    speed: 800,
    animateGradually: { enabled: true, delay: 25 },
    dynamicAnimation: { enabled: true, speed: 400 },
};

const fontFamily = '"Figtree Variable", ui-sans-serif, system-ui, sans-serif';

/**
 * Shared options: recessive grid and axes, thin marks, hover tooltips.
 */
function baseOptions(type, height) {
    return {
        chart: { type, height, fontFamily, foreColor: muted, toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0, animations },
        dataLabels: { enabled: false },
        legend: { show: false },
        grid: { borderColor: grid, strokeDashArray: 4, padding: { left: 4, right: 8 } },
        xaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
            labels: { style: { colors: muted, fontSize: '12px' } },
        },
        yaxis: { labels: { style: { colors: muted, fontSize: '12px' }, formatter: (value) => compact.format(value) } },
        tooltip: { theme: 'light', style: { fontSize: '13px', fontFamily }, y: { formatter: (value) => number.format(value) } },
        states: { hover: { filter: { type: 'darken', value: 0.92 } }, active: { filter: { type: 'none' } } },
    };
}

/** Format a Date as YYYY-MM-DD in local time (toISOString would shift the day). */
function toDateString(date) {
    const pad = (value) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function registerCharts(Alpine) {
    /**
     * Daily trend with two views: stacked delivered/failed volume, or credits.
     */
    Alpine.data('trendChart', (data) => ({
        view: 'volume',
        chart: null,

        init() {
            this.render();
            this.$watch('view', () => this.render());
        },

        render() {
            this.chart?.destroy();

            const options = baseOptions(this.view === 'volume' ? 'bar' : 'area', 300);
            const xaxis = {
                ...options.xaxis,
                type: 'datetime',
                categories: data.dates,
                labels: { ...options.xaxis.labels, format: 'dd MMM', datetimeUTC: false },
            };
            const tooltip = { ...options.tooltip, shared: true, intersect: false, x: { format: 'dd MMM yyyy' } };

            const config = this.view === 'volume'
                ? {
                    ...options,
                    chart: { ...options.chart, stacked: true },
                    series: [
                        { name: 'Delivered', data: data.delivered },
                        { name: 'Failed', data: data.failed },
                    ],
                    colors: [status.good, status.critical],
                    plotOptions: { bar: { columnWidth: '70%', borderRadius: 3, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
                    legend: { show: true, position: 'top', horizontalAlign: 'right', fontSize: '13px', labels: { colors: ink }, markers: { size: 5, shape: 'circle' }, itemMargin: { horizontal: 10 } },
                    xaxis,
                    tooltip,
                }
                : {
                    ...options,
                    series: [{ name: 'Credits', data: data.credits }],
                    colors: ['#2a78d6'],
                    stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'solid', opacity: 0.1 },
                    markers: { size: 0, strokeWidth: 2, strokeColors: '#ffffff', hover: { size: 5 } },
                    xaxis,
                    tooltip,
                };

            this.chart = new ApexCharts(this.$refs.canvas, config);
            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));

    /**
     * Small trend line inside a KPI card.
     */
    Alpine.data('sparkline', ({ values, color }) => ({
        chart: null,

        init() {
            this.chart = new ApexCharts(this.$el, {
                chart: { type: 'area', height: 56, sparkline: { enabled: true }, animations },
                series: [{ data: values }],
                colors: [color],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'solid', opacity: 0.12 },
                tooltip: { enabled: false },
            });

            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));

    /**
     * Gauge showing the delivery rate.
     */
    Alpine.data('gaugeChart', ({ value, label, color }) => ({
        chart: null,

        init() {
            this.chart = new ApexCharts(this.$el, {
                chart: { type: 'radialBar', height: 260, fontFamily, animations, parentHeightOffset: 0 },
                series: [value],
                labels: [label],
                colors: [color],
                stroke: { lineCap: 'round' },
                plotOptions: {
                    radialBar: {
                        startAngle: -135,
                        endAngle: 135,
                        hollow: { size: '66%' },
                        track: { background: grid, strokeWidth: '100%' },
                        dataLabels: {
                            name: { offsetY: 26, color: muted, fontSize: '13px', fontWeight: 500 },
                            value: { offsetY: -14, color: ink, fontSize: '32px', fontWeight: 600, formatter: (v) => `${Number(v).toFixed(1)}%` },
                        },
                    },
                },
            });

            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));

    /**
     * Donut with the total in the centre.
     */
    Alpine.data('donutChart', ({ labels, values, colors }) => ({
        chart: null,

        init() {
            this.chart = new ApexCharts(this.$el, {
                chart: { type: 'donut', height: 240, fontFamily, animations, parentHeightOffset: 0 },
                series: values,
                labels,
                colors,
                legend: { show: false },
                dataLabels: { enabled: false },
                stroke: { width: 2, colors: ['#ffffff'] },
                tooltip: { y: { formatter: (value) => number.format(value) } },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                name: { offsetY: 22, color: muted, fontSize: '13px' },
                                value: { offsetY: -12, color: ink, fontSize: '26px', fontWeight: 600, formatter: (v) => compact.format(v) },
                                total: { show: true, label: 'Total', color: muted, fontSize: '13px', formatter: (w) => compact.format(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) },
                            },
                        },
                    },
                },
                states: { hover: { filter: { type: 'darken', value: 0.92 } } },
            });

            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));

    /**
     * Monthly bars with the latest month emphasised.
     */
    Alpine.data('monthlyChart', ({ labels, values }) => ({
        chart: null,

        init() {
            const options = baseOptions('bar', 260);
            const colors = values.map((_, index) => (index === values.length - 1 ? '#2a78d6' : '#b7d3f6'));

            this.chart = new ApexCharts(this.$el, {
                ...options,
                series: [{ name: 'Messages', data: values }],
                colors,
                plotOptions: { bar: { distributed: true, borderRadius: 5, borderRadiusApplication: 'end', columnWidth: '58%' } },
                xaxis: { ...options.xaxis, categories: labels },
            });

            this.chart.render();
        },

        destroy() {
            this.chart?.destroy();
        },
    }));

    /**
     * Animate a number from 0 to its value once, respecting reduced motion.
     */
    Alpine.data('countUp', (target, decimals = 0, suffix = '') => ({
        display: '',

        init() {
            const format = (value) =>
                value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;

            if (reduceMotion || target === 0) {
                this.display = format(target);
                return;
            }

            const duration = 1000;
            const start = performance.now();

            const step = (now) => {
                const progress = Math.min(1, (now - start) / duration);
                this.display = format(target * (1 - Math.pow(1 - progress, 3)));

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        },
    }));

    /**
     * Grow a horizontal meter from 0 to its width after mount.
     */
    Alpine.data('meter', (width) => ({
        width: reduceMotion ? width : 0,

        init() {
            if (!reduceMotion) {
                requestAnimationFrame(() => requestAnimationFrame(() => (this.width = width)));
            }
        },
    }));

    /**
     * Date range popover: presets plus a two-month range calendar.
     */
    Alpine.data('dateRange', ({ from, to, maxDate, maxDays, baseUrl }) => ({
        open: false,
        start: from,
        end: to,
        picker: null,
        loading: false,
        loadingTarget: null,

        init() {
            // Build the calendar on first open: a hidden container has no width to measure
            this.$watch('open', (isOpen) => {
                if (isOpen && !this.picker) {
                    this.$nextTick(() => this.createPicker());
                }
            });
        },

        createPicker() {
            this.picker = flatpickr(this.$refs.calendar, {
                inline: true,
                mode: 'range',
                showMonths: window.innerWidth >= 768 ? 2 : 1,
                maxDate,
                defaultDate: [from, to],
                monthSelectorType: 'static',
                onChange: (dates) => {
                    this.start = dates[0] ? toDateString(dates[0]) : null;
                    this.end = dates[1] ? toDateString(dates[1]) : null;
                },
            });
        },

        get days() {
            if (!this.start || !this.end) {
                return 0;
            }

            return Math.round((new Date(this.end) - new Date(this.start)) / 86400000) + 1;
        },

        get canApply() {
            return this.days > 0 && this.days <= maxDays;
        },

        get selectionLabel() {
            if (!this.start) return 'Select a start date';
            if (!this.end) return 'Select an end date';
            if (this.days > maxDays) return `Choose ${maxDays} days or less`;

            return `${this.days} ${this.days === 1 ? 'day' : 'days'} selected`;
        },

        choose(range) {
            if (this.loading) return;

            this.loading = true;
            this.loadingTarget = range;
            window.location.href = `${baseUrl}?range=${range}`;
        },

        apply() {
            if (!this.canApply || this.loading) return;

            this.loading = true;
            this.loadingTarget = 'custom';
            window.location.href = `${baseUrl}?range=custom&from=${this.start}&to=${this.end}`;
        },

        reset() {
            this.choose('30d');
        },

        destroy() {
            this.picker?.destroy();
        },
    }));
}