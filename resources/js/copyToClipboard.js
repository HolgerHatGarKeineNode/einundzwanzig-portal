/*
 * `x-copy-to-clipboard="<expression>"` — copy the evaluated expression and confirm
 * with a Flux toast.
 *
 * Issue #80: this used to exist as three literal copies, one in each of the app and
 * auth layouts, and every one of them bound `click` only. The elements that carry the
 * directive are marked `role="button" tabindex="0"` (see
 * `resources/views/components/nostr-calendar-address.blade.php`), so the keyboard can
 * focus them — WCAG 2.1.1 then requires the keyboard to operate them too. Focus landed
 * there and Enter and Space did nothing.
 *
 * One definition, in one file, handling `click`, Enter and Space for every layout.
 */

/**
 * The two keys the WAI-ARIA button pattern requires. `Spacebar` is the legacy
 * `KeyboardEvent.key` value some engines still emit for the space bar.
 */
const ACTIVATION_KEYS = ['Enter', ' ', 'Spacebar'];

/**
 * Controls the browser already activates from the keyboard on its own: a real
 * `<button>` turns both Enter and Space into a `click`. Adding our own `keydown`
 * handler there would copy twice and raise two toasts — `webhooks.blade.php` uses the
 * directive on exactly such a `<button>`.
 *
 * @param {Element} el
 * @return {boolean}
 */
const activatesItself = (el) => el.matches('button, input, select, textarea, a[href]');

/**
 * Registers the directive on the given Alpine instance.
 *
 * @param {object} Alpine
 */
export default function registerCopyToClipboard(Alpine) {
    Alpine.directive('copy-to-clipboard', (el, {expression}, {evaluate}) => {
        const copy = () => {
            // Read lazily: the messages are written by `partials/clipboard-toast-messages`,
            // which is a classic script in the body while this module is deferred. The
            // fallbacks are the untranslated source strings, so a layout that forgets the
            // partial still copies — it only loses the translation.
            const messages = window.clipboardToastMessages ?? {};

            navigator.clipboard.writeText(evaluate(expression)).then(() => {
                Flux.toast({
                    heading: messages.heading ?? 'Success!',
                    text: messages.text ?? 'Copied into clipboard',
                    variant: 'success',
                    duration: 3000,
                });
            }).catch((err) => console.error(err));
        };

        el.addEventListener('click', copy);

        if (activatesItself(el)) {
            return;
        }

        el.addEventListener('keydown', (event) => {
            if (!ACTIVATION_KEYS.includes(event.key)) {
                return;
            }

            // Space scrolls the page down by default. On a control that is not a real
            // button nothing cancels that, so the page would jump while copying.
            event.preventDefault();
            copy();
        });
    });
}
