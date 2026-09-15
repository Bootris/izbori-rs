<!DOCTYPE html>
<html lang="sr-Latn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ \App\Models\Setting::get('site_name', config('app.name')) }}</title>
    <meta name="description" content="Rezultati izbora u realnom vremenu — po biračkom mestu, sa skeniranim zapisnicima i otvorenim podacima.">
    <script>
        window.__IZBORI__ = {
            dataUrl: @json(rtrim((string) config('izbori.snapshots.public_url'), '/')),
            siteName: @json(\App\Models\Setting::get('site_name', config('app.name'))),
            publisher: @json(\App\Models\Setting::get('publisher', '')),
            methodologyUrl: @json(\App\Models\Setting::get('methodology_url', '')),
            pollSeconds: 60
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    <div id="root"></div>
    <noscript>Za prikaz rezultata potreban je JavaScript. Sirovi podaci: <a href="{{ rtrim((string) config('izbori.snapshots.public_url'), '/') }}/index.json">/data/index.json</a></noscript>
</body>
</html>
