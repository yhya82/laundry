<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Phase 1 smoke test</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-screen items-center justify-center bg-stone-100">
        <livewire:phase-1-smoke-test />
        @livewireScripts
    </body>
</html>
