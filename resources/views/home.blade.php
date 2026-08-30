@extends('layouts.app')

@section('content')
<div class="relative flex flex-1 flex-col">

    {{-- Backdrop. Purely decorative, so it is hidden from assistive tech. --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-40 left-1/2 h-96 w-[42rem] -translate-x-1/2 rounded-full
                    bg-gradient-to-br from-blue-200/45 via-indigo-200/35 to-transparent blur-3xl
                    dark:from-blue-600/20 dark:via-indigo-600/12 dark:to-transparent"></div>
    </div>

    <div class="relative mx-auto flex w-full max-w-2xl flex-1 flex-col px-4 pt-16 pb-10 sm:pt-24">

        <div class="mb-8 text-center">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $siteName }}" class="mx-auto h-14 w-auto">
            @else
                {{-- Wordmark rather than a fixed logo: the site name is a
                     setting, so this has to look right whatever it becomes. --}}
                <h1 class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-600 bg-clip-text
                           text-5xl font-bold tracking-tight text-transparent
                           sm:text-6xl dark:from-white dark:via-slate-100 dark:to-slate-400">
                    {{ $siteName }}
                </h1>
            @endif
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                Ketik nama situs, langsung diantar ke tujuan.
            </p>
        </div>

        @include('components.search-form', ['query' => $query ?? ''])

        {{-- Both a failed search and a shared link whose entry is gone land
             here, so this state does real work rather than being a dead end. --}}
        @if (! empty($noResults) || session('deadLink'))
            <div class="mt-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3
                        dark:border-amber-900/40 dark:bg-amber-950/30">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor"
                     stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
                </svg>
                <p class="text-sm text-amber-900 dark:text-amber-200">
                    @if (session('deadLink'))
                        Tautan yang anda buka sudah tidak tersedia. Coba cari dengan kata kunci di bawah.
                    @else
                        Tidak ada hasil, coba dengan kata kunci lain.
                    @endif
                </p>
            </div>
        @endif

        {{-- Recent searches - switched off; see config/search.php
             ('recent_searches_enabled'). Kept in the visitor's own browser
             only; it never reaches the server. --}}
        @if (config('search.recent_searches_enabled'))
        <div x-data="recentSearches()" x-show="items.length" x-cloak class="mt-6">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Terakhir dicari</span>
                <button @click="clear()" class="text-xs text-slate-400 transition hover:text-slate-600">Hapus</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <template x-for="item in items" :key="item">
                    <a :href="`/?q=${encodeURIComponent(item)}`"
                       class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600 transition
                              hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                       x-text="item"></a>
                </template>
            </div>
        </div>
        @endif

        {{-- Category chips and the popular grid are switched off; see
             config/search.php ('catalog_preview_enabled'). --}}
        @if (config('search.catalog_preview_enabled') && $categories->isNotEmpty())
            <div class="mt-10">
                <span class="mb-3 block text-xs font-medium tracking-wide text-slate-400 uppercase">Kategori</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <a href="{{ route('category', $category->slug) }}"
                           class="group inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white
                                  px-3.5 py-1.5 text-sm text-slate-700 shadow-sm transition
                                  hover:border-blue-400 hover:text-blue-700 hover:shadow
                                  dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-500">
                            {{ $category->name }}
                            <span class="text-xs text-slate-400 group-hover:text-blue-500">{{ $category->links_count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if (config('search.catalog_preview_enabled') && $popularLinks->isNotEmpty())
            <div class="mt-8">
                <span class="mb-3 block text-xs font-medium tracking-wide text-slate-400 uppercase">Sering dibuka</span>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($popularLinks as $link)
                        <a href="{{ route('go', $link) }}" target="_blank" rel="noopener"
                           class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5
                                  transition hover:border-blue-400 hover:shadow-sm
                                  dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-600">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg
                                         bg-gradient-to-br from-blue-500 to-indigo-500 text-xs font-semibold text-white">
                                {{ mb_strtoupper(mb_substr($link->title, 0, 1)) }}
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-slate-700 dark:text-slate-200">{{ $link->title }}</span>
                                <span class="block truncate text-[11px] text-slate-400">
                                    {{ parse_url($link->url, PHP_URL_HOST) ?: '' }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
