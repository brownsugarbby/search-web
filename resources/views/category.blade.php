@extends('layouts.app')

@section('title', $category->name.' - '.$siteName)

@section('content')
<div class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">

    <div class="mb-6 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <a href="{{ route('home') }}" class="shrink-0 text-lg font-bold text-slate-900 dark:text-white">
            {{ $siteName }}
        </a>
        <div class="w-full">
            @include('components.search-form', ['query' => '', 'compact' => true])
        </div>
    </div>

    <h1 class="mb-1 text-xl font-semibold text-slate-900 dark:text-white">{{ $category->name }}</h1>
    <p class="mb-5 text-xs text-slate-400">{{ $links->total() }} entri</p>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($links as $link)
            <a href="{{ route('go', $link) }}" rel="noopener"
               class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-3 transition
                      hover:border-blue-400 hover:shadow-sm
                      dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-600">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                             bg-gradient-to-br from-blue-500 to-indigo-500 text-sm font-semibold text-white">
                    {{ mb_strtoupper(mb_substr($link->title, 0, 1)) }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $link->title }}</span>
                    <span class="block truncate text-xs text-slate-400">{{ parse_url($link->url, PHP_URL_HOST) ?: '' }}</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-6">{{ $links->links() }}</div>
</div>
@endsection
