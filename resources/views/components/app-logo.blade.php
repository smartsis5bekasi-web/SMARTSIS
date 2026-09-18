@props([
    'sidebar' => false,
])

@if($sidebar)
    {{-- Compact row on phones (the mobile sidebar is an overlay, so header
         height is screen real estate); the tall centred lockup returns from lg. --}}
    <a {{ $attributes->merge(['class' => 'flex min-w-0 flex-1 items-center gap-2 px-1 py-1 lg:flex-col lg:justify-center lg:gap-2 lg:px-2 lg:py-3']) }} data-flux-sidebar-brand>
        <x-app-logo-icon class="size-9 shrink-0 lg:size-16" />
        <span class="truncate text-base font-semibold tracking-wide text-zinc-800">SMARTSIS</span>
    </a>
@else
    <flux:brand name="SMARTSIS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
