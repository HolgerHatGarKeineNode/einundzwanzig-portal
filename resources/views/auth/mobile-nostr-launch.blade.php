<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Anmeldung mit Nostr') }} — Einundzwanzig</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center;
               background: #09090b; color: #fafafa; font-family: ui-sans-serif, system-ui, sans-serif; }
        .card { text-align: center; padding: 2rem; max-width: 22rem; }
        h1 { font-size: 1.25rem; margin: 1rem 0 .5rem; }
        p { color: #a1a1aa; line-height: 1.5; }
        a.button { display: inline-block; margin-top: 1.5rem; padding: .875rem 1.25rem; border-radius: .75rem;
                   background: #f7931a; color: #09090b; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Anmeldung mit Nostr') }}</h1>
        <p>{{ __('Dein Nostr-Signer (z. B. Amber) öffnet sich gleich. Falls nicht, tippe auf den Button.') }}</p>
        <a class="button" href="{{ $signerUri }}">{{ __('Signer öffnen') }}</a>
    </div>
    <script>
        // Launch via window.location so the intent carries category.BROWSABLE
        // and Amber routes it into its web-signing flow.
        window.location.href = @js($signerUri);
    </script>
</body>
</html>
