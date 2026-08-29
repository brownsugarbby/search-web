<?php

namespace App\Filament\Widgets;

use App\Enums\TrafficSource;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\SearchLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $since = now()->subDays(7);

        $searches = SearchLog::where('created_at', '>=', $since)->count();
        $misses = SearchLog::where('created_at', '>=', $since)->where('result_count', 0)->count();

        // The number that matters most: the share of visitors who asked for
        // something and left with nothing. Every point of it is a gap in the
        // catalog with real demand behind it.
        $missRate = $searches > 0 ? round($misses / $searches * 100) : 0;

        $shareOpens = LinkClick::where('created_at', '>=', $since)
            ->where('source', TrafficSource::Share->value)
            ->count();

        return [
            Stat::make('Pencarian (7 hari)', number_format($searches))
                ->description(number_format(SearchLog::whereDate('created_at', today())->count()).' hari ini')
                ->color('primary'),

            Stat::make('Tanpa hasil', $missRate.'%')
                ->description(number_format($misses).' pencarian gagal')
                ->color($missRate > 25 ? 'danger' : ($missRate > 10 ? 'warning' : 'success')),

            Stat::make('Dibuka dari tautan', number_format($shareOpens))
                ->description('Lewat tautan yang dibagikan orang')
                ->color('info'),

            Stat::make('Entri aktif', number_format(Link::query()->public()->count()))
                ->description(number_format(Link::whereDoesntHave('keywords')->count()).' tanpa kata kunci')
                ->color('gray'),
        ];
    }
}
