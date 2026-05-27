<html lang="it">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800&display=swap" rel="stylesheet">

        @vite('resources/js/fatturino.js')
        @vite('resources/css/fatturino.css')
        <x-inertia::head />
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
