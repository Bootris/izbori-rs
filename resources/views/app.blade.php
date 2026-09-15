<!DOCTYPE html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#ffffff">
    <title>{{ \App\Models\Setting::get('site_name', config('app.name')) }}</title>
    <meta name="description" content="Rezultati izbora po biračkom mestu, sa skeniranim zapisnicima i otvorenim podacima.">
    <script>
        window.__IZBORI__ = {
            dataUrl: @json(rtrim((string) config('izbori.snapshots.public_url'), '/')),
            siteName: @json(\App\Models\Setting::get('site_name', config('app.name'))),
            publisher: @json(\App\Models\Setting::get('publisher', '')),
            methodologyUrl: @json(\App\Models\Setting::get('methodology_url', '')),
            pollSeconds: 60
        };
    </script>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ \App\Models\Setting::get('site_name', config('app.name')) }}">
    <meta property="og:title" content="{{ \App\Models\Setting::get('site_name', config('app.name')) }}">
    <meta property="og:description" content="Rezultati izbora po biračkom mestu, sa skeniranim zapisnicima i otvorenim podacima.">
    <meta property="og:locale" content="sr_RS">
    <meta name="twitter:card" content="summary">
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    <div id="root"></div>
    <noscript>Za prikaz rezultata potreban je JavaScript. Sirovi podaci: <a href="{{ rtrim((string) config('izbori.snapshots.public_url'), '/') }}/index.json">/data/index.json</a></noscript>
</body>
</html>
