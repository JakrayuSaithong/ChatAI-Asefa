<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AsefaChatAI</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

    <div class="login-container">
        <div class="login-card glass-panel shadow-lg">
            <div class="text-center mb-4">
                <i class='bx bxs-bot fs-1 text-primary mb-2'></i>
                <h4 class="fw-bold text-primary">AsefaChat AI</h4>
                <p class="text-secondary small">Sign in to start your session</p>
            </div>

            <form id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label small fw-bold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class='bx bxs-user'></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="username" placeholder="Enter username" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class='bx bxs-lock-alt'></i></span>
                        <input type="password" class="form-control border-start-0 ps-0" id="password" placeholder="Enter password" required>
                    </div>
                </div>

                <div id="alertMessage" class="alert alert-danger d-none py-2 small" role="alert"></div>

                <button type="submit" class="btn btn-primary-custom w-100 rounded-3" id="loginBtn">
                    <span id="btnText">Sign In</span>
                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </form>
        </div>
    </div>

    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Login Logic -->
    <script src="assets/js/login.js"></script>
</body>

</html>