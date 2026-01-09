$(document).ready(function() {
    // --- Security: Disable Inspect & Right Click ---
    document.addEventListener('contextmenu', event => event.preventDefault());
    document.onkeydown = function(e) {
        if (e.keyCode == 123) return false; // F12
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) return false; // Ctrl+Shift+I
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) return false; // Ctrl+Shift+J
        if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false; // Ctrl+U
    };

    // --- Authentication ---
    const accessToken = localStorage.getItem('access_token');
    const userImg = localStorage.getItem('user_image');
    const userName = localStorage.getItem('user_username');

    // if (!accessToken) {
    //     window.location.href = 'login';
    //     return;
    // }

    if (userImg) $('#userAvatar').attr('src', userImg);
    if (userName) $('#userName').text(userName);

    $('#logoutBtn').on('click', function(e) {
        e.preventDefault();
        localStorage.clear();
        window.location.href = 'login';
    });

    // --- Sidebar ---
    $('#toggleSidebar, #closeSidebar').on('click', () => $('#sidebar').toggleClass('collapsed'));
    $(window).resize(() => {
        if ($(window).width() > 768) $('#sidebar').removeClass('collapsed');
        else $('#sidebar').addClass('collapsed');
    });
    if ($(window).width() <= 768) $('#sidebar').addClass('collapsed');

    // --- Chat Logic ---
    let currentModel = 'google/gemma-3-27b-it:free';
    let currentSessionId = null;
    let chatHistoryContext = [];
    let isSending = false; // Throttling Lock

    loadHistory();

    $('#chatInput').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';

        if ($(this).val().trim().length > 0 && !isSending) {
            $('#sendBtn').prop('disabled', false);
        } else {
            $('#sendBtn').prop('disabled', true);
        }
    });

    $('#chatInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleUserMessage();
        }
    });

    function uid(prefix = 'msg') {
        if (window.crypto && crypto.randomUUID) {
            return `${prefix}-${crypto.randomUUID()}`;
        }

        return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    $('#sendBtn').on('click', handleUserMessage);
    $('#newChatBtn').on('click', startNewChat);

    $('.dropdown-item').on('click', function(e) {
        e.preventDefault();
        $('.dropdown-item').removeClass('active');
        $(this).addClass('active');
        currentModel = $(this).data('model');
        $('#modelSelector').text($(this).text());
    });

    $(document).on('click', '.history-item', function(e) {
        if (isSending) return; // Prevent switching while generating
        if ($(e.target).closest('.delete-chat').length) return;
        const sessionId = $(this).data('id');
        loadChatSession(sessionId);
        $('.history-item').removeClass('active');
        $(this).addClass('active');
        if ($(window).width() <= 768) $('#sidebar').addClass('collapsed');
    });

    $(document).on('click', '.delete-chat', function(e) {
        e.stopPropagation();
        if (isSending) return;
        const sessionId = $(this).closest('.history-item').data('id');
        deleteChat(sessionId);
    });

    // --- Core Functions ---

    function startNewChat() {
        if (isSending) return;
        currentSessionId = null;
        chatHistoryContext = [];
        $('#messagesContainer').empty();
        $('#messagesContainer').append(`
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center text-secondary opacity-50" id="welcomeMessage">
                <i class='bx bxs-bot fs-1 mb-3'></i>
                <h4>How can I help you today?</h4>
            </div>
        `);
        $('.history-item').removeClass('active');
        $('#chatInput').focus();
    }

    async function handleUserMessage() {
        if (isSending) return;

        const input = $('#chatInput');
        const content = input.val().trim();
        if (!content) return;

        // Lock & UI
        isSending = true;
        input.val('');
        input.css('height', 'auto');
        $('#sendBtn').prop('disabled', true);
        $('#welcomeMessage').addClass('d-none');

        addMessage('user', content);

        // Placeholder for AI streaming message
        const aiMsgId = uid('ai-msg');
        addMessageContainer('model', aiMsgId);

        let fullAiResponse = "";
        let retryStatusId = null;

        const nowTH = new Date().toLocaleString("th-TH", {
            timeZone: "Asia/Bangkok",
            dateStyle: "full",
            timeStyle: "medium"
        });

        try {
            const contextMessages = chatHistoryContext.slice(-10);
            const messagesPayload = [
                { 
                    role: "system", 
                    content: `
                    คำสั่งคือ
                    - ตอบเป็นภาษาไทย
                    - วันนี้คือ ${nowTH}
                    - ถ้าถามเรื่องใหม่แล้ว ไม่ต้องอ้างอิงถึงเรื่องเก่า
                    ` 
                },
                ...contextMessages,
                { 
                    role: "user", 
                    content: content 
                }
            ];

            // console.log(currentModel);

            const response = await fetch('api/chat_stream.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: currentModel,
                    messages: messagesPayload,
                    username: userName,
                    session_id: currentSessionId
                })
            });

            if (!response.ok || !response.body) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            
            // Refresh history to update title/sorting immediately
            loadHistory();

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            // Robust SSE buffer (prevents Parse Error when chunk splits mid-event)
            let sseBuffer = "";

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                sseBuffer += decoder.decode(value, { stream: true });

                let idx;
                while ((idx = sseBuffer.indexOf("\n\n")) !== -1) {
                    const eventBlock = sseBuffer.slice(0, idx);
                    sseBuffer = sseBuffer.slice(idx + 2);

                    const line = eventBlock.trim();
                    if (!line.startsWith('data: ')) continue;

                    const jsonText = line.slice(6);
                    if (jsonText === '[DONE]') continue;

                    try {
                        const payload = JSON.parse(jsonText);

                        if (payload.type === 'content') {
                            fullAiResponse += payload.data;
                            updateMessageContent(aiMsgId, fullAiResponse);
                            if (retryStatusId) { $(`#${retryStatusId}`).remove(); retryStatusId = null; }
                        } else if (payload.type === 'session_id') {
                            if (!currentSessionId) {
                                currentSessionId = payload.data;
                                loadHistory();
                            }
                        } else if (payload.type === 'status') {
                            if (!retryStatusId) {
                                retryStatusId = 'retry-' + Date.now();
                                $(`#${aiMsgId}`).after(`<div id="${retryStatusId}" class="text-warning small ms-5 mb-2"><i class='bx bx-loader-alt bx-spin'></i> ${payload.data}</div>`);
                            } else {
                                $(`#${retryStatusId}`).html(`<i class='bx bx-loader-alt bx-spin'></i> ${payload.data}`);
                            }
                        } else if (payload.type === 'error') {
                            throw new Error(payload.data);
                        }
                    } catch (e) {
                        console.error("Parse Error", e, { jsonText });
                    }
                }
            }

            // Update Context
            chatHistoryContext.push({ role: 'user', content: content });
            chatHistoryContext.push({ role: 'assistant', content: fullAiResponse });

        } catch (error) {
            console.error("Stream Error:", error);
            
            let userMsg = "เกิดข้อผิดพลาด กรุณาลองใหม่ภายหลัง"; // Default: An error occurred
            const errMsg = error.message || "";
            
            if (errMsg.includes("429") || errMsg.includes("Server busy")) {
                userMsg = "ขณะนี้มีผู้ใช้งานจำนวนมาก กรุณารอสักครู่แล้วลองใหม่";
            } else if (errMsg.includes("404") || errMsg.includes("Not Found")) {
                userMsg = "โมเดลนี้ไม่สามารถใช้งานได้ หรือไม่มีอยู่จริง";
            } else if (errMsg.includes("401") || errMsg.includes("Unauthorized")) {
                userMsg = "เกิดข้อผิดพลาดเกี่ยวกับสิทธิ์การเข้าถึง (API Key)";
            } else if (errMsg.includes("400")) {
                userMsg = "คำขอไม่ถูกต้อง (Bad Request)";
            }

            updateMessageContent(aiMsgId, (fullAiResponse || "") + "\n\n**ข้อผิดพลาด:** " + userMsg);
        } finally {
            if (retryStatusId) $(`#${retryStatusId}`).remove();
            isSending = false;
        }
    }

    function addMessageContainer(role, id) {
        const isUser = role === 'user';
        const avatar = isUser ? (userImg || 'assets/img/default-avatar.svg') : 'assets/img/ai-avatar.png';

        const html = `
            <div class="message ${isUser ? 'user' : 'ai'} fade-in" id="${id}">
                ${!isUser ? `<div class="d-flex align-items-start"><i class='bx bxs-bot fs-2 text-primary'></i></div>` : ''}
                <div class="message-content">
                    <span class="typing-indicator">...</span>
                </div>
                 ${isUser ? `<img src="${avatar}" class="avatar ms-2 align-self-start">` : ''}
            </div>
        `;

        $('#messagesContainer').append(html);
        scrollToBottom();
    }

    function updateMessageContent(id, content) {
        const renderedContent = marked.parse(content);
        $(`#${id} .message-content`).html(renderedContent);

        // Highlight code blocks
        $(`#${id} pre code`).each((i, block) => {
            hljs.highlightElement(block);
        });

        scrollToBottom();
    }

    function addMessage(role, content) {
        const id = uid('msg');
        addMessageContainer(role, id);
        updateMessageContent(id, content);
    }

    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        container.scrollTop = container.scrollHeight;
    }

    function loadHistory() {
        $.ajax({
            url: 'api/history.php',
            method: 'GET',
            data: { username: userName },
            success: function(response) {
                const history = (typeof response === 'string') ? JSON.parse(response) : response;
                $('#historyList').empty();
                if (history.error) return;

                history.forEach(chat => {
                    const html = `
                        <div class="history-item ${chat.id === currentSessionId ? 'active' : ''}" data-id="${chat.id}">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                <i class='bx bx-message-square-detail'></i>
                                <span class="text-truncate">${chat.title}</span>
                            </div>
                            <button class="btn btn-link link-danger p-0 delete-chat" title="Delete chat">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                    `;
                    $('#historyList').append(html);
                });
            }
        });
    }

    function loadChatSession(sessionId) {
        if (isSending) return;
        currentSessionId = sessionId;
        chatHistoryContext = [];
        $('#welcomeMessage').addClass('d-none');
        $('#messagesContainer').empty();

        $.ajax({
            url: 'api/load_chat.php',
            method: 'GET',
            data: { session_id: sessionId },
            success: function(response) {
                const messages = (typeof response === 'string') ? JSON.parse(response) : response;
                if (messages.error) return;

                messages.forEach(msg => {
                    const rawRole = (msg.role || msg.Role || '').toLowerCase();
                    const role = (rawRole === 'model' || rawRole === 'assistant') ? 'model' : 'user';
                    
                    addMessage(role, msg.content || msg.Content);
                    chatHistoryContext.push({
                        role: role === 'user' ? 'user' : 'assistant',
                        content: msg.content || msg.Content
                    });
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading chat session:', error);
            }
        });
    }

    function startNewChat() {
        if (isSending) return;
        currentSessionId = null;
        chatHistoryContext = [];
        $('#messagesContainer').empty();
        $('#messagesContainer').append(`
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center text-secondary opacity-50" id="welcomeMessage">
                <i class='bx bxs-bot fs-1 mb-3'></i>
                <h4>มีอะไรให้ฉันช่วยวันนี้?</h4>
            </div>
        `);
        $('.history-item').removeClass('active');
        $('#chatInput').focus();
    }

    // ... (handleUserMessage unchanged) ...

    function deleteChat(sessionId) {
        if (isSending) return;
        
        Swal.fire({
            title: 'แน่ใจหรือไม่?',
            text: "คุณต้องการลบแชทนี้ใช่ไหม",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'ใช่',
            cancelButtonText: 'ยกเลิก',
            background: '#ffffff',
            color: '#333',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/history.php',
                    method: 'DELETE',
                    data: JSON.stringify({ session_id: sessionId, username: userName }),
                    success: function(response) {
                        const res = (typeof response === 'string') ? JSON.parse(response) : response;
                        if (res.success) {
                            if (currentSessionId === sessionId) startNewChat();
                            loadHistory();
                            Swal.fire(
                                'ลบสำเร็จ!',
                                'ลบแชทเรียบร้อยแล้ว',
                                'success'
                            )
                        } else {
                            Swal.fire('Error', 'ไม่สามารถลบแชทได้', 'error');
                        }
                    }
                });
            }
        });
    }
});