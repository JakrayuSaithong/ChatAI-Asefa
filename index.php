<?php
// $logFile = __DIR__ . '/url_access.log';

// $fullUrl =
//     (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
//     . '://' . $_SERVER['HTTP_HOST']
//     . $_SERVER['REQUEST_URI'];

// $time = date('Y-m-d H:i:s');

// file_put_contents(
//     $logFile,
//     "[$time] $fullUrl\n",
//     FILE_APPEND | LOCK_EX
// );
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AsefaChat AI</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Highlight.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github-dark.min.css">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        p {
            margin-bottom: 0;
        }

        /* Model Dropdown Styles */
        .dropdown-menu.model-menu {
            max-height: 400px;
            overflow-y: auto;
            min-width: 260px;
        }

        .dropdown-menu.model-menu .dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
        }

        .dropdown-menu.model-menu .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-menu.model-menu .dropdown-item.active {
            background-color: #0d6efd;
            color: white;
        }

        .dropdown-menu.model-menu .dropdown-item.disabled,
        .dropdown-menu.model-menu .dropdown-item.user-restricted {
            opacity: 0.5;
            pointer-events: none;
            display: none !important;
        }

        .dropdown-menu.model-menu .dropdown-header {
            font-size: 0.7rem;
            color: #6c757d;
            padding: 0.5rem 1rem;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .model-badge {
            font-size: 0.6rem;
            padding: 0.2em 0.5em;
            margin-left: auto;
        }

        .model-icon {
            width: 20px;
            margin-right: 8px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="app-container">
        <!-- Sidebar - Offcanvas on mobile, static on desktop -->
        <aside class="sidebar offcanvas-lg offcanvas-start p-3" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-2 justify-content-center" id="sidebarLabel">
                    <img src="assets/img/Left - Blue.png" alt="Logo_Asefa" class="w-75">
                </div>
                <button type="button" class="btn btn-link text-secondary p-0 d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close">
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
                    <div class="flex-grow-1">
                        <div class="fw-bold small text-truncate" id="userName">User</div>
                        <div class="dropup d-none">
                            <a href="#" class="text-secondary small text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">Account</a>
                            <ul class="dropdown-menu" aria-labelledby="profileDropdown">
                                <li><a class="dropdown-item" href="#" id="settingsBtn"><i class='bx bx-cog me-2'></i>Settings</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="#" id="logoutBtn"><i class='bx bx-log-out me-2'></i>Sign out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="chat-area">
            <header class="chat-header">
                <div class="d-flex align-items-center gap-3">
                    <!-- Mobile: Offcanvas toggle -->
                    <button class="btn btn-link text-secondary p-0 d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                        <i class='bx bx-menu fs-4'></i>
                    </button>
                    <!-- Desktop: Collapse/Expand toggle -->
                    <button class="btn btn-link text-secondary p-0 d-none d-lg-block" type="button" id="toggleSidebarDesktop" title="Toggle Sidebar">
                        <i class='bx bx-menu fs-4'></i>
                    </button>


                    <div class="dropdown d-none">
                        <button class="btn btn-link text-dark text-decoration-none fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="modelSelector">
                            Claude Sonnet 4
                        </button>
                        <ul class="dropdown-menu model-menu">


                            <li>
                                <h6 class="dropdown-header">Claude</h6>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-model="anthropic/claude-sonnet-4">
                                    <span class="model-icon"><i class='bx bx-brain'></i></span>
                                    Claude Sonnet 4
                                    <span class="badge bg-success model-badge">Default</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-model="anthropic/claude-3.5-sonnet">
                                    <span class="model-icon"><i class='bx bx-brain'></i></span>
                                    Claude 3.5 Sonnet
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-model="anthropic/claude-3-haiku">
                                    <span class="model-icon"><i class='bx bx-zap'></i></span>
                                    Claude 3 Haiku
                                    <span class="badge bg-info model-badge">Fast</span>
                                </a>
                            </li>

                            <!-- ========== MODELS สำหรับ SUPER USER เท่านั้น ========== -->
                            <!-- จะถูกซ่อนโดย JS สำหรับ user ทั่วไป -->

                            <li class="super-user-only">
                                <hr class="dropdown-divider">
                            </li>

                            <!-- Gemini Models -->
                            <li class="super-user-only">
                                <h6 class="dropdown-header">Gemini</h6>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item " href="#" data-model="google/gemini-2.5-pro">
                                    <span class="model-icon"><i class='bx bx-diamond'></i></span>
                                    Gemini 2.5 Pro
                                </a>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item" href="#" data-model="google/gemini-2.5-flash">
                                    <span class="model-icon"><i class='bx bx-bolt'></i></span>
                                    Gemini 2.5 Flash
                                    <span class="badge bg-info model-badge">Fast</span>
                                </a>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item" href="#" data-model="google/gemini-3-flash-preview">
                                    <span class="model-icon"><i class='bx bx-rocket'></i></span>
                                    Gemini 3 Flash
                                    <span class="badge bg-success model-badge">New</span>
                                </a>
                            </li>

                            <li class="super-user-only">
                                <a class="dropdown-item" href="#" data-model="google/gemini-3-pro">
                                    <span class="model-icon"><i class='bx bx-rocket'></i></span>
                                    Gemini 3 PRO
                                    <span class="badge bg-success model-badge">New</span>
                                </a>
                            </li>


                            <li class="super-user-only">
                                <hr class="dropdown-divider">
                            </li>

                            <!-- Perplexity Models -->
                            <li class="super-user-only">
                                <h6 class="dropdown-header">Perplexity (Built-in Search)</h6>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item active" href="#" data-model="perplexity/sonar-pro">
                                    <span class="model-icon"><i class='bx bx-search-alt'></i></span>
                                    Sonar Pro
                                    <span class="badge bg-primary model-badge">Search</span>
                                </a>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item" href="#" data-model="perplexity/sonar-reasoning">
                                    <span class="model-icon"><i class='bx bx-analyse'></i></span>
                                    Sonar Reasoning
                                </a>
                            </li>

                            <li class="super-user-only">
                                <hr class="dropdown-divider">
                            </li>

                            <!-- OpenAI Models -->
                            <li class="super-user-only">
                                <h6 class="dropdown-header">OpenAI (Web Search)</h6>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item" href="#" data-model="openai/gpt-4o">
                                    <span class="model-icon"><i class='bx bx-globe'></i></span>
                                    GPT-4o
                                </a>
                            </li>
                            <li class="super-user-only">
                                <a class="dropdown-item" href="#" data-model="openai/gpt-4o-mini">
                                    <span class="model-icon"><i class='bx bx-chip'></i></span>
                                    GPT-4o Mini
                                    <span class="badge bg-secondary model-badge">Budget</span>
                                </a>
                            </li>

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
                <div id="filePreviewArea" class="d-flex gap-2 flex-wrap mb-2 d-none">
                    <!-- Preview items will be injected here -->
                </div>
                <div class="input-container d-flex align-items-end position-relative">
                    <input type="file" id="fileInput" class="d-none" multiple>
                    <button class="btn text-secondary border-0 p-2 m-1" id="attachBtn" type="button" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-content="เพิ่มรูปภาพและไฟล์" data-bs-placement="top">
                        <i class='bx bx-paperclip fs-4'></i>
                    </button>
                    <textarea class="form-control ps-0" id="chatInput" placeholder="Message AsefaChat..." rows="1" style="border-top-left-radius: 0; border-bottom-left-radius: 0;"></textarea>
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

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-slider-alt me-2'></i>ตั้งค่าบุคลิก AI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small mb-3">กำหนดลักษณะนิสัยและการตอบโต้ของ AI ตามที่คุณต้องการ</p>

                    <form id="settingsForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">บุคลิกและน้ำเสียง</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class='bx bx-user'></i></span>
                                <input type="text" class="form-control" id="settingPersonality" placeholder="เช่น เป็นกันเอง, ทางการ, ทันสมัย">
                            </div>
                            <div class="input-group">
                                <span class="input-group-text"><i class='bx bx-volume-full'></i></span>
                                <input type="text" class="form-control" id="settingTone" placeholder="เช่น สุภาพ, สนุกสนาน, วิชาการ">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">หัวข้อที่สนใจเป็นพิเศษ</label>
                            <input type="text" class="form-control" id="settingTopics" placeholder="เช่น วิศวกรรม, Programming, ความรู้ทั่วไป">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">การใช้อีโมจิ</label>
                            <select class="form-select" id="settingEmoji">
                                <option value="Min">ค่าเริ่มต้น (ใช้น้อย)</option>
                                <option value="None">ไม่ใช้เลย</option>
                                <option value="Max">ใช้เยอะ (เน้นอารมณ์)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">คำสั่งเพิ่มเติม (Custom Instructions)</label>
                            <textarea class="form-control" id="settingCustom" rows="3" placeholder="ระบุเงื่อนไขเพิ่มเติมที่ต้องการ..."></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="presetDefault">ค่าเริ่มต้น</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="presetLess">ทางการ</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="presetMore">สร้างสรรค์</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="saveSettingsBtn">บันทึก</button>
                </div>
            </div>
        </div>
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