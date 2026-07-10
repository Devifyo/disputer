// Shared date/time formatting for the flight-dispute SPA.
// FlightAware times arrive as ISO 8601 (UTC) and are shown in the
// viewer's local timezone.

export function formatDateTime(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleString(undefined, {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    });
}

export function formatTime(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}
