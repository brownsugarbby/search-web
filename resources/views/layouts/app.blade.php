<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteName)</title>
    <meta name="description" content="@yield('meta_description', $metaDescription)">
    @hasSection('noindex')
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="icon" href="{{ $favicon }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-white text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">

    @if ($bannerText)
        <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs text-amber-900
                    dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
            {{ $bannerText }}
            @if ($bannerLink)
                <a href="{{ $bannerLink }}" class="font-semibold underline underline-offset-2 hover:no-underline">
                    Baca disini.
                </a>
            @endif
        </div>
    @endif

    <main class="flex flex-1 flex-col">
        @yield('content')
    </main>

    <footer class="px-4 py-6 text-center text-xs text-slate-400 dark:text-slate-500">
        <span>&copy;{{ date('Y') }}</span>
        <span class="mx-1.5">|</span>
        <a href="{{ route('privacy-policy') }}" class="hover:text-slate-600 hover:underline dark:hover:text-slate-300">
            Kebijakan Privasi
        </a>
    </footer>

</body>
</html>
