{{--
    Single date field using the shared flatpickr datePicker.
    Usage: <x-date-field name="from" label="From" :value="$from" />  (posts Y-m-d)
--}}
@props([
    'name',
    'value' => null,
    'label' => null,
    'min' => null,
    'max' => null,
    'id' => null,
])

@php($id ??= 'date-' . $name . '-' . uniqid())

<div {{ $attributes }} x-data="datePicker(@js(['value' => $value, 'minDate' => $min, 'maxDate' => $max ?? now()->toDateString()]))">
    @if ($label)
        <label class="field-label" for="{{ $id }}">{{ $label }}</label>
    @endif
    <div class="relative">
        <x-heroicon-o-calendar-days class="pointer-events-none absolute left-3.5 top-1/2 z-10 size-4 -translate-y-1/2 text-muted" />
        <input x-ref="input" id="{{ $id }}" type="text" name="{{ $name }}" value="{{ $value }}" placeholder="Select date" class="field-input pl-10">
    </div>
</div>