function showToast(message, type = 'success', duration = 3000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    // Add icon based on type
    let icon = 'bx-check-circle';
    if (type === 'error') icon = 'bx-error-circle';
    if (type === 'warning') icon = 'bx-error';
    if (type === 'info') icon = 'bx-info-circle';
    
    toast.innerHTML = `
        <i class='bx ${icon}'></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    // Auto remove
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => {
            toast.remove();
            if (container.childNodes.length === 0) {
                container.remove();
            }
        }, 300;
    }, duration);
}

// Override window.alert for legacy support (optional but helpful)
// window.alert = (msg) => showToast(msg, 'info');
