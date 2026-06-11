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
        .logo { width: 5rem; height: 5rem; margin: 0 auto 1.5rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { color: #a1a1aa; line-height: 1.5; }
        button.launch { margin-top: 1.5rem; width: 100%; padding: 1rem 1.25rem; border: 0; border-radius: .75rem;
                        background: #f7931a; color: #09090b; font-weight: 600; font-size: 1.05rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ __('Anmeldung mit Nostr') }}</h1>
        <p>{{ __('Tippe auf den Button, um die Anmeldung mit deinem Nostr-Signer (z. B. Amber) zu signieren.') }}</p>
        {{-- The signer MUST be launched from a user gesture: browsers only
             attach the EXTRA_APPLICATION_ID that routes Amber into its
             web-signing flow when the external-app launch is user-initiated.
             An auto-redirect on load is rejected by Amber as malformed. --}}
        <button class="launch" onclick="launchSigner()">{{ __('Mit Amber signieren') }}</button>
    </div>
    <script>
        // Build the NIP-55 signer URI in the browser with
        // encodeURIComponent(JSON.stringify(event)) and launch via
        // window.location so the intent carries category.BROWSABLE.
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
    </script>
</body>
</html>
