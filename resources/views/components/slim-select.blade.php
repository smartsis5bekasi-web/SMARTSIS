@props([
    'placeholder' => null,
])

{{--
    SlimSelect-enhanced <select>. Usage mirrors a native select:

        <x-slim-select wire:model="role" placeholder="{{ __('Pilih peran') }}">
            <option value="">{{ __('Pilih peran') }}</option>
            @foreach (...) <option value="...">...</option> @endforeach
        </x-slim-select>

    `wire:ignore` keeps Livewire from morphing SlimSelect's injected DOM, while
    `@entangle` bridges the value back to the Livewire property (honouring any
    .live/.defer modifier on the wire:model).

    Everything crossing the SlimSelect boundary is normalised to a string:
    SlimSelect matches options with `===`, so an `?int $classroom_id` arriving as
    `3` would never match `<option value="3">` and the edit form would render the
    placeholder instead of the saved choice.
--}}
<div
    wire:ignore
    x-data="{
        instance: null,
        value: @entangle($attributes->wire('model')),
        /** SlimSelect compares option values with ===, so always hand it a string. */
        asOptionValue(value) {
            return value === null || value === undefined ? '' : String(value);
        },
        selectedOptionValue() {
            return this.asOptionValue(this.instance?.getSelected()[0]);
        },
        init() {
            const select = this.$refs.select;
            const hasPlaceholder = @js((bool) $placeholder);

            Array.from(select.options).forEach((option) => {
                if (option.value === '') {
                    if (hasPlaceholder) {
                        // Convert to a SlimSelect placeholder option so it shows
                        // when nothing is selected (create forms).
                        option.setAttribute('data-placeholder', 'true');
                    } else {
                        option.remove();
                    }
                }
            });

            this.instance = new SlimSelect({
                select: select,
                settings: {
                    allowDeselect: false,
                    @if ($placeholder) placeholderText: @js($placeholder), @endif
                },
                events: {
                    afterChange: (selected) => {
                        this.value = selected.length ? selected[0].value : '';
                    },
                },
            });

            this.$nextTick(() => {
                const value = this.asOptionValue(this.value);

                if (value !== '') {
                    // Edit form (or a create form re-rendered after a validation
                    // error): restore the saved value without echoing it back.
                    this.instance.setSelected(value, false);
                } else if (! hasPlaceholder) {
                    // No placeholder configured: auto-select the first real option.
                    this.value = this.selectedOptionValue() || this.asOptionValue(select.options[0]?.value);
                    this.instance.setSelected(this.asOptionValue(this.value), false);
                }
                // Has placeholder + empty value: SlimSelect shows the placeholder option naturally.
            });

            this.$watch('value', (value) => {
                const next = this.asOptionValue(value);

                if (this.selectedOptionValue() !== next) {
                    this.instance.setSelected(next, false);
                }
            });
        },
        destroy() {
            this.instance?.destroy();
        },
    }"
>
    <select x-ref="select" {{ $attributes->except('wire:model')->merge(['class' => 'w-full']) }}>
        {{ $slot }}
    </select>
</div>
