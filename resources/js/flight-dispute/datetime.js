// Shared date/time formatting for the flight-dispute SPA.
// FlightAware times arrive as ISO 8601 (UTC). Pass an IANA timeZone to show
// airport-local time (what's printed on tickets); omit it for viewer-local.

export function formatDateTime(iso, timeZone = undefined) {
    if (!iso) return null;
    return new Date(iso).toLocaleString(undefined, {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
        ...(timeZone ? { timeZone } : {}),
    });
}

export function formatTime(iso, timeZone = undefined) {
    if (!iso) return null;
    return new Date(iso).toLocaleTimeString(undefined, {
        hour: '2-digit', minute: '2-digit',
        ...(timeZone ? { timeZone } : {}),
    });
}

// "2h 1m" style countdown/duration from milliseconds.
export function formatDuration(ms) {
    const minutes = Math.max(0, Math.round(ms / 60000));
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h && m) return `${h}h ${m}m`;
    if (h) return `${h}h`;
    return `${m} min`;
}
