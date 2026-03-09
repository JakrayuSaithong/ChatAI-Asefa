/**
 * ASEFA AI Chat Application
 * 
 * รองรับ Models: Claude, Gemini, Perplexity พร้อม Web Search
 * Features: Streaming, Multimodal, File Upload, Chat History
 * 
 * @version 2.0.0
 * @author ASEFA Development Team
 */

$(document).ready(function () {
    // ============================================================
    // SECURITY: Disable Inspect & Right Click
    // ============================================================
    document.addEventListener('contextmenu', event => event.preventDefault());
    document.onkeydown = function (e) {
        if (e.keyCode == 123) return false; // F12
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) return false;
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) return false;
        if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false;
    };

    // ============================================================
    // AUTHENTICATION
    // ============================================================
    const userImg = localStorage.getItem('user_image');
    const userName = localStorage.getItem('user_username');

    let NameModal = $(".dropdown-menu .active").text();
    $("#modelSelector").text(NameModal);

    // เช็คการใช้ให้เช็คจาก user_username ที่อยู่ใน localStorage เป็นหลัก
    const currentPath = window.location.pathname;
    const isLoginPage = currentPath.endsWith('login') || currentPath.endsWith('login.php');

    if (!userName && !isLoginPage) {
        localStorage.clear();
        window.location.href = 'login';
        return;
    }

    if (userImg) $('#userAvatar').attr('src', userImg);
    if (userName) $('#userName').text(userName);

    // ============================================================
    // USER PERMISSIONS (Sidebar & Settings)
    // ============================================================
    const superUsers = ['660500122', '671100095'];
    if (superUsers.includes(userName)) {
        // Super User: Show Sidebar Profile Dropup
        $('.sidebar .dropup').removeClass('d-none');
        // Model Selector จะถูกจัดการใน applyUserModelPermissions()
    }

    $('#logoutBtn').on('click', function (e) {
        e.preventDefault();
        localStorage.clear();
        window.location.href = 'login';
    });

    // ============================================================
    // GLOBAL ERROR HANDLING
    // ============================================================
    $(document).ajaxError(function (event, jqxhr, settings, thrownError) {
        if (settings.suppressGlobalError) return;

        if (jqxhr.status === 0 || jqxhr.status >= 500) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'การเชื่อมต่อขัดข้อง',
                text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ (' + (thrownError || jqxhr.status) + ')',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });

    // ============================================================
    // SIDEBAR TOGGLE
    // ============================================================
    const SIDEBAR_COLLAPSED_KEY = 'sidebar_collapsed';

    if (localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true') {
        $('#sidebar').addClass('collapsed');
        $('#toggleSidebarDesktop i').removeClass('bx-menu').addClass('bx-menu-alt-right');
    }

    $('#toggleSidebarDesktop').on('click', function () {
        const $sidebar = $('#sidebar');
        const $icon = $(this).find('i');

        $sidebar.toggleClass('collapsed');

        if ($sidebar.hasClass('collapsed')) {
            $icon.removeClass('bx-menu').addClass('bx-menu-alt-right');
            localStorage.setItem(SIDEBAR_COLLAPSED_KEY, 'true');
        } else {
            $icon.removeClass('bx-menu-alt-right').addClass('bx-menu');
            localStorage.setItem(SIDEBAR_COLLAPSED_KEY, 'false');
        }
    });

    // ============================================================
    // MODEL CONFIGURATION
    // ============================================================
    
    /**
     * Default Model - Claude Sonnet 4 with Web Search
     * User ทั่วไปจะใช้ Claude เป็นหลัก
     */
    let currentModel = 'perplexity/sonar-pro';
    
    /**
     * Models สำหรับ User ทั่วไป (Claude Only)
     */
    const regularUserModels = [
        'anthropic/claude-sonnet-4',
        'anthropic/claude-3.5-sonnet',
        'anthropic/claude-3-haiku',
    ];
    
    /**
     * Models ที่รองรับ Multimodal (Vision)
     */
    const multimodalModels = [
        // Claude
        'anthropic/claude-sonnet-4',
        'anthropic/claude-3.5-sonnet',
        'anthropic/claude-3-opus',
        'anthropic/claude-3-haiku',
        // Gemini
        'google/gemini-2.5-pro',
        'google/gemini-2.5-flash',
        'google/gemini-3-pro',
        'google/gemini-3-flash-preview',
        // OpenAI
        'openai/gpt-4o',
        'openai/gpt-4o-mini',
        // Perplexity
        'perplexity/sonar-pro',
    ];

    /**
     * Models ที่มี Web Search 
     */
    const webSearchModels = [
        'anthropic/claude-sonnet-4',
        'anthropic/claude-3.5-sonnet',
        'anthropic/claude-3-opus',
        'anthropic/claude-3-haiku',
        'google/gemini-2.5-pro',
        'google/gemini-2.5-flash',
        'google/gemini-3-pro',
        'google/gemini-3-flash-preview',
        'openai/gpt-4o',
        'openai/gpt-4o-mini',
        'perplexity/sonar-pro',
        'perplexity/sonar-reasoning',
    ];

    // ============================================================
    // CHAT STATE
    // ============================================================
    let currentSessionId = null;
    let chatHistoryContext = [];
    let isSending = false;
    let attachedFiles = [];

    // Init Bootstrap Popover
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

    // ============================================================
    // USER MODEL PERMISSIONS
    // ============================================================
    applyUserModelPermissions();

    function applyUserModelPermissions() {
        // Super Users - เห็นทุก model
        const superUsers = ['660500122', '671100095'];
        
        if (superUsers.includes(userName)) {
            // Super User: แสดงทุก model และ dropdown selector
            $('.super-user-only').removeClass('d-none');
            $('.chat-header .dropdown').removeClass('d-none');
            return;
        }
        
        // ========== User ทั่วไป: บังคับใช้ Claude เท่านั้น ==========
        
        // 1. ซ่อน models ที่ไม่ใช่ Claude (super-user-only items)
        $('.super-user-only').addClass('d-none');
        
        // 2. ซ่อน dropdown selector สำหรับ user ทั่วไป (ไม่ต้องเลือก model)
        // หากต้องการให้ user เลือกระหว่าง Claude หลายรุ่น ให้ comment บรรทัดนี้
        $('.chat-header .dropdown').addClass('d-none');
        
        // 3. บังคับใช้ Claude Sonnet 4 เป็น default
        currentModel = 'perplexity/sonar-pro';
        $('#modelSelector').text('Claude Sonnet 4');
        
        // 4. ซ่อน models ที่ไม่อยู่ใน regularUserModels
        $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').each(function () {
            const model = $(this).data('model');
            if (!regularUserModels.includes(model)) {
                $(this).addClass('d-none user-restricted');
            }
        });
        
        // 5. Set active state
        $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').removeClass('active');
        $('#modelSelector').siblings('.dropdown-menu').find('[data-model="perplexity/sonar-pro"]').addClass('active');
    }

    // Load History on Start
    loadHistory();

    // ============================================================
    // INPUT HANDLING
    // ============================================================
    $('#chatInput').on('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';

        if ($(this).val().trim().length > 0 && !isSending) {
            $('#sendBtn').prop('disabled', false);
        } else {
            $('#sendBtn').prop('disabled', true);
        }
    });

    $('#chatInput').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleUserMessage();
        }
    });

    // ============================================================
    // UTILITY FUNCTIONS
    // ============================================================
    function uid(prefix = 'msg') {
        if (window.crypto && crypto.randomUUID) {
            return `${prefix}-${crypto.randomUUID()}`;
        }
        return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        container.scrollTop = container.scrollHeight;
    }

    // ============================================================
    // EVENT BINDINGS
    // ============================================================
    $('#sendBtn').on('click', handleUserMessage);
    $('#newChatBtn').on('click', startNewChat);

    // Model Selector
    $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').on('click', function (e) {
        e.preventDefault();
        $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').removeClass('active');
        $(this).addClass('active');
        currentModel = $(this).data('model');
        $('#modelSelector').text($(this).text());
        
        // Show toast if model has web search
        if (webSearchModels.includes(currentModel)) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: '🔍 Web Search พร้อมใช้งาน',
                showConfirmButton: false,
                timer: 2000
            });
        }
    });

    // History Item Click
    $(document).on('click', '.history-item', function (e) {
        if (isSending) return;
        if ($(e.target).closest('.delete-chat').length) return;
        
        const sessionId = $(this).data('id');
        loadChatSession(sessionId);
        $('.history-item').removeClass('active');
        $(this).addClass('active');
        
        // Close offcanvas on mobile
        if ($(window).width() < 992) {
            const offcanvas = bootstrap.Offcanvas.getInstance('#sidebar');
            if (offcanvas) offcanvas.hide();
        }
    });

    // Delete Chat
    $(document).on('click', '.delete-chat', function (e) {
        e.stopPropagation();
        if (isSending) return;
        const sessionId = $(this).closest('.history-item').data('id');
        deleteChat(sessionId);
    });

    // ============================================================
    // SETTINGS MODAL
    // ============================================================
    $('#settingsBtn').on('click', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'api/get_settings.php',
            method: 'GET',
            data: { username: userName },
            success: function (response) {
                const res = (typeof response === 'string') ? JSON.parse(response) : response;
                if (res.success && res.data) {
                    $('#settingPersonality').val(res.data.Personality || '');
                    $('#settingTone').val(res.data.Tone || '');
                    $('#settingTopics').val(res.data.Topics || '');
                    $('#settingEmoji').val(res.data.EmojiLevel || 'Min');
                    $('#settingCustom').val(res.data.CustomInstructions || '');
                    if (!res.data.Personality && !res.data.Tone) setDefaultSettings();
                } else {
                    setDefaultSettings();
                }
                new bootstrap.Modal('#settingsModal').show();
            }
        });
    });

    function setDefaultSettings() {
        $('#settingPersonality').val('ผู้ช่วยอัจฉริยะ');
        $('#settingTone').val('สุภาพและเป็นมืออาชีพ');
        $('#settingTopics').val('ทั่วไป');
        $('#settingEmoji').val('Min');
        $('#settingCustom').val('');
    }

    $('#saveSettingsBtn').on('click', function () {
        const data = {
            username: userName,
            personality: $('#settingPersonality').val(),
            tone: $('#settingTone').val(),
            topics: $('#settingTopics').val(),
            emoji_level: $('#settingEmoji').val(),
            custom_instructions: $('#settingCustom').val()
        };

        $.ajax({
            url: 'api/save_settings.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function (response) {
                const res = (typeof response === 'string') ? JSON.parse(response) : response;
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'อัปเดตการตั้งค่าเรียบร้อยแล้ว!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    bootstrap.Modal.getInstance('#settingsModal').hide();
                } else {
                    Swal.fire('ข้อผิดพลาด', 'ไม่สามารถบันทึกการตั้งค่าได้', 'error');
                }
            }
        });
    });

    // Preset Buttons
    $('#presetDefault').on('click', setDefaultSettings);

    $('#presetLess').on('click', function () {
        $('#settingPersonality').val('ผู้เชี่ยวชาญ');
        $('#settingTone').val('จริงจัง, เป็นทางการ, กระชับ');
        $('#settingEmoji').val('None');
        $('#settingCustom').val('ตอบสั้นๆ ไม่เยิ่นเย้อ');
    });

    $('#presetMore').on('click', function () {
        $('#settingPersonality').val('เพื่อนคู่คิด');
        $('#settingTone').val('สนุกสนาน, เป็นกันเอง');
        $('#settingEmoji').val('Max');
        $('#settingCustom').val('แสดงความกระตือรือร้นและให้กำลังใจ!');
    });

    // ============================================================
    // CORE CHAT FUNCTIONS
    // ============================================================
    
    function startNewChat() {
        if (isSending) return;
        
        currentSessionId = null;
        chatHistoryContext = [];
        attachedFiles = [];
        renderFilePreviews();
        
        $('#messagesContainer').empty();
        $('#messagesContainer').append(`
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center text-secondary opacity-50" id="welcomeMessage">
                <i class='bx bxs-bot fs-1 mb-3'></i>
                <h4>มีอะไรให้ฉันช่วยวันนี้?</h4>
        
            </div>
        `);
        
        $('.history-item').removeClass('active');
        $('#chatInput').focus();
        
        if ($(window).width() < 992) {
            const el = $('#sidebar')[0];
            const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(el);
            offcanvas.hide();
        }
    }

    /**
     * Handle User Message - Main Chat Function
     */
    async function handleUserMessage() {
        if (isSending) return;

        const input = $('#chatInput');
        const content = input.val().trim();

        if (!content && attachedFiles.length === 0) return;

        // Lock UI
        isSending = true;
        input.val('');
        input.css('height', 'auto');
        $('#sendBtn').prop('disabled', true);
        $('#welcomeMessage').addClass('d-none');

        // Render User Attachments
        let attachmentHtml = '';
        if (attachedFiles.length > 0) {
            attachmentHtml = attachedFiles.map(f => {
                if (f.type.startsWith('image/')) {
                    return `<div class="mb-2 text-end"><img src="${f.data}" class="rounded shadow-sm" style="max-width: 256px; height: auto;"></div>`;
                } else {
                    return `<div class="mb-2 text-end"><span class="bg-white p-1 rounded border small"><i class='bx bx-file'></i> ${f.name}</span></div>`;
                }
            }).join('');
        }

        // Display User Message
        const userMsgId = uid('user-msg');
        addMessageContainer('user', userMsgId, attachmentHtml);
        updateMessageContent(userMsgId, content);

        // Prepare Files & Clear UI
        const filesToSend = [...attachedFiles];
        attachedFiles = [];
        renderFilePreviews();

        // Create AI Message Placeholder
        const aiMsgId = uid('ai-msg');
        addMessageContainer('model', aiMsgId);

        // Show analyzing status if files attached
        if (filesToSend.length > 0) {
            const loader = `<div class="d-flex align-items-center gap-2 text-secondary small fade-in" id="${aiMsgId}-status">
                <i class='bx bx-loader-alt bx-spin'></i> 
                <span>กำลังวิเคราะห์ไฟล์...</span>
             </div>`;
            $(`#${aiMsgId} .typing-indicator`).replaceWith(loader);
        }

        let fullAiResponse = "";
        let retryStatusId = null;

        // Current DateTime (Thai)
        const nowTH = new Date().toLocaleString("th-TH", {
            timeZone: "Asia/Bangkok",
            dateStyle: "full",
            timeStyle: "medium"
        });

        // ============================================================
        // SYSTEM PROMPT - Smart Business Intelligence Assistant
        // ============================================================
        
        const modelDisplayNames = {
            'anthropic/claude-sonnet-4': 'Claude Sonnet 4',
            'anthropic/claude-3.5-sonnet': 'Claude 3.5 Sonnet',
            'anthropic/claude-3-haiku': 'Claude 3 Haiku',
            'anthropic/claude-3-opus': 'Claude 3 Opus',
            'google/gemini-2.5-pro': 'Gemini 2.5 Pro',
            'google/gemini-2.5-flash': 'Gemini 2.5 Flash',
            'google/gemini-3-flash-preview': 'Gemini 3 Flash',
            'perplexity/sonar-pro': 'Perplexity Sonar Pro',
            'openai/gpt-4o': 'GPT-4o',
        };
        
        const currentModelName = modelDisplayNames[currentModel] || currentModel;
        
        const systemPrompt = `คุณคือ AI Assistant ระดับ Senior Business Intelligence Analyst พัฒนาโดย ASEFA
        คุณมีความสามารถในการค้นหาข้อมูลจากอินเทอร์เน็ตแบบ Real-time
        
        ## ข้อมูลระบบ
        - Model: ${currentModelName}
        - วันที่: ${nowTH}
        
        ---
        
        ## 🎯 หลักการตอบแบบ Smart Response
        
        ### Pattern การตอบที่ดี: "Direct → Expand → Connect"
        
        1. **Direct (ตอบตรงคำถาม)**
           - ตอบคำตอบหลักทันทีในประโยคแรก
           - ใช้ตัวหนา **bold** เน้นข้อมูลสำคัญ
           
        2. **Expand (ขยายความที่เกี่ยวข้อง)**
           - ให้รายละเอียดเพิ่มเติมที่น่าสนใจ
           - ใส่บริบท ที่มา ความสัมพันธ์
           - ใช้ bullet points จัดระเบียบ
        
        3. **Connect (เชื่อมโยงมุมมอง)**
           - เชื่อมโยงกับ industry trends
           - ให้ strategic insights
           - แนะนำมุมมองเชิงธุรกิจ
        
        ---
        
        ## 📝 ตัวอย่างการตอบที่ดี
        
        **คำถาม:** "CNTE มี CATL ถือหุ้นอยู่กี่เปอร์เซ็นต์"
        
        **การตอบที่ดี:**
        ข้อมูลปัจจุบันระบุว่า **CATL ถือหุ้นใน CNTE อยู่ที่ 20%** ครับ
        
        รายละเอียดเพิ่มเติมที่น่าสนใจ:
        
        * **ผู้ก่อตั้ง:** CNTE ก่อตั้งปี 2019 โดย Huang Shilin (อดีตรองประธาน CATL)
        * **ความสัมพันธ์เชิงกลยุทธ์:** CNTE เป็นพันธมิตรสำคัญในตลาด BESS โดยเฉพาะ System Integration
        * **บทบาท:** เน้น R&D อุปกรณ์จัดเก็บพลังงานสำหรับ Grid และ C&I
        
        ---
        
        ## 🧠 วิธีคิดก่อนตอบ
        
        เมื่อได้รับคำถาม ให้คิดว่า:
        1. **คำตอบตรงๆ คืออะไร?** → ตอบก่อน
        2. **ข้อมูลอะไรที่เกี่ยวข้องและน่าสนใจ?** → ขยายความ
        3. **มีมุมมองเชิงกลยุทธ์อะไรบ้าง?** → เชื่อมโยง
        4. **ผู้ถามน่าจะอยากรู้อะไรเพิ่ม?** → เสนอต่อยอด
        
        ---
        
        ## 🎨 สไตล์การนำเสนอ
        
        ### รูปแบบที่ใช้:
        - **ตัวหนา** สำหรับข้อมูลสำคัญ ตัวเลข ชื่อ
        - Bullet points สำหรับรายละเอียด
        - ตาราง สำหรับเปรียบเทียบ/ตัวเลข
        - Heading สำหรับแบ่งหมวดหมู่
        
        ### น้ำเสียง:
        - มืออาชีพ แต่เข้าถึงง่าย
        - ใช้ "ครับ" ลงท้าย
        - ไม่ใช้ emoji ยกเว้นถูกขอ
        
        ---
        
        ## ⚡ เมื่อถูกถามเรื่องต่างๆ
        
        ### ข้อมูลบริษัท/หุ้น/ธุรกิจ:
        → ค้นหา → ตอบตรง → ให้บริบท → วิเคราะห์เชิงกลยุทธ์
        
        ### ข้อมูลเทคนิค/เทคโนโลยี:
        → อธิบายชัดเจน → ยกตัวอย่าง → เชื่อมโยงการใช้งานจริง
        
        ### คำถามทั่วไป/ทักทาย:
        → ตอบสั้นๆ "สวัสดีครับ มีอะไรให้ช่วยไหมครับ?"
        
        ### ถูกถามว่าเป็น AI อะไร:
        → "ผมคือ ${currentModelName} ครับ"
        
        ---
        
        ## 🚫 ข้อจำกัด
        
        ### ราคาหุ้น Real-time:
        - ไม่สามารถดึงได้ → แนะนำ set.or.th หรือ settrade.com
        - แต่สามารถให้ข้อมูลบริษัท ผลประกอบการ การวิเคราะห์ได้
        
        ### ข้อมูลไม่แน่ใจ:
        - บอกตรงๆ ว่าไม่แน่ใจ ไม่เดา
        
        ---
        
        ## ❌ สิ่งที่ห้ามทำ (บังคับเคร่งครัด)
        
        - ห้ามแนะนำตัวเองโดยไม่ถูกถาม
        - ห้ามใช้ emoji โดยไม่จำเป็น
        - ห้ามเปิดประโยคด้วย "แน่นอนครับ!" "ยินดีครับ!"
        - ห้ามตอบแค่คำตอบเดียวโดดๆ โดยไม่ให้บริบท (ยกเว้นคำถามง่ายมาก)
        - ห้ามตอบยาวเกินไปจนหลุดประเด็น
        
        ### 🚫 ห้ามใส่ Citations/References (สำคัญมาก!)
        - ห้ามใส่ตัวเลขอ้างอิง เช่น [1], [2], [3], [1-5], [1][2][3] โดยเด็ดขาด
        - ห้ามใส่ footnotes หรือ references ใดๆ ในคำตอบ
        - ห้ามแสดง URL หรือ link ในคำตอบ ยกเว้นถูกขอโดยตรง
        - ถ้าข้อมูลมาจากหลายแหล่ง ให้สรุปรวมเป็นคำตอบเดียว ไม่ต้องระบุแหล่งที่มา
        - เขียนคำตอบเหมือนผู้เชี่ยวชาญที่รู้ข้อมูลอยู่แล้ว ไม่ใช่การ quote จากแหล่งอ้างอิง
        
        ---
        
        จงปฏิบัติตาม Pattern "Direct → Expand → Connect" อย่างเคร่งครัด`;

        try {
            // Prepare Messages
            const contextMessages = chatHistoryContext.slice(-10);
            const messagesPayload = [
                {
                    role: "system",
                    content: systemPrompt
                },
                ...contextMessages,
                {
                    role: "user",
                    content: content,
                    files: filesToSend
                }
            ];

            console.log('Sending to model:', currentModel);

            // ============================================================
            // FETCH STREAMING RESPONSE
            // ============================================================
            const response = await fetch('api/chat_stream.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: currentModel,
                    messages: messagesPayload,
                    username: userName,
                    session_id: currentSessionId,
                    web_search: true // Enable web search
                })
            });

            if (!response.ok || !response.body) {
                throw new Error(`HTTP Error: ${response.status}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            // SSE Buffer
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
                            if (retryStatusId) { 
                                $(`#${retryStatusId}`).remove(); 
                                retryStatusId = null; 
                            }
                        } else if (payload.type === 'session_id') {
                            if (!currentSessionId) currentSessionId = payload.data;
                            loadHistory();
                        } else if (payload.type === 'status') {
                            // Show status (searching, retrying, etc.)
                            if (!retryStatusId) {
                                retryStatusId = 'status-' + Date.now();
                                $(`#${aiMsgId}`).after(`
                                    <div id="${retryStatusId}" class="text-info small ms-5 mb-2 fade-in">
                                        ${payload.data}
                                    </div>
                                `);
                            } else {
                                $(`#${retryStatusId}`).html(payload.data);
                            }
                        } else if (payload.type === 'error') {
                            throw new Error(payload.data);
                        } else if (payload.type === 'done') {
                            // Stream completed
                            if (retryStatusId) {
                                $(`#${retryStatusId}`).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                        }
                    } catch (e) {
                        console.error("Parse Error", e, { jsonText });
                        const errHTML = `<div class="text-danger small mt-1"><i class='bx bx-error'></i> เกิดข้อผิดพลาดในการประมวลผลข้อมูล</div>`;
                        $(`#${aiMsgId} .message-content`).append(errHTML);
                    }
                }
            }

            // Update Context
            chatHistoryContext.push({ role: 'user', content: content, files: filesToSend });
            chatHistoryContext.push({ role: 'assistant', content: fullAiResponse });

        } catch (error) {
            console.error("Stream Error:", error);

            let userMsg = "เกิดข้อผิดพลาด กรุณาลองใหม่ภายหลัง";
            const errMsg = error.message || "";

            if (errMsg.includes("429") || errMsg.includes("Server busy")) {
                userMsg = "ขณะนี้มีผู้ใช้งานจำนวนมาก กรุณารอสักครู่แล้วลองใหม่";
            } else if (errMsg.includes("404") || errMsg.includes("Not Found")) {
                userMsg = "โมเดลนี้ไม่สามารถใช้งานได้ หรือไม่มีอยู่จริง";
            } else if (errMsg.includes("401") || errMsg.includes("Unauthorized")) {
                userMsg = "เกิดข้อผิดพลาดเกี่ยวกับสิทธิ์การเข้าถึง (API Key)";
            } else if (errMsg.includes("400")) {
                userMsg = "คำขอไม่ถูกต้อง (Bad Request)";
            } else if (errMsg) {
                userMsg = errMsg;
            }

            const errorHtml = `\n\n<div class="alert alert-danger mt-2 mb-0 p-2 small border-0"><i class='bx bxs-error-circle'></i> <strong>ข้อผิดพลาด:</strong> ${userMsg}</div>`;
            updateMessageContent(aiMsgId, (fullAiResponse || "") + errorHtml);

        } finally {
            if (retryStatusId) $(`#${retryStatusId}`).remove();
            isSending = false;
        }
    }

    // ============================================================
    // MESSAGE RENDERING
    // ============================================================
    
    function addMessageContainer(role, id, attachmentHtml = '') {
        const isUser = role === 'user';
        const avatar = isUser ? (userImg || 'assets/img/default-avatar.svg') : 'assets/img/ai-avatar.png';

        const html = `
            <div class="message ${isUser ? 'user' : 'ai'} fade-in" id="${id}">
                ${!isUser ? `<div class="d-flex align-items-start"><i class='bx bxs-bot fs-2 text-primary'></i></div>` : ''}
                <div class="d-flex flex-column ${isUser ? 'align-items-end' : ''}" style="flex: 1; min-width: 0;">
                    ${attachmentHtml ? `<div class="attachment-preview mb-1">${attachmentHtml}</div>` : ''}
                    <div class="message-content">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
                ${isUser ? `<img src="${avatar}" class="avatar ms-2 align-self-start">` : ''}
            </div>
        `;

        $('#messagesContainer').append(html);
        scrollToBottom();
    }

    function updateMessageContent(id, content) {
        const messageContainer = $(`#${id} .message-content`);

        if (!content) return;

        const renderedContent = marked.parse(content);
        messageContainer.html(renderedContent);

        // Highlight code blocks
        $(`#${id} pre code`).each((i, block) => {
            hljs.highlightElement(block);
        });

        scrollToBottom();
    }

    function addMessage(role, content, attachmentHtml = '') {
        const id = uid('msg');
        addMessageContainer(role, id, attachmentHtml);
        updateMessageContent(id, content);
    }

    // ============================================================
    // CHAT HISTORY
    // ============================================================
    
    function loadHistory() {
        $.ajax({
            url: 'api/history.php',
            method: 'GET',
            data: { username: userName },
            success: function (response) {
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
        attachedFiles = [];
        renderFilePreviews();
        $('#welcomeMessage').addClass('d-none');
        $('#messagesContainer').empty();

        $.ajax({
            url: 'api/load_chat.php',
            method: 'GET',
            data: { session_id: sessionId },
            success: function (response) {
                const messages = (typeof response === 'string') ? JSON.parse(response) : response;
                if (messages.error) return;

                let hasAttachments = false;

                messages.forEach(msg => {
                    const rawRole = (msg.role || msg.Role || '').toLowerCase();
                    const role = (rawRole === 'model' || rawRole === 'assistant') ? 'model' : 'user';

                    let content = msg.content || msg.Content;

                    // Render Attachments
                    let attachmentHtml = '';
                    if (msg.attachments && msg.attachments.length > 0) {
                        hasAttachments = true;
                        msg.attachments.forEach(file => {
                            if (!file.exists) {
                                attachmentHtml += `<div class="text-secondary small fst-italic mb-2 text-end"><i class='bx bx-error'></i> ${file.original_name} (ไฟล์หมดเวลา)</div>`;
                            } else {
                                const ext = file.original_name.split('.').pop().toLowerCase();
                                const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);

                                if (isImg) {
                                    attachmentHtml += `<div class="mb-2 text-end"><img src="${file.url}" class="rounded shadow-sm" style="max-width: 256px; height: auto;"></div>`;
                                } else {
                                    attachmentHtml += `<div class="mb-2 text-end"><a href="${file.url}" target="_blank" class="text-decoration-none text-body bg-white p-1 rounded border small"><i class='bx bx-file'></i> ${file.original_name}</a></div>`;
                                }
                            }
                        });
                        content = content.replace(/\[Attached: .*?\]/gs, '').trim();
                    }

                    addMessage(role, content, attachmentHtml);

                    chatHistoryContext.push({
                        role: role === 'user' ? 'user' : 'assistant',
                        content: content,
                        attachments: msg.attachments || []
                    });
                });

                // Auto-switch model if history has files
                if (hasAttachments) {
                    filterModelsForFile(true);
                } else {
                    filterModelsForFile(false);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error loading chat session:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'ไม่สามารถโหลดแชทได้',
                    text: 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่',
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    }

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
                    success: function (response) {
                        const res = (typeof response === 'string') ? JSON.parse(response) : response;
                        if (res.success) {
                            if (currentSessionId === sessionId) startNewChat();
                            loadHistory();
                            Swal.fire('ลบสำเร็จ!', 'ลบแชทเรียบร้อยแล้ว', 'success');
                        } else {
                            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถลบแชทได้', 'error');
                        }
                    }
                });
            }
        });
    }

    // ============================================================
    // FILE UPLOAD HANDLING
    // ============================================================
    
    $('#attachBtn').on('click', () => $('#fileInput').click());

    $('#fileInput').on('change', function (e) {
        if (this.files && this.files.length > 0) {
            handleFileSelect(this.files);
        }
        $(this).val('');
    });

    // Drag & Drop
    const dropZone = document.body;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            handleFileSelect(files);
        }
    }

    async function handleFileSelect(files) {
        // Whitelist ของนามสกุลไฟล์ที่ปลอดภัย
        const allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const ext = file.name.split('.').pop().toLowerCase();

            // เช็คนามสกุลไฟล์
            if (!allowedExts.includes(ext)) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: `ไม่อนุญาตให้อัปโหลดไฟล์ .${ext}`,
                    showConfirmButton: false,
                    timer: 3000
                });
                continue;
            }

            // Limit 5MB
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: `${file.name} มีขนาดใหญ่เกิน 5MB`,
                    showConfirmButton: false,
                    timer: 3000
                });
                continue;
            }

            // Avoid duplicates
            if (attachedFiles.some(f => f.name === file.name && f.size === file.size)) continue;

            const base64 = await readFileAsBase64(file);
            attachedFiles.push({
                name: file.name,
                type: file.type,
                size: file.size,
                data: base64
            });
        }

        renderFilePreviews();
        filterModelsForFile(attachedFiles.length > 0);
    }

    function readFileAsBase64(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.readAsDataURL(file);
        });
    }

    function renderFilePreviews() {
        const container = $('#filePreviewArea');
        container.empty();

        if (attachedFiles.length === 0) {
            container.addClass('d-none');
            if ($('#chatInput').val().trim().length === 0) {
                $('#sendBtn').prop('disabled', true);
            }
            return;
        }

        container.removeClass('d-none');
        $('#sendBtn').prop('disabled', false);

        attachedFiles.forEach((file, index) => {
            const isImage = file.type.startsWith('image/');
            const icon = isImage 
                ? `<img src="${file.data}" class="rounded" style="width: 30px; height: 30px; object-fit: cover;">` 
                : `<i class='bx bx-file fs-4 text-secondary'></i>`;

            const html = `
                <div class="d-flex align-items-center gap-2 bg-light border rounded px-2 py-1 small shadow-sm position-relative fade-in">
                    ${icon}
                    <span class="text-truncate" style="max-width: 120px;">${file.name}</span>
                    <button type="button" class="btn-close btn-close-dark ms-1 remove-file" style="font-size: 0.6rem;" data-index="${index}"></button>
                </div>
            `;
            container.append(html);
        });
    }

    $(document).on('click', '.remove-file', function () {
        const index = $(this).data('index');
        attachedFiles.splice(index, 1);
        renderFilePreviews();
        filterModelsForFile(attachedFiles.length > 0);
    });

    function filterModelsForFile(hasFile) {
        if (!hasFile) {
            // Restore all models
            $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').removeClass('disabled').css('pointer-events', 'auto');
            return;
        }

        // Disable non-multimodal models
        let currentIsValid = false;
        $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').each(function () {
            const model = $(this).data('model');
            if (!multimodalModels.includes(model)) {
                $(this).addClass('disabled').css('pointer-events', 'none');
            } else {
                if (model === currentModel) currentIsValid = true;
            }
        });

        if (!currentIsValid) {
            // Auto switch to first valid multimodal model
            const firstValid = $('#modelSelector').siblings('.dropdown-menu').find('.dropdown-item').not('.disabled').first();
            if (firstValid.length) {
                firstValid.click();
            }
        }
    }

});