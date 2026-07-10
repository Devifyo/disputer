import axios from 'axios';

const el = document.getElementById('flight-dispute-app');
const BASE = el?.dataset.base
    ? new URL(el.dataset.base, window.location.origin).pathname
    : '/flight-disputes';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

const http = axios.create({
    baseURL: `${BASE}/api`,
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
        Accept: 'application/json',
    },
});

export default {
    claims: {
        list() {
            return http.get('/claims').then((r) => r.data.data);
        },
        get(id) {
            return http.get(`/claims/${id}`).then((r) => r.data.data);
        },
        create(payload) {
            return http.post('/claims', payload).then((r) => r.data.data);
        },
    },
    // PDF / photo itinerary upload — creates an itinerary + one claim per passenger.
    uploadItinerary(file, onProgress) {
        const form = new FormData();
        form.append('file', file);
        return http.post('/upload', form, { onUploadProgress: onProgress });
    },
    // "Protect Your Trip" — future trips monitored by Unjamm.
    trips: {
        list() {
            return http.get('/trips').then((r) => r.data.data);
        },
        get(id) {
            return http.get(`/trips/${id}`).then((r) => r.data.data);
        },
        create(payload) {
            return http.post('/trips', payload).then((r) => r.data);
        },
        upload(file, onProgress) {
            const form = new FormData();
            form.append('file', file);
            return http.post('/trips/upload', form, { onUploadProgress: onProgress }).then((r) => r.data);
        },
        remove(id) {
            return http.delete(`/trips/${id}`).then((r) => r.data);
        },
        // FlightAware monitoring history (detected events + poll log).
        monitoring(id) {
            return http.get(`/trips/${id}/monitoring`).then((r) => r.data.data);
        },
        // Manual "refresh now" — returns the updated trip.
        sync(id) {
            return http.post(`/trips/${id}/sync`).then((r) => r.data.data);
        },
        // Eligible trip → compensation claims (one per passenger).
        createClaim(id) {
            return http.post(`/trips/${id}/claim`).then((r) => r.data);
        },
    },
};
