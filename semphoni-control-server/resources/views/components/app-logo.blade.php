@props([
'sidebar' => false,
])

<flux:sidebar.brand name="SEMphoni" class="w-full" {{ $attributes }}>
    <x-slot name="logo" class="flex max-h-32 h-auto w-full items-center">
        <x-app-logo-icon class="max-h-32 object-contain object-left" />
    </x-slot>
</flux:sidebar.brand>