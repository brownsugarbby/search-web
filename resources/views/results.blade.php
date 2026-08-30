{{--
    The ranked result list.

    Only reachable when config('search.results_page_enabled') is true. It is
    built and tested either way; phase 1 simply redirects instead of rendering
    it. The per-result share button lives here, which is why sharing arrives
    together with this page.
--}}
@extends('layouts.app')

@section('title', $query.' - '.$siteName)

@section('content')
<div class="mx-auto w-full max-w-2xl flex-1 px-4 py-6">

    <div class="mb-6 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <a href="{{ route('home') }}" class="shrink-0">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-8 w-auto">
            @else
                <span class="text-xl font-bold text-slate-900 dark:text-white">{{ $siteName }}</span>
            @endif
        </a>
        <div class="w-full">
            @include('components.search-form', ['query' => $query, 'compact' => true])
        </div>
    </div>

    <p class="mb-4 text-xs text-slate-400">{{ $results->count() }} hasil untuk "{{ $query }}"</p>

    <ul class="space-y-4">
        @foreach ($results as $link)
            <li class="rounded-2xl border border-slate-200 p-4 transition hover:border-blue-300 hover:shadow-sm
                       dark:border-slate-800 dark:hover:border-blue-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('go', $link) }}" target="_blank" rel="noopener"
                           class="text-base font-medium text-blue-700 hover:underline dark:text-blue-400">
                            {{ $link->title }}
                        </a>
                        <p class="mt-0.5 truncate text-xs text-emerald-700 dark:text-emerald-500">
                            {{ parse_url($link->url, PHP_URL_HOST) ?: $link->url }}
                        </p>
                        @if ($link->description)
                            <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400">{{ $link->description }}</p>
                        @endif
                    </div>

                    @include('components.share-button', ['link' => $link])
                </div>
            </li>
        @endforeach
    </ul>

</div>
@endsection
