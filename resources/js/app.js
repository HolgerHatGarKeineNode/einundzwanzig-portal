import nostrLogin from "./nostrLogin.js";
import registerCopyToClipboard from "./copyToClipboard.js";

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = L;

Alpine.data('nostrLogin', nostrLogin);
registerCopyToClipboard(Alpine);
