@props([
    'sidebar' => false,
])

@if($sidebar)
    <a {{ $attributes->merge(['class' => 'flex flex-1 flex-col items-center gap-2 px-2 py-3']) }} data-flux-sidebar-brand>
        <x-app-logo-icon class="h-16 w-16" />
        <span class="text-base font-semibold tracking-wide text-zinc-800">SMARTSIS</span>
    </a>
@else
    <flux:brand name="SMARTSIS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
