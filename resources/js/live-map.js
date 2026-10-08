import { escapeHtml } from './http';

const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

export function baseMap(element, center = [4.711, -74.0721], zoom = 12) {
    const map = window.L.map(element).setView(center, zoom);
    window.L.tileLayer(TILE_URL, { maxZoom: 19, attribution: TILE_ATTRIBUTION }).addTo(map);
    setTimeout(() => map.invalidateSize(), 250);
    return map;
}

export function siteCircle(site, options = {}) {
    return window.L.circle([Number(site.latitude), Number(site.longitude)], {
        radius: Number(site.radius_m),
        color: '#4f46e5',
        weight: 1.5,
        fillOpacity: 0.08,
        ...options,
    }).bindTooltip(`${escapeHtml(site.name)} · ${site.radius_m} m`);
}

/**
 * Mapa en vivo de las marcaciones de hoy; se actualiza cada `interval` segundos.
 * El mapa y las capas viven fuera del estado reactivo de Alpine para que Leaflet no se envuelva en Proxies.
 */
export function liveMap(config) {
    let map = null;
    let siteLayer = null;
    let punchLayer = null;
    let markers = {};
    let timer = null;

    return {
        punches: [],
        stats: { on_site: 0, checked_out: 0, out_of_zone: 0, late: 0 },
        generatedAt: null,
        countdown: config.interval,
        filter: 'all',
        search: '',
        error: null,
        loading: false,

        init() {
            map = baseMap(this.$refs.map);
            siteLayer = window.L.layerGroup().addTo(map);
            punchLayer = window.L.layerGroup().addTo(map);
            this.load(true);
            timer = setInterval(() => {
                this.countdown -= 1;
                if (this.countdown <= 0) this.load(false);
            }, 1000);
        },

        destroy() {
            clearInterval(timer);
        },

        get visible() {
            const term = this.search.trim().toLowerCase();
            return this.punches.filter((p) => {
                if (this.filter === 'on_site' && !p.on_site) return false;
                if (this.filter === 'out_of_zone' && !p.out_of_zone) return false;
                if (this.filter === 'late' && p.status !== 'late') return false;
                if (term && !`${p.name} ${p.code ?? ''} ${p.department ?? ''}`.toLowerCase().includes(term)) return false;
                return true;
            });
        },

        async load(fit) {
            this.countdown = config.interval;
            this.loading = true;
            try {
                const res = await fetch(config.dataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this.punches = data.punches;
                this.stats = data.stats;
                this.generatedAt = data.generated_at;
                this.error = null;
                this.draw(data, fit);
            } catch (e) {
                this.error = 'No se pudo actualizar el mapa (' + e.message + '). Se reintentará.';
            } finally {
                this.loading = false;
            }
        },

        draw(data, fit) {
            siteLayer.clearLayers();
            punchLayer.clearLayers();
            markers = {};
            const bounds = [];

            data.sites.forEach((site) => {
                siteCircle(site).addTo(siteLayer);
                bounds.push([Number(site.latitude), Number(site.longitude)]);
            });

            data.punches.forEach((p) => {
                const color = p.out_of_zone ? '#dc2626' : p.on_site ? '#16a34a' : '#6b7280';
                const marker = window.L.circleMarker([p.lat, p.lng], {
                    radius: 9,
                    color: '#fff',
                    weight: 2,
                    fillColor: color,
                    fillOpacity: 0.95,
                }).bindPopup(this.popup(p));
                marker.addTo(punchLayer);
                markers[p.id] = marker;
                bounds.push([p.lat, p.lng]);
            });

            if (fit && bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
        },

        popup(p) {
            const selfie = p.selfie ? `<img src="${escapeHtml(p.selfie)}" alt="" class="mb-2 h-24 w-24 rounded object-cover">` : '';
            const zone = p.out_of_zone
                ? '<span style="color:#dc2626;font-weight:600">Fuera de zona</span>'
                : '<span style="color:#16a34a;font-weight:600">Dentro de la geocerca</span>';
            const link = p.url ? `<a href="${escapeHtml(p.url)}" style="color:#4f46e5">Ver registro →</a>` : '';
            return `<div style="min-width:180px">${selfie}
                <div style="font-weight:600">${escapeHtml(p.name)}</div>
                <div style="color:#6b7280;font-size:12px">${escapeHtml(p.department ?? '')}</div>
                <div style="margin-top:4px">Entrada ${escapeHtml(p.check_in)}${p.check_out ? ' · Salida ' + escapeHtml(p.check_out) : ''}</div>
                <div>${escapeHtml(p.site ?? 'Sin sitio')} · ${p.distance ?? '?'} m</div>
                <div>${zone}${p.offline ? ' · <em>sin conexión</em>' : ''}</div>
                <div style="margin-top:4px">${link}</div></div>`;
        },

        focus(p) {
            map.setView([p.lat, p.lng], 17);
            markers[p.id]?.openPopup();
        },
    };
}
