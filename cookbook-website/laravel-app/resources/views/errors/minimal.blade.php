<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') - Ableton Cookbook</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body {
                font-family: 'Ableton Sans', 'AbletonSans-Regular', ui-sans-serif, system-ui, sans-serif;
            }
        </style>
    </head>
    <body class="bg-ableton-black antialiased">
        @yield('content')
    </body>
</html>