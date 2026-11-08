<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
<flux:toast.group>
    <flux:toast/>
</flux:toast.group>
{{ $slot }}
@fluxScripts
<script>
    if (!localStorage.getItem('flux.appearance')) {
        localStorage.setItem('flux.appearance', 'dark');
    }
</script>
@include('partials.clipboard-toast-messages')
</body>
</html>
