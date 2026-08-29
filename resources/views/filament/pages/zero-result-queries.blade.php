<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Apa yang dicari pengunjung tapi tidak ada</x-slot>
        <x-slot name="description">
            Setiap baris adalah permintaan nyata yang gagal. Baris bertanda "Tautan mati"
            berarti ada orang yang sedang membagikan tautan ke entri yang sudah tidak ada -
            itu yang paling mendesak.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
