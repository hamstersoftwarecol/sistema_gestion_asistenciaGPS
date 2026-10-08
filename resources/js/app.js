import Alpine from 'alpinejs';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { punchClock } from './punch';
import { liveMap } from './live-map';
import { pointsMap, sitePicker } from './site-picker';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

window.L = L;
window.Alpine = Alpine;

Alpine.data('punchClock', punchClock);
Alpine.data('liveMap', liveMap);
Alpine.data('sitePicker', sitePicker);
Alpine.data('pointsMap', pointsMap);

Alpine.start();

if ('serviceWorker' in navigator && window.location.protocol !== 'file:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
