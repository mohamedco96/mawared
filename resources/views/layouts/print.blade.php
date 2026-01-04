<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>

    {{-- Load print assets --}}
    @vite(['resources/css/print.css', 'resources/js/print-handler.js'])

    {{-- Print configuration --}}
    <meta name="print-format" content="{{ $format ?? 'a4' }}">
    <meta name="auto-print" content="{{ $autoPrint ? 'true' : 'false' }}">
</head>
<body class="format-{{ $format ?? 'a4' }}">
    {{-- Company Header --}}
    <header class="print-header">
        @yield('header')
    </header>

    {{-- Main Content --}}
    <main class="print-content">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="print-footer">
        @yield('footer')
    </footer>

    {{-- Print configuration for JavaScript --}}
    <script>
        window.printConfig = {
            autoPrint: {{ $autoPrint ? 'true' : 'false' }},
            format: '{{ $format ?? 'a4' }}'
        };
    </script>
</body>
</html>
