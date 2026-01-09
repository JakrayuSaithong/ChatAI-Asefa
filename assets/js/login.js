$(document).ready(function() {
    // Check if already logged in (simple check)
    // Note: A real app might verify token validity on load, but for now we trust storage until expiration check.
    const token = localStorage.getItem('access_token');
    const expireDate = localStorage.getItem('expire_date');

    // --- Security: Disable Inspect & Right Click ---
    document.addEventListener('contextmenu', event => event.preventDefault());
    document.onkeydown = function(e) {
        if (e.keyCode == 123) return false; // F12
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) return false; // Ctrl+Shift+I
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) return false; // Ctrl+Shift+J
        if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false; // Ctrl+U
    };

    // --- URL Token Login ---
    const urlParams = new URLSearchParams(window.location.search);
    const urlToken = urlParams.get('token');

    if (urlToken) {
        setLoading(true);
        fetchUserInfo(urlToken);
    }

    if (token && expireDate && !urlToken) {
        if (new Date(expireDate) > new Date()) {
             window.location.href = 'index';
        }
    }

    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        const username = $('#username').val();
        const password = $('#password').val();
        
        if(!username || !password) return;

        setLoading(true);
        $('#alertMessage').addClass('d-none');

        // 1. Auth API
        $.ajax({
            url: 'https://innovation.asefa.co.th/applications/auth/authen',
            method: 'POST',
            data: {
                ASF_USN: username,
                ASF_PSW: password,
                ASF_APP_ID: "APP"
            },
            success: function(response) {
                // The API returns JSON, verify structure
                // Response: { "token": { "access_token": "...", "expire": ... }, "status": true ... }
                
                // Note: Sometimes APIs return string JSON, need to parse if not auto-parsed
                let data = (typeof response === 'string') ? JSON.parse(response) : response;

                if (data.status === true && data.token && data.token.access_token) {
                    
                    const accessToken = data.token.access_token;
                    const apiExpireDate = data.ExpireDate;
                    if (apiExpireDate && apiExpireDate !== "0000-00-00") {
                        const exp = new Date(apiExpireDate);
                        const today = new Date();
                        if (exp < today) {
                            showError("Your account has expired.");
                            setLoading(false);
                            return;
                        }
                    }

                    // 2. Token API (Get User Info)
                    fetchUserInfo(accessToken);

                } else {
                    showError("Invalid username or password.");
                    setLoading(false);
                }
            },
            error: function(xhr, status, error) {
                console.error("Login Error:", error);
                showError("Login failed. Please check your connection.");
                setLoading(false);
            }
        });
    });

    function fetchUserInfo(accessToken) {
        $.ajax({
            url: 'https://innovation.asefa.co.th/applications/token/authtoken',
            method: 'POST',
            data: {
                token: accessToken
            },
            success: function(response) {
                let data = (typeof response === 'string') ? JSON.parse(response) : response;
                
                // Response: { "ASEFA": true, "DATA": { "Users_Username": "...", "Users_Image": "..." } }
                if (data.ASEFA === true && data.DATA) {
                    // Save to LocalStorage
                    localStorage.setItem('access_token', accessToken);
                    localStorage.setItem('user_username', data.DATA.Users_Username);
                    localStorage.setItem('user_image', data.DATA.Users_Image);
                    
                    // Expire handled by token.expire (int timestamp?) or just set a long local session
                    // Prompt said "expire" in token object is 2147483647 (Integer max).
                    // We can rely on API failure later if token expires.
                    
                    // Redirect
                    window.location.href = 'index';
                } else {
                    showError("Failed to retrieve user information.");
                    setLoading(false);
                }
            },
            error: function() {
                showError("Failed to validate token.");
                setLoading(false);
            }
        });
    }

    function setLoading(isLoading) {
        if (isLoading) {
            $('#loginBtn').prop('disabled', true);
            $('#btnText').addClass('d-none');
            $('#btnSpinner').removeClass('d-none');
        } else {
            $('#loginBtn').prop('disabled', false);
            $('#btnText').removeClass('d-none');
            $('#btnSpinner').addClass('d-none');
        }
    }

    function showError(msg) {
        $('#alertMessage').text(msg).removeClass('d-none');
    }
});
