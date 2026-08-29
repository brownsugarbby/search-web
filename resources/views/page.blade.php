@extends('layouts.app')

@section('title', $page->title.' - '.$siteName)
@section('meta_description', $page->meta_description ?? $metaDescription)

@section('content')
<article class="mx-auto w-full max-w-2xl flex-1 px-4 py-10">
    <a href="{{ route('home') }}" class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400">
        &larr; Kembali
    </a>
    <h1 class="mb-4 text-2xl font-bold text-slate-900 dark:text-white">{{ $page->title }}</h1>
    <div class="prose prose-slate max-w-none text-sm leading-relaxed dark:prose-invert">
        {!! $page->body !!}
    </div>
</article>
@endsection
