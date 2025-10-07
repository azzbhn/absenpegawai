// Fungsi utilitas untuk website absensi

// Format tanggal Indonesia
function formatTanggal(tanggal) {
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    return new Date(tanggal).toLocaleDateString('id-ID', options);
}

// Format waktu
function formatWaktu(waktu) {
    return new Date('1970-01-01T' + waktu + 'Z').toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Validasi form
function validateForm(formData) {
    const errors = [];
    
    if (!formData.get('username') || !formData.get('password')) {
        errors.push('Username dan password harus diisi');
    }
    
    return errors;
}

// Notifikasi sukses
function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: message,
        timer: 3000,
        showConfirmButton: false
    });
}

// Notifikasi error
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        timer: 5000
    });
}

// Konfirmasi aksi
function confirmAction(message, callback) {
    Swal.fire({
        title: 'Konfirmasi',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            callback();
        }
    });
}

// Debounce function untuk search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export fungsi ke global scope
window.absensiUtils = {
    formatTanggal,
    formatWaktu,
    validateForm,
    showSuccess,
    showError,
    confirmAction,
    debounce
};