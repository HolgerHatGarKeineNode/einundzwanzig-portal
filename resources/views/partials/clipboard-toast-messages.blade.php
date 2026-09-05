{{--
    Toast copy for the shared clipboard directive in `resources/js/copyToClipboard.js`.

    The directive is JavaScript and therefore cannot call `__()`. Handing the two
    translated strings over on `window` from this one partial is what lets the directive
    itself live in a single file: the layouts that used to carry a full copy of it
    (issue #80) now carry neither the behaviour nor the strings.

    Included AFTER `@fluxScripts`, alongside the other bottom-of-body scripts, so the
    layouts keep their existing script order. The directive reads the object when a copy
    actually happens, not at registration time, so this classic script and the deferred
    module can load in either order.
--}}
<script>
    window.clipboardToastMessages = {
        heading: @js(__('Success!')),
        text: @js(__('Copied into clipboard')),
    };
</script>
