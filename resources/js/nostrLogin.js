export default () => ({
    pollingInterval: null,
    errorCheckInterval: null,
    authErrorShown: false,
    startTime: null,
    pollCount: 0,
    MAX_POLL_COUNT: 30,

    async init() {
        this.startTime = Date.now();
    },

    async openNostrLogin() {
        const livewireComponent = this.$el.closest('[wire\\:id]')?.__livewire;
        const rawChallenge = livewireComponent?.$wire?.nostrChallenge;

        // Livewire's $wire proxy returns a function (server-action fallback) when
        // a property is missing from the snapshot. Only accept a non-empty string.
        const challenge = typeof rawChallenge === 'string' && rawChallenge !== ''
            ? rawChallenge
            : null;

        if (!challenge) {
            this.showAuthError('Login challenge missing. Please reload and try again.');
            return;
        }

        if (!window.nostr || typeof window.nostr.signEvent !== 'function') {
            this.showAuthError('No Nostr signer found. Please install a Nostr browser extension.');
            return;
        }

        const event = {
            kind: 22242,
            created_at: Math.floor(Date.now() / 1000),
            tags: [['challenge', challenge]],
            content: '',
        };

        let signedEvent;
        try {
            signedEvent = await window.nostr.signEvent(event);
        } catch (error) {
            console.error('Nostr signEvent failed:', error);
            this.showAuthError('Could not sign Nostr login event. Please try again.');
            return;
        }

        // Some Nostr extensions return objects wrapped in extension-specific
        // proxies (e.g. cloneInto results) that Livewire cannot serialize.
        // Round-trip through JSON to guarantee a plain, cloneable object.
        let plainSignedEvent;
        try {
            plainSignedEvent = JSON.parse(JSON.stringify(signedEvent));
        } catch (error) {
            console.error('Nostr signedEvent serialization failed:', error);
            this.showAuthError('Wallet returned an incompatible signature. Please try a different wallet.');
            return;
        }

        this.$dispatch('nostrLoggedIn', {signedEvent: plainSignedEvent});
    },

    initErrorPolling() {
        this.errorCheckInterval = setInterval(() => {
            this.checkForErrors();
        }, 4000);
    },

    async checkForErrors() {
        if (this.authErrorShown) {
            return;
        }

        try {
            const livewireComponent = this.$el.closest('[wire\\:id]')?.__livewire;
            if (!livewireComponent) {
                return;
            }

            this.pollCount++;
            const elapsedSeconds = Math.floor((Date.now() - this.startTime) / 1000);

            const response = await fetch('/api/check-auth-error', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({
                    k1: livewireComponent.$wire.k1,
                    elapsed_seconds: elapsedSeconds,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                if (data.error) {
                    this.showAuthError(data.error);
                    this.authErrorShown = true;
                }
            }
        } catch (error) {
            console.error('Error checking for auth errors:', error);
        }
    },

    showAuthError(error) {
        let message = error || 'Authentication failed. Please try again.';
        let variant = 'danger';

        if (message.includes('incompatible') || message.includes('format')) {
            message = 'Wallet signature format incompatible. Please try a different wallet.';
            variant = 'warning';
        } else if (message.includes('expired') || message.includes('Session')) {
            message = 'Session expired. Please try again.';
            variant = 'warning';
        }

        if (window.Flux && window.Flux.toast) {
            window.Flux.toast({
                heading: 'Authentication Error',
                text: message,
                variant: variant,
                duration: 8000,
            });
        }

        this.$dispatch('auth-error', {message, variant});
    },

    destroy() {
        if (this.errorCheckInterval) {
            clearInterval(this.errorCheckInterval);
        }
    },

});
