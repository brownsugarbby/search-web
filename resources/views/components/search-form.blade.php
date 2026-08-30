@props(['query' => '', 'compact' => false])

{{-- GET, not POST: this is what makes a search shareable and cacheable. --}}
<form
    action="{{ route('home') }}"
    method="GET"
    {{-- In redirect mode a search lands on someone else's site, so it opens a
         tab and leaves the visitor's search box where they left it. With the
         results list on, that page is ours and stays in-tab - the "Cari cepat"
         button below carries its own target, because it always jumps out. --}}
    @if (! config('search.results_page_enabled')) target="_blank" @endif
    class="w-full"
    x-data="suggest(@js($query), @js((bool) config('search.recent_searches_enabled')))"
    @click.outside="close()"
    @keydown.window.prevent.slash="focusInput()"
>
    <div class="relative z-50">
        <label for="q" class="sr-only">Kata kunci</label>

        <svg class="pointer-events-none absolute top-1/2 left-5 h-5 w-5 -translate-y-1/2 text-slate-400"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/>
        </svg>

        <input
            id="q"
            x-ref="input"
            type="text"
            name="q"
            value="{{ $query }}"
            placeholder="Ketik nama situs yang dicari…"
            autocomplete="off"
            enterkeyhint="search"
            @if (! $compact) autofocus @endif
            x-model="term"
            @keydown.escape="close()"
            @keydown.enter="onEnter($event)"
            class="w-full rounded-2xl border border-slate-200 bg-white py-4 pr-12 pl-13 text-base shadow-sm
                   outline-none transition placeholder:text-slate-400
                   hover:shadow-md focus:border-blue-500 focus:shadow-lg focus:ring-4 focus:ring-blue-500/10
                   dark:border-slate-700 dark:bg-slate-900 dark:placeholder:text-slate-500 dark:focus:border-blue-400"
        >

        {{-- Clear. Only rendered when there is something to clear. --}}
        <button
            type="button"
            x-show="term.length"
            x-cloak
            @click="clear()"
            aria-label="Hapus"
            class="absolute top-1/2 right-4 -translate-y-1/2 rounded-full p-1 text-slate-400
                   transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>

        {{-- Typeahead dropdown - switched off; see config/search.php
             ('suggest_enabled'). The endpoint refuses to answer while it
             is off, so this cannot be re-enabled by the markup alone. --}}
        @if (config('search.suggest_enabled'))
        {{--
            The dropdown shows where each keyword leads, not just the word.
            On a curated directory "where would this take me?" is the question
            worth answering, and answering it here means most visits never need
            a results page at all.
        --}}
        <div
            x-show="open && items.length"
            x-cloak
            {{-- No opacity transition here: a fractional opacity gives the
                 dropdown its own stacking context, which drops it underneath
                 the buttons below the input. --}}
            class="absolute z-50 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white
                   shadow-xl dark:border-slate-700 dark:bg-slate-900"
        >
            <ul>
                <template x-for="(item, i) in items" :key="item.keyword">
                    <li>
                        <a
                            :href="`/go/${item.slug}`"
                            target="_blank"
                            rel="noopener"
                            @mouseenter="active = i"
                            :class="active === i ? 'bg-slate-50 dark:bg-slate-800' : ''"
                            class="flex items-center gap-3 px-4 py-3 transition"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                         bg-gradient-to-br from-blue-500 to-indigo-500 text-sm font-semibold text-white"
                                  x-text="item.title.charAt(0).toUpperCase()"></span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100"
                                      x-text="item.keyword"></span>
                                <span class="block truncate text-xs text-slate-400" x-text="item.host || item.title"></span>
                            </span>

                            <template x-if="item.category">
                                <span class="hidden shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px]
                                             text-slate-500 sm:inline dark:bg-slate-800 dark:text-slate-400"
                                      x-text="item.category"></span>
                            </template>
                        </a>
                    </li>
                </template>
            </ul>
        </div>
        @endif

    </div>

    <div class="relative z-0 mt-4 flex flex-col justify-center gap-2 sm:flex-row">
        <button
            type="submit"
            class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm transition
                   hover:bg-blue-700 hover:shadow focus:ring-4 focus:ring-blue-500/25 focus:outline-none"
        >
            Telusuri
        </button>
        {{-- Always jumps straight to the top match, even once the list is on. --}}
        <button
            type="submit"
            name="lucky"
            value="1"
            formtarget="_blank"
            class="rounded-xl bg-slate-100 px-6 py-2.5 text-sm font-medium text-slate-700 transition
                   hover:bg-slate-200 focus:ring-4 focus:ring-slate-400/20 focus:outline-none
                   dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
        >
            Cari cepat
        </button>
    </div>
</form>
