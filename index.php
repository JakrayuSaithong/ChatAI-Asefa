<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AsefaChat AI</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Highlight.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github-dark.min.css">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        p {
            margin-bottom: 0;
        }
    </style>
</head>

<body>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar p-3" id="sidebar">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    <!-- <i class='bx bxs-bot text-primary fs-4'></i>
                    <span class="fw-bold text-primary">AsefaChat</span> -->
                    <img src="assets/img/Left - Blue.png" alt="Logo_Asefa" class="w-75">
                </div>
                <button class="btn btn-link text-secondary p-0 d-md-none" id="closeSidebar">
                    <i class='bx bx-x fs-4'></i>
                </button>
            </div>

            <button class="btn new-chat-btn w-100 mb-3 rounded-pill py-2 text-start px-3 shadow-sm" id="newChatBtn">
                <i class='bx bx-plus me-2'></i> New Chat
            </button>

            <div class="text-secondary small fw-bold mb-2 ps-2">Chat</div>
            <div class="history-list" id="historyList">
                <!-- Chat history items will be injected here -->
            </div>

            <div class="mt-auto border-top pt-3">
                <div class="d-flex align-items-center gap-2 px-2">
                    <img src="assets/img/default-avatar.svg" alt="User" class="avatar" id="userAvatar">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-bold small text-truncate" id="userName">User</div>
                        <a href="#" class="text-secondary small text-decoration-none" id="logoutBtn">Log out</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="chat-area">
            <header class="chat-header">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-link text-secondary p-0" id="toggleSidebar">
                        <i class='bx bx-menu fs-4'></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-link text-dark text-decoration-none fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="modelSelector">
                            Gemini 3.0 27B
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-model="openai/gpt-5-mini">GPT 5 mini</a></li>
                            <li><a class="dropdown-item" href="#" data-model="openai/gpt-4o-mini">GPT 4o mini</a></li>
                            <li><a class="dropdown-item active" href="#" data-model="google/gemma-3-27b-it:free">Gemini 3.0 27B</a></li>
                            <li><a class="dropdown-item" href="#" data-model="meta-llama/llama-3.3-70b-instruct:free">Llama 3.3 70B</a></li>
                            <li><a class="dropdown-item" href="#" data-model="google/gemini-3-flash-preview">Gemini 3.0 Flash Preview</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="messages-container" id="messagesContainer">
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center text-secondary opacity-50" id="welcomeMessage">
                    <i class='bx bxs-bot fs-1 mb-3'></i>
                    <h4>มีอะไรให้ฉันช่วยวันนี้?</h4>
                </div>
                <!-- Messages will be injected here -->
            </div>

            <div class="input-area-wrapper">
                <div class="input-container d-flex align-items-end">
                    <textarea class="form-control" id="chatInput" placeholder="Message AsefaChat..." rows="1"></textarea>
                    <button class="send-btn" id="sendBtn" disabled>
                        <i class='bx bxs-send'></i>
                    </button>
                </div>
                <div class="text-center mt-2">
                    <small class="text-secondary" style="font-size: 0.7rem;">AI can make mistakes. Please check important info.</small>
                </div>
            </div>
        </main>
    </div>

    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Markdown Parser (Marked) -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- Highlight.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- App Logic -->
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/login.js?v=<?php echo time(); ?>"></script>

</body>

</html>