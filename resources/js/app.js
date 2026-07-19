// SMARTSIS global frontend helpers.
//
// SweetAlert2 is the single source for delete confirmations and status toasts
// (see LIBRARY.md / UI_STANDARDS.md). The library itself is loaded globally via
// CDN in partials/head.blade.php, so here we only wire up the helpers.

/**
 * Show the standard "are you sure?" delete confirmation, then run the callback
 * only if the user confirms.
 *
 * Usage in Blade (inside a Livewire component):
 *   <button @click="confirmDelete(() => $wire.delete({{ $row->id }}))">…</button>
 *
 * @param {() => void} onConfirm  Runs when the user accepts.
 * @param {{title?: string, text?: string, confirmButtonText?: string, cancelButtonText?: string}} [options]
 */
window.confirmDelete = function confirmDelete(onConfirm, options = {}) {
    window.Swal.fire({
        title: options.title ?? 'Apakah Anda yakin?',
        text: options.text ?? 'Data yang dihapus tidak dapat dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        reverseButtons: true,
        confirmButtonColor: '#441daa', // brand violet (primary-600)
        cancelButtonColor: '#6b7280', // neutral gray
        confirmButtonText: options.confirmButtonText ?? 'Ya, hapus',
        cancelButtonText: options.cancelButtonText ?? 'Batal',
    }).then((result) => {
        if (result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
};

/**
 * Status feedback toast for in-page (AJAX) Livewire actions, triggered via:
 *   $this->dispatch('swal', icon: 'success', title: __('Tersimpan'));
 *
 * Redirect flows use realrashid/sweet-alert instead: call toast()/alert() in
 * PHP and the @include('sweetalert::alert') in the layouts renders it after
 * navigation.
 */
window.addEventListener('swal', (event) => {
    const detail = (Array.isArray(event.detail) ? event.detail[0] : event.detail) ?? {};
    const { icon = 'success', title = '' } = detail;

    window.Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
});
