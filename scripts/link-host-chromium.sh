#!/usr/bin/env bash
#
# Point pest-plugin-browser's Playwright at the Chromium already installed on
# this host, instead of letting it download its own copy.
#
# Playwright resolves a browser by joining PLAYWRIGHT_BROWSERS_PATH with a
# directory named "{name}-{revision}" from node_modules/playwright-core/browsers.json
# and a platform path, then only checks that the file exists. So a symlink
# registry is enough. There is no executable-path option to set: Pest's
# BrowserFactory::launch() sends a fixed options object with no executablePath
# or channel, and playwright-core honours no PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
# (grep coreBundle.js — only PLAYWRIGHT_MCP_EXECUTABLE_PATH exists).
#
# Both variants are needed. Pest launches headless by default, which resolves to
# chromium-headless-shell, so wiring only plain chromium still fails with
# "Executable doesn't exist".
#
# The revision is not a constant — it moves with playwright-core. It is read
# from browsers.json here rather than hardcoded.
#
set -euo pipefail

cd "$(dirname "$0")/.."

BROWSERS_JSON=node_modules/playwright-core/browsers.json

if [ ! -f "$BROWSERS_JSON" ]; then
    echo "link-host-chromium: $BROWSERS_JSON is missing — run yarn install first." >&2
    exit 1
fi

CHROMIUM_BIN=$(command -v chromium || command -v chromium-browser || command -v google-chrome-stable || true)

if [ -z "$CHROMIUM_BIN" ]; then
    echo "link-host-chromium: no Chromium found on this host. Install one (Arch: pacman -S chromium)." >&2
    exit 1
fi

REV=$(php -r '
    $d = json_decode(file_get_contents($argv[1]), true);
    foreach ($d["browsers"] as $b) {
        if ($b["name"] === "chromium") { echo $b["revision"]; return; }
    }
    exit(1);
' "$BROWSERS_JSON")

ROOT=${PLAYWRIGHT_BROWSERS_PATH:-$HOME/.cache/ms-playwright}

link() {
    local dir="$ROOT/$1-$REV/$2"
    mkdir -p "$dir"
    ln -sfn "$CHROMIUM_BIN" "$dir/$3"
    touch "$ROOT/$1-$REV/INSTALLATION_COMPLETE"
}

link chromium chrome-linux64 chrome
link chromium_headless_shell chrome-headless-shell-linux64 chrome-headless-shell

echo "link-host-chromium: $ROOT/{chromium,chromium_headless_shell}-$REV -> $CHROMIUM_BIN ($($CHROMIUM_BIN --version))"
