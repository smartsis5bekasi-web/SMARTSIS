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
--}}
<div
    wire:ignore
    x-data="{
        instance: null,
        value: @entangle($attributes->wire('model')),
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
                if (this.value !== '' && this.value !== null && this.value !== undefined) {
                    // Edit form: restore the saved value.
                    this.instance.setSelected(this.value);
                } else if (!hasPlaceholder) {
                    // No placeholder configured: auto-select the first real option.
                    this.value = this.instance.getSelected()[0] ?? (select.options[0]?.value ?? '');
                    this.instance.setSelected(this.value ?? '');
                }
                // Has placeholder + empty value: SlimSelect shows the placeholder option naturally.
            });

            this.$watch('value', (value) => {
                if ((this.instance.getSelected()[0] ?? '') !== (value ?? '')) {
                    this.instance.setSelected(value ?? '');
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
