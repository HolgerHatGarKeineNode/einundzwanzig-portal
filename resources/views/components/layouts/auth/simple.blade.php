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
<script>
    window.wnjParams = {
        position: 'bottom',
        // The only accepted value is 'bottom', default is top
        accent: 'orange',
        // Supported values: cyan (default), green, purple, red, orange, neutral, stone
        startHidden: false,
        // If the host page has a button that call `getPublicKey` to start a
        // login procedure, the minimized widget can be hidden until connected
        compactMode: false,
        // Show the minimized widget in a compact form
        disableOverflowFix: false,
        // If the host page on mobile has an horizontal scrolling, the floating
        // element/modal are pushed to the extreme right/bottom and exit the
        // viewport. A style is injected in the html/body elements fix this.
        // This option permit to disable this default behavior
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/window.nostr.js/dist/window.nostr.min.js"></script>
</body>
</html>
