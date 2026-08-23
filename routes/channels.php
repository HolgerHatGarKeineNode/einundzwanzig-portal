<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Bewusst leer (Issue #29, Plan-Phase P4).
|
| Das Portal broadcastet ausschliesslich auf OEFFENTLICHEN Kanaelen — `portal` und
| `meetup-events`. Ein oeffentlicher Kanal wird von Reverb ohne Rueckfrage bei der
| Anwendung abonniert; `Broadcast::channel()` wird fuer ihn nie aufgerufen. Eine
| Autorisierungsregel hier haette also keine Wirkung, aber sie waere ein Versprechen:
| wer sie liest, haelt den Kanal fuer geschuetzt.
|
| Private und Presence-Kanaele sind laut Plan ausdruecklich out of scope; sie brauchen
| `/broadcasting/auth` und eine Autorisierungsschicht, die es hier nicht gibt.
|
| Die Datei existiert trotzdem, und sie existierte VOR `reverb:install`: der Installer
| ruft sonst `install:broadcasting` auf, das `resources/js/echo.js` anlegt und einen
| Import in `app.js` haengt. Das Portal konsumiert seine eigenen Kanaele nicht.
|
*/
