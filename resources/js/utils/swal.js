import Swal from 'sweetalert2';

/**
 * Confirm action with SweetAlert2 (replaces window.confirm)
 * @param {Object} options - Swal options
 * @returns {Promise<boolean>} - true if confirmed, false if cancelled
 */
export async function confirm(options = {}) {
    const result = await Swal.fire({
        icon: options.icon || 'warning',
        title: options.title || 'Are you sure?',
        text: options.text || '',
        showCancelButton: true,
        confirmButtonText: options.confirmText || 'Yes, proceed',
        cancelButtonText: options.cancelText || 'Cancel',
        confirmButtonColor: options.confirmColor || '#2563eb',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        ...options,
    });
    return result.isConfirmed;
}

/**
 * Success toast notification
 */
export function toastSuccess(message, options = {}) {
    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        ...options,
    });
}

/**
 * Error toast notification
 */
export function toastError(message, options = {}) {
    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        ...options,
    });
}
