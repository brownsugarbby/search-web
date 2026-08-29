<?php

namespace App\Enums;

enum TrafficSource: string
{
    /** Typed a query on the site. */
    case Direct = 'direct';

    /** Used the "Cari cepat" button. */
    case Lucky = 'lucky';

    /** Opened a shared /s/{slug} link. */
    case Share = 'share';

    /** Picked an entry from the typeahead. */
    case Suggest = 'suggest';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Pencarian',
            self::Lucky => 'Cari cepat',
            self::Share => 'Tautan dibagikan',
            self::Suggest => 'Saran',
        };
    }
}
