<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Anmeldung mit Nostr') }} — EINUNDZWANZIG</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center;
               background: #09090b; color: #fafafa; font-family: ui-sans-serif, system-ui, sans-serif; }
        .card { text-align: center; padding: 2rem; max-width: 22rem; }
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
        {{-- Must be launched from a user gesture (browsers only dispatch the
             external-app intent on user activation). --}}
        <button class="launch" onclick="launchSigner()">{{ __('Mit Amber signieren') }}</button>
    </div>
    <script>
        // Amber (v6.2+) reads the signer parameters from intent EXTRAS, not
        // from the URI query, unless the browser sets EXTRA_APPLICATION_ID —
        // which Chrome does NOT do for a plain nostrsigner: navigation. An
        // intent:// URL lets us pass the event as the data URI AND the
        // parameters as extras (S.* entries), so Amber's app-to-app flow
        // accepts it. The query is also kept on the data URI as a fallback
        // for the web flow.
        function launchSigner() {
            const event = {
                kind: 22242,
                created_at: Math.floor(Date.now() / 1000),
                content: '',
                tags: [['challenge', @js($k1)]],
            };
            const eventEnc = encodeURIComponent(JSON.stringify(event));
            const callbackUrl = @js($callbackUrl);
            const query = 'compressionType=none&returnType=event&type=sign_event'
                + '&appName=EINUNDZWANZIG&callbackUrl=' + encodeURIComponent(callbackUrl);

            window.location.href = 'intent:' + eventEnc + '?' + query
                + '#Intent;scheme=nostrsigner;category=android.intent.category.BROWSABLE'
                + ';S.type=sign_event;S.returnType=event;S.appName=EINUNDZWANZIG'
                + ';S.callbackUrl=' + callbackUrl
                + ';end';
        }
    </script>
</body>
</html>
