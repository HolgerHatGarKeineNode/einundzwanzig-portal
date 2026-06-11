<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Login bestätigt') }} — Einundzwanzig</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center;
               background: #09090b; color: #fafafa; font-family: ui-sans-serif, system-ui, sans-serif; }
        .card { text-align: center; padding: 2rem; max-width: 22rem; }
        .check { font-size: 3rem; }
        h1 { font-size: 1.25rem; margin: 1rem 0 .5rem; }
        p { color: #a1a1aa; line-height: 1.5; }
        a.button { display: block; margin-top: 1.5rem; padding: .875rem 1.25rem; border-radius: .75rem;
                   background: #f7931a; color: #09090b; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    {{-- No auto-redirect here: a JS navigation to a custom scheme triggers
         Chrome's confirmation prompt. The button tap is a user gesture and
         opens the app directly. With verified App Links this page never
         renders — Android opens the app before the request is made. --}}
    <div class="card">
        <div class="check">✅</div>
        <h1>{{ __('Login bestätigt') }}</h1>
        <p>{{ __('Tippe auf den Button, um zurück zur Einundzwanzig-App zu gelangen.') }}</p>
        <a class="button" href="{{ $deepLink }}">{{ __('Zurück zur App') }}</a>
    </div>
</body>
</html>
