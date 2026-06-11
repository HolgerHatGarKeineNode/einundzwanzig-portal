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
        button.launch { margin-top: 1.5rem; padding: .875rem 1.25rem; border: 0; border-radius: .75rem;
                        background: #f7931a; color: #09090b; font-weight: 600; font-size: 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Anmeldung mit Nostr') }}</h1>
        <p>{{ __('Dein Nostr-Signer (z. B. Amber) öffnet sich gleich. Falls nicht, tippe auf den Button.') }}</p>
        <button class="launch" onclick="launchSigner()">{{ __('Signer öffnen') }}</button>
    </div>
    <script>
        // Build the NIP-55 signer URI in the browser with
        // encodeURIComponent(JSON.stringify(event)) — the exact encoding
        // Amber accepts. Launch via window.location so the intent carries
        // category.BROWSABLE and Amber uses its web-signing flow.
        function launchSigner() {
            const event = {
                kind: 22242,
                created_at: Math.floor(Date.now() / 1000),
                content: '',
                tags: [['challenge', @js($k1)]],
            };
            window.location.href = 'nostrsigner:' + encodeURIComponent(JSON.stringify(event))
                + '?compressionType=none&returnType=event&type=sign_event&appName=Einundzwanzig'
                + '&callbackUrl=' + encodeURIComponent(@js($callbackUrl));
        }

        launchSigner();
    </script>
</body>
</html>
