<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {!! seo($SEOData ?? null) !!}
    @googlefonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="font-sans antialiased bg-zinc-900 dark">
<main>
    {{ $slot }}
</main>>
@fluxScripts
<script>
    localStorage.setItem('flux.appearance', 'dark');
</script
@persist('toast')
<flux:toast.group>
    <flux:toast/>
</flux:toast.group>
@endpersist
</body>
</html>
