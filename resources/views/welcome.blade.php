{{-- Phase 2 placeholder. Replaced by the real storefront layout in Phase 4 (spec §27). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
</head>
<body>
<div class="wrap">
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Phase 2 — Laravel foundation. No storefront yet.</p>

    <div class="card">
        <table class="check">
            <tr><th>Laravel</th><td>{{ app()->version() }}</td></tr>
            <tr><th>PHP</th><td>{{ PHP_VERSION }}</td></tr>
            <tr><th>Environment</th><td>{{ app()->environment() }}</td></tr>
            <tr><th>Database driver</th><td><code>{{ config('database.default') }}</code></td></tr>
            <tr><th>Session / Cache / Queue</th><td><code>{{ config('session.driver') }}</code> / <code>{{ config('cache.default') }}</code> / <code>{{ config('queue.default') }}</code></td></tr>
            <tr><th>Cipher</th><td><code>{{ config('app.cipher') }}</code></td></tr>
            <tr><th>Log channel</th><td><code>{{ config('logging.default') }}</code></td></tr>
        </table>
    </div>

    <p class="muted" style="margin-top:1.5rem">
        Assets are static files under <code>public/</code> — no Vite, no Node build step.
    </p>
</div>
</body>
</html>
