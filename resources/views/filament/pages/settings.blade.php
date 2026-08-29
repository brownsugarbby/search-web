<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        {{--
            Inline rather than a Tailwind class: the panel has no custom theme
            registered, so it serves Filament's prebuilt CSS and utilities
            written in this view are not part of that build.
        --}}
        <div style="margin-top: 2rem">
            <x-filament::button type="submit">Simpan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
