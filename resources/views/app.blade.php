<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Aplica el tema antes de mostrar la página para evitar destellos. --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Mantiene el fondo del tema mientras carga el CSS y evita el rebote vertical. --}}
        <style>
            html {
                background-color: #faf7f2;
                overscroll-behavior-y: none;
            }

            html.dark {
                background-color: #191c17;
            }
        </style>

        <link rel="icon" href="/images/nelixia-logo.png" type="image/png">
        <link rel="apple-touch-icon" href="/images/nelixia-logo.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Nelixia') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>