<?php

use App\Filament\Pages\ZeroResultQueries;
use App\Filament\Resources\Links\Pages\ListLinks;
use App\Models\Link;
use App\Models\SearchLog;
use App\Models\User;

use function Pest\Livewire\livewire;

it('keeps a guest out of the admin panel', function () {
    $this->get('/'.config('search.panel_path'))->assertRedirect();
});

it('does not sit at the default /admin path', function () {
    expect(config('search.panel_path'))->not->toBe('admin');
});

describe('signed in', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->create()));

    it('lists links', function () {
        $link = Link::factory()->create(['title' => 'Portal Berita']);

        livewire(ListLinks::class)
            ->assertCanSeeTableRecords([$link]);
    });

    /*
     * This page groups by keyword, which put it at odds with MySQL's
     * ONLY_FULL_GROUP_BY twice: once on the aggregate itself, and again on the
     * primary-key tiebreaker Filament adds to every sort. Neither showed up
     * until the page was actually rendered, so it is covered here.
     */
    it('renders the zero-result report over a grouped query', function () {
        SearchLog::insert([
            ['query_raw' => 'bioskop', 'query_normalized' => 'bioskop', 'result_count' => 0, 'source' => 'direct', 'created_at' => now()],
            ['query_raw' => 'bioskop', 'query_normalized' => 'bioskop', 'result_count' => 0, 'source' => 'direct', 'created_at' => now()],
            ['query_raw' => 'ketemu', 'query_normalized' => 'ketemu', 'result_count' => 3, 'source' => 'direct', 'created_at' => now()],
        ]);

        livewire(ZeroResultQueries::class)
            ->assertOk()
            ->assertSee('bioskop')
            // Searches that succeeded have no business in a report about gaps.
            ->assertDontSee('ketemu');
    });

    it('ranks a dead shared link above a more common failed search', function () {
        SearchLog::insert([
            ['query_raw' => 'sering', 'query_normalized' => 'sering', 'result_count' => 0, 'source' => 'direct', 'created_at' => now()],
            ['query_raw' => 'sering', 'query_normalized' => 'sering', 'result_count' => 0, 'source' => 'direct', 'created_at' => now()],
            ['query_raw' => 'sering', 'query_normalized' => 'sering', 'result_count' => 0, 'source' => 'direct', 'created_at' => now()],
            ['query_raw' => 'dibagikan', 'query_normalized' => 'dibagikan', 'result_count' => 0, 'source' => 'share', 'created_at' => now()],
        ]);

        // Fewer searches, but someone is actively handing that link out, so it
        // has to come first.
        livewire(ZeroResultQueries::class)
            ->assertOk()
            ->assertSeeInOrder(['dibagikan', 'sering']);
    });
});
