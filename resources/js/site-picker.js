import { escapeHtml } from './http';
import { baseMap, siteCircle } from './live-map';

/**
 * Selector de geocerca para el formulario de sitios: clic o arrastre del marcador para fijar
 * el centro, y el radio se dibuja en vivo.
 */
export function sitePicker(config) {
    let map = null;
    let marker = null;
    let circle = null;

    return {
        lat: config.lat,
        lng: config.lng,
        radius: config.radius,
        locating: false,

        init() {
            const hasCenter = this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '';
            const center = hasCenter ? [Number(this.lat), Number(this.lng)] : [4.711, -74.0721];
            map = baseMap(this.$refs.map, center, hasCenter ? 16 : 12);

            (config.others || []).forEach((site) => siteCircle(site, { color: '#9ca3af' }).addTo(map));

            marker = window.L.marker(center, { draggable: true }).addTo(map);
            circle = window.L.circle(center, { radius: Number(this.radius) || 0, color: '#4f46e5', fillOpacity: 0.15 }).addTo(map);

            marker.on('dragend', (e) => this.set(e.target.getLatLng()));
            map.on('click', (e) => this.set(e.latlng));

            this.$watch('radius', (r) => circle.setRadius(Number(r) || 0));
            this.$watch('lat', () => this.move());
            this.$watch('lng', () => this.move());
        },

        set(latlng) {
            this.lat = Number(latlng.lat).toFixed(7);
            this.lng = Number(latlng.lng).toFixed(7);
        },

        move() {
            const ll = [Number(this.lat), Number(this.lng)];
            if (Number.isNaN(ll[0]) || Number.isNaN(ll[1])) return;
            marker.setLatLng(ll);
            circle.setLatLng(ll);
        },

        locate() {
            if (!navigator.geolocation) return;
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.set({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                    map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                    this.locating = false;
                },
                () => (this.locating = false),
                { enableHighAccuracy: true, timeout: 15000 },
            );
        },
    };
}

/**
 * Mapa de solo lectura con sitios y puntos (p. ej. ubicación de entrada y salida de un registro).
 */
export function pointsMap(config) {
    return {
        init() {
            const map = baseMap(this.$refs.map);
            const bounds = [];

            (config.sites || []).forEach((site) => {
                siteCircle(site).addTo(map);
                bounds.push([Number(site.latitude), Number(site.longitude)]);
            });

            (config.points || []).forEach((point) => {
                window.L.circleMarker([point.lat, point.lng], {
                    radius: 8,
                    color: '#fff',
                    weight: 2,
                    fillColor: point.color || '#4f46e5',
                    fillOpacity: 0.95,
                })
                    .bindTooltip(escapeHtml(point.label), { permanent: true, direction: 'top', offset: [0, -8] })
                    .addTo(map);
                bounds.push([point.lat, point.lng]);
            });

            if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
        },
    };
}
