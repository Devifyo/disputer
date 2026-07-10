// App-wide delete confirmation, styled like the rest of the web app
// (SweetAlert2 is loaded globally in layouts/app.blade.php).
export function confirmRemove(title, text) {
    if (!window.Swal) {
        return Promise.resolve(window.confirm(`${title}\n${text}`));
    }
    return window.Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, remove it',
        cancelButtonText: 'Cancel',
        heightAuto: false,
        scrollbarPadding: false,
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: 'px-6 py-2.5 rounded-xl font-bold text-sm',
            cancelButton: 'px-6 py-2.5 rounded-xl font-bold text-sm',
        },
    }).then((result) => result.isConfirmed);
}
