import { firstError, haversine, postJson, refreshCsrf, uuid } from './http';

const QUEUE_KEY = 'geoasistencia.queue';
const DEVICE_KEY = 'geoasistencia.device';

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function writeQueue(queue) {
    try {
        localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
        return true;
    } catch (e) {
        return false;
    }
}

function deviceId() {
    try {
        let id = localStorage.getItem(DEVICE_KEY);
        if (!id) {
            id = uuid();
            localStorage.setItem(DEVICE_KEY, id);
        }
        return id;
    } catch (e) {
        return null;
    }
}

function gpsMessage(error) {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return 'Permiso de ubicación denegado. Actívalo en el navegador para registrar asistencia.';
        case error.POSITION_UNAVAILABLE:
            return 'No se pudo obtener la ubicación. Sal a un lugar con mejor señal GPS.';
        case error.TIMEOUT:
            return 'El GPS tardó demasiado en responder. Inténtalo de nuevo.';
        default:
            return 'Error de GPS: ' + error.message;
    }
}

/**
 * Registro de entrada/salida con selfie + GPS y cola sin conexión.
 * Las marcaciones hechas sin señal se guardan en localStorage con la hora real de captura
 * y se reproducen en orden contra /my-attendance/sync cuando vuelve la conexión.
 */
export function punchClock(config) {
    let stream = null;

    return {
        nextType: config.nextType,
        cameraOn: false,
        cameraError: null,
        selfie: null,
        position: null,
        gpsError: null,
        locating: false,
        nearest: null,
        online: navigator.onLine,
        queue: readQueue(),
        busy: false,
        syncing: false,
        message: null,
        messageType: 'info',
        lastResult: null,

        init() {
            window.addEventListener('online', () => {
                this.online = true;
                this.sync();
            });
            window.addEventListener('offline', () => (this.online = false));
            this.locate();
            if (this.online && this.queue.length) this.sync();
        },

        async startCamera() {
            this.cameraError = null;
            if (!navigator.mediaDevices?.getUserMedia) {
                this.cameraError = 'Este navegador no permite usar la cámara (se requiere HTTPS).';
                return;
            }
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false,
                });
                this.$refs.video.srcObject = stream;
                await this.$refs.video.play();
                this.cameraOn = true;
            } catch (e) {
                this.cameraError = 'No se pudo acceder a la cámara: ' + e.message;
            }
        },

        stopCamera() {
            stream?.getTracks().forEach((t) => t.stop());
            stream = null;
            this.cameraOn = false;
        },

        capture() {
            const video = this.$refs.video;
            const width = Math.min(640, video.videoWidth || 640);
            const height = Math.round((width * (video.videoHeight || 480)) / (video.videoWidth || 640));
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.translate(width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, width, height);
            this.selfie = canvas.toDataURL('image/jpeg', 0.7);
            this.stopCamera();
        },

        retake() {
            this.selfie = null;
            this.startCamera();
        },

        locate() {
            if (!navigator.geolocation) {
                this.gpsError = 'Este navegador no soporta geolocalización.';
                return;
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.position = {
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        accuracy: Math.round(pos.coords.accuracy),
                    };
                    this.gpsError = null;
                    this.locating = false;
                    this.computeNearest();
                },
                (err) => {
                    this.gpsError = gpsMessage(err);
                    this.locating = false;
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
            );
        },

        computeNearest() {
            if (!this.position || !config.sites.length) {
                this.nearest = null;
                return;
            }
            let best = null;
            for (const site of config.sites) {
                const distance = haversine(this.position.latitude, this.position.longitude, Number(site.latitude), Number(site.longitude));
                if (!best || distance < best.distance) best = { name: site.name, radius: site.radius_m, distance };
            }
            best.distance = Math.round(best.distance);
            best.inside = best.distance <= best.radius;
            this.nearest = best;
        },

        get canSubmit() {
            return !!this.position && (!config.requireSelfie || !!this.selfie) && !this.busy;
        },

        flash(text, type = 'info') {
            this.message = text;
            this.messageType = type;
        },

        async submit() {
            if (!this.canSubmit) return;

            const payload = {
                type: this.nextType,
                ...this.position,
                selfie: this.selfie,
                device_id: deviceId(),
                captured_at: new Date().toISOString(),
            };

            this.busy = true;

            if (!navigator.onLine) {
                this.enqueue(payload);
                this.busy = false;
                return;
            }

            try {
                let res = await postJson(config.punchUrl, payload);
                if (res.status === 419) {
                    await refreshCsrf(config.tokenUrl);
                    res = await postJson(config.punchUrl, payload);
                }
                if (res.ok) {
                    this.lastResult = res.data;
                    this.flash(res.data.message, res.data.out_of_zone ? 'warning' : 'success');
                    this.selfie = null;
                    setTimeout(() => window.location.reload(), 2500);
                } else {
                    this.flash(firstError(res.data), 'error');
                }
            } catch (e) {
                // Sin respuesta del servidor: se trata como falta de señal.
                this.enqueue(payload);
            } finally {
                this.busy = false;
            }
        },

        enqueue(payload) {
            if (!config.allowOffline) {
                this.flash('No hay conexión y la política de RR. HH. no permite registros sin conexión.', 'error');
                return;
            }
            payload.client_id = uuid();
            this.queue.push(payload);
            if (!writeQueue(this.queue)) {
                this.queue.pop();
                this.flash('No hay espacio en el dispositivo para guardar el registro sin conexión.', 'error');
                return;
            }
            const verb = payload.type === 'in' ? 'Entrada' : 'Salida';
            const time = new Date(payload.captured_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
            this.flash(`Sin señal: ${verb} de las ${time} guardada en el dispositivo. Se enviará automáticamente al recuperar la conexión.`, 'warning');
            this.nextType = this.nextType === 'in' ? 'out' : 'in';
            this.selfie = null;
        },

        async sync() {
            if (!this.queue.length || this.syncing || !navigator.onLine) return;
            this.syncing = true;
            try {
                await refreshCsrf(config.tokenUrl);
                const res = await postJson(config.syncUrl, { punches: this.queue });
                if (!res.ok) throw new Error(firstError(res.data));

                const results = res.data.results || [];
                const settled = results.filter((r) => r.ok || r.discard).map((r) => r.client_id);
                const okCount = results.filter((r) => r.ok).length;
                const errors = results.filter((r) => !r.ok).map((r) => r.message);

                this.queue = this.queue.filter((p) => !settled.includes(p.client_id));
                writeQueue(this.queue);

                if (errors.length) {
                    this.flash(`Sincronizados ${okCount} registro(s). Rechazados: ${errors.join(' · ')}`, 'error');
                } else {
                    this.flash(`Conexión recuperada: ${okCount} registro(s) sin conexión sincronizados.`, 'success');
                }
                if (okCount) setTimeout(() => window.location.reload(), 3000);
            } catch (e) {
                this.flash('No se pudo sincronizar la cola todavía: ' + e.message, 'warning');
            } finally {
                this.syncing = false;
            }
        },
    };
}
