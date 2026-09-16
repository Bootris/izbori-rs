<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Public site = one static React SPA that reads /data/*.json (see
| docs/DATA-CONTRACT.md). It never talks to Laravel. Everything that is not the
| admin panel, the data folder or a real file falls through to the SPA shell.
|
| The shell is the only PHP hit a visitor ever causes, so it is served without
| the `web` group: no session row, no cookies, no CSRF token, and a public
| Cache-Control so nginx microcache / the CDN absorb a refresh storm instead of
| php-fpm. Settings baked into the shell (site name, publisher) change rarely;
| a minute of staleness is fine.
*/
$reserved = implode('|', array_map('preg_quote', array_filter([
    config('izbori.admin_path'),
    'data', 'storage', 'build', 'up', 'livewire', 'filament', 'js', 'css', 'fonts',
])));

Route::get('/{any?}', fn () => response()
    ->view('app')
    ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=600'))
    ->where('any', "^(?!(?:{$reserved})(?:/|$)).*$")
    ->withoutMiddleware('web')
    ->name('spa');
