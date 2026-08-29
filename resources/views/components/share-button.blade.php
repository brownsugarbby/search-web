@props(['link'])

{{--
    Shares this specific entry, not the query that found it.

    The URL carries only the slug, so it resolves against the live catalog when
    the recipient opens it - if an admin later edits this entry's destination,
    every copy of this link already in circulation follows along.
--}}
<div
    x-data="share({ url: @js($link->shareUrl()), title: @js($link->title) })"
    class="relative shrink-0"
>
    <button
        type="button"
        @click="go()"
        :aria-label="`Bagikan ${title}`"
        class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-blue-600
               focus:ring-4 focus:ring-blue-500/15 focus:outline-none dark:hover:bg-slate-800"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/>
        </svg>
    </button>

    {{-- Desktop fallback: no native share sheet, so offer the options directly. --}}
    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        class="absolute right-0 z-10 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1
               text-sm shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
        <a :href="`https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`" target="_blank" rel="noopener"
           class="block px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">WhatsApp</a>
        <button type="button" @click="copy()"
                class="block w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-800"
                x-text="copied ? 'Tersalin!' : 'Salin tautan'"></button>
    </div>
</div>
