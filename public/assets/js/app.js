document.addEventListener('DOMContentLoaded', function() {
    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const alertEl = document.getElementById('loginAlert');
            alertEl.innerHTML = '';
            const fd = new FormData(loginForm);
            fetch('/middleware/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alertEl.innerHTML = '<div class="alert alert-success">' + (json.message || 'Logged in') + '</div>';
                        setTimeout(() => location.reload(), 800);
                    } else {
                        alertEl.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Login failed') + '</div>';
                    }
                })
                .catch(err => {
                    alertEl.innerHTML = '<div class="alert alert-danger">Network error</div>';
                });
        });
    }

    // Register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const alertEl = document.getElementById('registerAlert');
            alertEl.innerHTML = '';
            const fd = new FormData(registerForm);
            fetch('/middleware/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alertEl.innerHTML = '<div class="alert alert-success">' + (json.message || 'Registered') + '</div>';
                        setTimeout(() => {
                            // close modal and open login modal
                            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                            var registerModalEl = document.getElementById('registerModal');
                            var registerModal = bootstrap.Modal.getInstance(registerModalEl);
                            if (registerModal) registerModal.hide();
                            loginModal.show();
                        }, 1000);
                    } else {
                        alertEl.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Registration failed') + '</div>';
                    }
                })
                .catch(err => {
                    alertEl.innerHTML = '<div class="alert alert-danger">Network error</div>';
                });
        });
    }
});
