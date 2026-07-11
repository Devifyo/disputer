// App-wide confirmation dialogs, styled like the rest of the web app
// (SweetAlert2 is loaded globally in layouts/app.blade.php).

function confirm({ title, text, confirmText, icon = 'warning' }) {
    if (!window.Swal) {
        return Promise.resolve(window.confirm(`${title}\n${text}`));
    }
    return window.Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonColor: '#0f172a',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: confirmText,
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

export function confirmRemove(title, text) {
    return confirm({ title, text, confirmText: 'Yes, remove it' });
}

export function confirmAction(title, text, confirmText = 'Yes, continue') {
    return confirm({ title, text, confirmText });
}

/**
 * Blocking "we're working on it" dialog with a spinner, for actions that
 * take a few seconds (e.g. the live eligibility review). Returns a function
 * that closes the dialog.
 */
export function showProcessing(title, text) {
    if (!window.Swal) return () => {};

    window.Swal.fire({
        title,
        text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        heightAuto: false,
        scrollbarPadding: false,
        customClass: { popup: 'rounded-2xl' },
        didOpen: () => window.Swal.showLoading(),
    });

    return () => window.Swal.close();
}
