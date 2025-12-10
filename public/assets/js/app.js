document.addEventListener('DOMContentLoaded', function() {
    // Derive auth URL from window.AUTH_URL (set by header.php)
    const authUrl = window.AUTH_URL || '/middleware/auth.php';
    console.log('AUTH_URL from header:', authUrl);

    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const alertEl = document.getElementById('loginAlert');
            alertEl.innerHTML = '';
            
            const formData = new FormData(loginForm);
            
            fetch(authUrl, { 
                method: 'POST', 
                body: formData, 
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Auth response status:', response.status);
                return response.json();
            })
            .then(json => {
                console.log('Auth response JSON:', json);
                if (json.success) {
                    alertEl.innerHTML = '<div class="alert alert-success">' + (json.message || 'Logged in successfully') + '</div>';
                    // Navigate to dashboard based on role
                    setTimeout(() => {
                        const appRoot = window.APP_ROOT || '';
                        const roleMap = {
                            1: 'superadmin-dashboard',
                            2: 'admin-dashboard',
                            3: 'staff-dashboard',
                            4: 'user-dashboard'
                        };
                        const nav = roleMap[json.role] || 'user-dashboard';
                        let dashboardUrl;
                        if (appRoot && appRoot !== '/') {
                            dashboardUrl = appRoot + '/index.php?nav=' + nav;
                        } else {
                            dashboardUrl = '/index.php?nav=' + nav;
                        }
                        console.log('Redirecting to:', dashboardUrl);
                        window.location.href = dashboardUrl;
                    }, 800);
                } else {
                    alertEl.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Login failed') + '</div>';
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alertEl.innerHTML = '<div class="alert alert-danger">Network error: ' + err.message + '</div>';
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
            
            const formData = new FormData(registerForm);
            
            fetch(authUrl, { 
                method: 'POST', 
                body: formData, 
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Register response status:', response.status);
                return response.json();
            })
            .then(json => {
                console.log('Register response JSON:', json);
                if (json.success) {
                    alertEl.innerHTML = '<div class="alert alert-success">' + (json.message || 'Registered successfully') + '</div>';

                    const registerModalEl = document.getElementById('registerModal');
                    const loginModalEl = document.getElementById('loginModal');

                    // When register modal finishes hiding, show login
                    const showLoginAfterHide = () => {
                        registerModalEl.removeEventListener('hidden.bs.modal', showLoginAfterHide);
                        const loginModal = new bootstrap.Modal(loginModalEl);
                        loginModal.show();
                    };

                    // Blur any focused element inside the modal to avoid aria-hidden/focus conflict
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }

                    const registerModal = bootstrap.Modal.getInstance(registerModalEl);
                    if (registerModal) {
                        registerModalEl.addEventListener('hidden.bs.modal', showLoginAfterHide, { once: true });
                        registerModal.hide();
                    } else {
                        // Fallback: directly show login if modal instance missing
                        showLoginAfterHide();
                    }
                } else {
                    alertEl.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Registration failed') + '</div>';
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                alertEl.innerHTML = '<div class="alert alert-danger">Network error: ' + err.message + '</div>';
            });
        });
    }

    // Highlight active nav link based on current page
    const urlParams = new URLSearchParams(window.location.search);
    const currentNav = urlParams.get('nav');
    if (currentNav) {
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navLinks.forEach(link => {
            if (link.getAttribute('href') && link.getAttribute('href').includes('nav=' + currentNav)) {
                link.classList.add('active');
            }
        });
    }
});
