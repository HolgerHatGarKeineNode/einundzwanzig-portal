export default () => ({
    pollingInterval: null,
    errorCheckInterval: null,
    authErrorShown: false,
    startTime: null,
    pollCount: 0,
    MAX_POLL_COUNT: 30,
    // Toggled while window.nostr.signEvent is awaiting the wallet so we can
    // pause wire:poll, preventing it from racing with auth()->login()'s
    // session-id migration (which would otherwise yield a 419 on the next
    // round-trip).
    nostrLoginInProgress: false,

    async init() {
        this.startTime = Date.now();
    },

    async resolveChallenge() {
        // 1) Prefer the data-attribute rendered straight from Blade. This avoids
        //    Livewire's $wire proxy entirely and survives any reactive snapshot
        //    quirks that have surfaced behind HTTP caches on production.
        const fromDataset = this.$root?.dataset?.nostrChallenge;
        if (typeof fromDataset === 'string' && fromDataset !== '') {
            return fromDataset;
        }

        // 2) Fallback to the Livewire snapshot via $wire.
        const livewireComponent = this.$el.closest('[wire\\:id]')?.__livewire;
        const fromWire = livewireComponent?.$wire?.nostrChallenge;
        if (typeof fromWire === 'string' && fromWire !== '') {
            return fromWire;
        }

        // 3) Last resort: ask the server for a freshly issued challenge.
        if (livewireComponent?.$wire?.requestNostrChallenge) {
            try {
                const refreshed = await livewireComponent.$wire.requestNostrChallenge();
                if (typeof refreshed === 'string' && refreshed !== '') {
                    return refreshed;
                }
            } catch (error) {
                console.error('requestNostrChallenge failed:', error);
            }
        }

        return null;
    },

    async openNostrLogin() {
        // Flip the flag immediately so the wire:poll <template x-if> in the
        // blade unmounts the polling element before we kick off any async
        // work. Stays true through the dispatch so the subsequent
        // auth()->login() round-trip is the only request in flight.
        this.nostrLoginInProgress = true;

        try {
            const challenge = await this.resolveChallenge();

            if (!challenge) {
                this.showAuthError('Login challenge missing. Please reload and try again.');
                this.nostrLoginInProgress = false;
                return;
            }

            if (!window.nostr || typeof window.nostr.signEvent !== 'function') {
                this.showAuthError('No Nostr signer found. Please install a Nostr browser extension.');
                this.nostrLoginInProgress = false;
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
                this.nostrLoginInProgress = false;
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
                this.nostrLoginInProgress = false;
                return;
            }

            // Leave nostrLoginInProgress = true: the loginListener will issue
            // a redirect; resetting the flag here would re-mount wire:poll and
            // race the redirect.
            this.$dispatch('nostrLoggedIn', {signedEvent: plainSignedEvent});
        } catch (error) {
            console.error('openNostrLogin unexpected error:', error);
            this.showAuthError('Authentication failed. Please try again.');
            this.nostrLoginInProgress = false;
        }
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
        let message;
        if (typeof error === 'string') {
            message = error;
        } else if (error && typeof error === 'object' && typeof error.message === 'string') {
            message = error.message;
        } else {
            message = 'Authentication failed. Please try again.';
        }

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
    },

    destroy() {
        if (this.errorCheckInterval) {
            clearInterval(this.errorCheckInterval);
        }
    },

});
