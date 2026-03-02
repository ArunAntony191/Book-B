const Popup = {
    overlay: null,
    content: null,

    init() {
        if (this.overlay) return;

        this.overlay = document.createElement('div');
        this.overlay.className = 'popup-overlay';
        this.overlay.innerHTML = `
            <div class="popup-content">
                <div class="popup-icon"></div>
                <h2 class="popup-title"></h2>
                <p class="popup-message"></p>
                <div class="popup-input-container" style="display: none;">
                    <input type="text" class="popup-input">
                </div>
                <div class="popup-actions"></div>
            </div>
        `;
        document.body.appendChild(this.overlay);

        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) this.close(null);
        });

        // Add escape key support
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.classList.contains('active')) {
                this.close(null);
            }
        });
    },

    show({ title, message, type = 'info', showInput = false, placeholder = '', confirmText = 'OK', cancelText = 'Cancel', showCancel = false }) {
        this.init();

        const iconDiv = this.overlay.querySelector('.popup-icon');
        const titleH2 = this.overlay.querySelector('.popup-title');
        const messageP = this.overlay.querySelector('.popup-message');
        const inputContainer = this.overlay.querySelector('.popup-input-container');
        const input = this.overlay.querySelector('.popup-input');
        const actionsDiv = this.overlay.querySelector('.popup-actions');

        // Set content
        titleH2.textContent = title;
        messageP.textContent = message;

        // Set icon
        let iconClass = 'bx-info-circle';
        if (type === 'success') iconClass = 'bx-check-circle';
        if (type === 'error') iconClass = 'bx-error-circle';
        if (type === 'warning') iconClass = 'bx-error';
        if (type === 'question') iconClass = 'bx-help-circle';

        iconDiv.className = `popup-icon ${type}`;
        iconDiv.innerHTML = `<i class='bx ${iconClass}'></i>`;

        // Handle Input
        if (showInput) {
            inputContainer.style.display = 'block';
            input.value = '';
            input.placeholder = placeholder;
            setTimeout(() => input.focus(), 100);
        } else {
            inputContainer.style.display = 'none';
        }

        // Handle Actions
        actionsDiv.innerHTML = '';

        if (showCancel) {
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'popup-btn popup-btn-outline';
            cancelBtn.textContent = cancelText;
            cancelBtn.onclick = () => this.close(null);
            actionsDiv.appendChild(cancelBtn);
        }

        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'popup-btn popup-btn-primary';
        confirmBtn.textContent = confirmText;
        confirmBtn.onclick = () => {
            const value = showInput ? input.value : true;
            this.close(value);
        };
        actionsDiv.appendChild(confirmBtn);

        // Show overlay
        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';

        return new Promise((resolve) => {
            this.resolve = resolve;
        });
    },

    close(value) {
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
        if (this.resolve) {
            this.resolve(value);
            this.resolve = null;
        }
    },

    alert(title, message, type = 'info') {
        return this.show({ title, message, type });
    },

    confirm(title, message, options = {}) {
        return this.show({
            title,
            message,
            type: 'question',
            showCancel: true,
            confirmText: options.confirmText || 'Confirm',
            cancelText: options.cancelText || 'Cancel'
        });
    },

    prompt(title, message, placeholder = '') {
        return this.show({
            title,
            message,
            type: 'question',
            showInput: true,
            showCancel: true,
            placeholder
        });
    }
};
