document.addEventListener('DOMContentLoaded', () => {
    // Add any interactive JavaScript here
    console.log('BOOK-B Loaded');

    // Example: Toggle password visibility
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    if (togglePassword && password) {
        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye slash icon
            this.classList.toggle('bx-show');
            this.classList.toggle('bx-hide');
        });
    }
});
