<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Administration · {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 40rem;
                line-height: 1.5;
                color: #1b1b18;
            }
            a { color: #1b4332; }
        </style>
    </head>
    <body>
        <h1>Administration</h1>
        <p>Admin-only area for this shelter install.</p>
        <p><a href="{{ route('admin.settings.edit') }}">Organisation settings</a></p>
        <p><a href="{{ route('admin.custom-fields.index') }}">Custom fields</a></p>
        <p><a href="{{ route('home') }}">Back to home</a></p>
    </body>
</html>
