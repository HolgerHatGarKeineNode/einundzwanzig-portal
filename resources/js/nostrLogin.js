import {npubEncode} from "nostr-tools/nip19";

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
        const pubkey = await window.nostr.getPublicKey();
        const npub = npubEncode(pubkey);
        console.log(pubkey);
        console.log(npub);
        this.$dispatch('nostrLoggedIn', {pubkey: npub});
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
                    k1: livewireComponent.entangle('k1')[0],
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
