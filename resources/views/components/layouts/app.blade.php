<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @googlefonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-900 text-white">
<flux:navbar>
    <flux:brand>{{ config('app.name') }}</flux:brand>
</flux:navbar>

<main class="min-h-screen">
    {{ $slot }}
</main>

@persist('toast')
<flux:toast.group>
    <flux:toast/>
</flux:toast.group>
@endpersist
@livewireScripts
@fluxScripts
</body>
</html>
