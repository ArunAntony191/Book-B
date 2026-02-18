<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
// Redirect if not logged in
if (!$userId) {
    header("Location: ../login.php");
    exit();
}

// Prevent self-chat
$targetUserId = $_GET['user'] ?? null;
if ($targetUserId && (int)$targetUserId === (int)$userId) {
    header("Location: index.php");
    exit();
}

$users = getRecentChats($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .chat-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            height: calc(100vh - 140px); /* Adjust to fit within main-content padding */
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        /* Report Modal Styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .close-modal:hover { color: var(--text-main); }


        .users-sidebar {
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            height: 100%;
            overflow: hidden;
        }
        .users-list {
            flex: 1;
            overflow-y: auto;
        }

        .user-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
        }
        .user-item:hover {
            background: #f1f5f9;
        }
        .user-item.active {
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
        }
        .chat-main {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            background: white;
        }

        #messages-container {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: white;
        }
        .message {
            max-width: 70%;
            padding: 0.8rem 1.2rem;
            border-radius: 1rem;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        .message-sent {
            align-self: flex-end;
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 0.2rem;
        }
        .message-received {
            align-self: flex-start;
            background: #f1f5f9;
            color: var(--text-main);
            border-bottom-left-radius: 0.2rem;
        }
        .chat-input-area {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            background: white;
        }
        .chat-header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .clear-chat-btn {
            color: #ef4444;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background 0.2s;
            display: none;
        }
        .clear-chat-btn:hover {
            background: #fef2f2;
        }
        .attachment-btn {
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .attachment-btn:hover {
            color: var(--primary);
        }
        .search-area {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }
        .search-input {
            width: 100%;
            padding: 0.6rem 2.5rem 0.6rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 0.9rem;
            outline: none;
        }
        .search-wrapper {
            position: relative;
        }
        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .message-image {
            max-width: 100%;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            display: block;
            cursor: pointer;
        }
        .user-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info {
            flex: 1;
        }
        .unread-badge {
            background: #ef4444;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            margin-left: 0.5rem;
        }
        .unread-dot {
            width: 10px;
            height: 10px;
            background: #ef4444;
            border-radius: 50%;
            margin-left: 0.5rem;
        }
        .message-time {
            font-size: 0.7rem;
            margin-top: 0.3rem;
            opacity: 0.7;
            display: block;
            text-align: right;
        }
        .message-received .message-time {
            text-align: left;
        }
        .sidebar-section-title {
            padding: 0.75rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: #f1f5f9;
        }
        .date-divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        .date-divider::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: var(--border-color);
            z-index: 1;
        }
        .date-divider span {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 1rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .search-loading {
            text-align: center;
            padding: 1rem;
            color: var(--primary);
            font-size: 0.8rem;
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../includes/dashboard_header.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="chat-layout">
                <div class="users-sidebar">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); font-weight: 700; background: white;">Messages</div>
                    <div class="search-area">
                        <div class="search-wrapper">
                            <input type="text" id="user-search" class="search-input" placeholder="Search for anyone...">
                            <i class='bx bx-search search-icon'></i>
                        </div>
                    </div>
                    <div id="search-loading" class="search-loading">
                        <i class='bx bx-loader-alt bx-spin'></i> Searching...
                    </div>
                    <div class="users-list" id="users-list">
                        <div id="recent-chats-title" class="sidebar-section-title">Recent Chats</div>
                        <div id="contacts-container">
                            <?php if (empty($users)): ?>
                                <div id="no-chats-msg" style="padding: 2rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                                    <i class='bx bx-message-rounded-dots' style="font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    No recent chats. <br> Search for someone to start chatting!
                                </div>
                            <?php endif; ?>
                            <?php foreach ($users as $user): ?>
                                <div class="user-item" data-id="<?php echo $user['id']; ?>" data-name="<?php echo strtolower($user['firstname'] . ' ' . $user['lastname']); ?>" onclick="selectUser(<?php echo $user['id']; ?>, '<?php echo $user['firstname']; ?>')">
                                    <div class="user-info">
                                        <div style="font-weight: 600;"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ucfirst($user['role']); ?></div>
                                    </div>
                                    <div class="unread-container">
                                        <?php if ($user['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $user['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>


                <div class="chat-main">
                    <div id="chat-header-container" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <span id="chat-header" style="font-weight: 700;">Select a user to start chatting</span>
                        <div class="chat-header-actions">
                            <i id="report-btn" class='bx bx-flag' title="Report User" style="color: #f59e0b; cursor: pointer; display: none; font-size: 1.25rem;" onclick="openReportModal()"></i>
                            <i id="clear-chat-btn" class='bx bx-trash clear-chat-btn' title="Clear Chat" onclick="clearChat()"></i>
                        </div>
                    </div>
                    <div id="messages-container">
                        <div style="margin: auto; text-align: center; color: var(--text-muted);">
                            <i class='bx bx-message-square-dots' style="font-size: 4rem; display: block; margin-bottom: 1rem;"></i>
                            <p>Pick a contact to load messages</p>
                        </div>
                    </div>
                    <div class="chat-input-area">
                        <label for="image-upload" class="attachment-btn">
                            <i class='bx bx-image-add'></i>
                        </label>
                        <input type="file" id="image-upload" style="display: none;" accept="image/*" onchange="previewImage(this)">
                        <input type="text" id="message-input" class="form-input" placeholder="Type your message..." disabled>
                        <button id="send-btn" class="btn btn-primary" onclick="sendMessage()" disabled>Send</button>
                    </div>
                    <div id="image-preview-container" style="display: none; padding: 0.5rem 1.5rem; background: #f8fafc; border-top: 1px solid var(--border-color);">
                        <div style="position: relative; display: inline-block;">
                            <img id="image-preview" src="" style="height: 60px; border-radius: 8px;">
                            <i class='bx bx-x' style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; cursor: pointer;" onclick="cancelImage()"></i>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Report Modal -->
        <div id="reportModal" class="modal">
            <div class="modal-content">
                <span class="close-modal" onclick="closeReportModal()">&times;</span>
                <h2 style="margin-bottom: 1.5rem; font-size: 1.25rem;">Report User</h2>
                <form id="reportForm" onsubmit="submitReport(event)">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Reason</label>
                        <select name="reason" class="form-input" required style="width: 100%;">
                            <option value="">Select a reason...</option>
                            <option value="Harassment/Abuse">Harassment or Verbal Abuse</option>
                            <option value="Spam/Scam">Spam or Scam Attempt</option>
                            <option value="Inappropriate Content">Inappropriate Content</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Description</label>
                        <textarea name="description" class="form-input" rows="4" style="width: 100%;" placeholder="Please provide details..." required></textarea>
                    </div>
                    <div style="text-align: right;">
                        <button type="button" class="btn btn-outline" onclick="closeReportModal()" style="margin-right: 0.5rem;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: #ef4444;">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        let currentReceiverId = null;
        const currentUserId = <?php echo (int)$userId; ?>;
        const API_URL = '<?php echo CHAT_SERVICE_URL; ?>';

        function selectUser(userId, userName) {
            currentReceiverId = userId;
            document.getElementById('chat-header').textContent = 'Chat with ' + userName;
            document.getElementById('message-input').disabled = false;
            document.getElementById('send-btn').disabled = false;
            document.getElementById('message-input').disabled = false;
            document.getElementById('send-btn').disabled = false;
            document.getElementById('clear-chat-btn').style.display = 'block';
            document.getElementById('report-btn').style.display = 'block';
            
            // Highlight active user
            document.querySelectorAll('.user-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-id') == userId) item.classList.add('active');
            });

            fetchMessages();
            if (typeof refreshSidebarUnread === 'function') refreshSidebarUnread();
        }

        async function fetchMessages() {
            if (!currentReceiverId) return;
            try {
                const response = await fetch(`${API_URL}?action=messages&user1=${currentUserId}&user2=${currentReceiverId}`);
                const messages = await response.json();
                renderMessages(messages);
            } catch (err) {
                console.error("Failed to fetch messages", err);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            const shouldScroll = container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
            
            container.innerHTML = '';
            let lastDate = null;

            messages.forEach(msg => {
                const msgDate = new Date(msg.created_at).toLocaleDateString();
                
                if (msgDate !== lastDate) {
                    const divider = document.createElement('div');
                    divider.className = 'date-divider';
                    
                    const today = new Date().toLocaleDateString();
                    const yesterday = new Date(Date.now() - 86400000).toLocaleDateString();
                    
                    let dateLabel = msgDate;
                    if (msgDate === today) dateLabel = 'Today';
                    else if (msgDate === yesterday) dateLabel = 'Yesterday';
                    
                    divider.innerHTML = `<span>${dateLabel}</span>`;
                    container.appendChild(divider);
                    lastDate = msgDate;
                }

                const isSent = msg.sender_id == currentUserId;
                const div = document.createElement('div');
                div.className = `message ${isSent ? 'message-sent' : 'message-received'}`;
                
                if (msg.message) {
                    const textSpan = document.createElement('span');
                    textSpan.textContent = msg.message;
                    div.appendChild(textSpan);
                }
                
                if (msg.attachment_url) {
                    const img = document.createElement('img');
                    img.src = '../' + msg.attachment_url;
                    img.className = 'message-image';
                    img.onclick = () => window.open(img.src, '_blank');
                    div.appendChild(img);
                }
                
                // Add timestamp
                const timeStr = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const timeSpan = document.createElement('small');
                timeSpan.className = 'message-time';
                timeSpan.textContent = timeStr;
                div.appendChild(timeSpan);
                
                container.appendChild(div);
            });
            
            if (shouldScroll) {
                container.scrollTop = container.scrollHeight;
            }
        }

        async function sendMessage() {
            const input = document.getElementById('message-input');
            const imageInput = document.getElementById('image-upload');
            const message = input.value.trim();
            
            if (!message && !imageInput.files[0]) return;
            if (!currentReceiverId) return;

            const formData = new FormData();
            formData.append('sender_id', currentUserId);
            formData.append('receiver_id', currentReceiverId);
            if (message) formData.append('message', message);
            if (imageInput.files[0]) formData.append('image', imageInput.files[0]);

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    input.value = '';
                    cancelImage();
                    fetchMessages();
                }
            } catch (err) {
                console.error("Failed to send message", err);
            }
        }

        async function clearChat() {
            if (!currentReceiverId || !confirm('Are you sure you want to clear this chat?')) return;
            
            try {
                const response = await fetch(`${API_URL}?user1=${currentUserId}&user2=${currentReceiverId}`, {
                    method: 'DELETE'
                });
                if (response.ok) {
                    fetchMessages();
                }
            } catch (err) {
                console.error("Failed to clear chat", err);
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('image-preview').src = e.target.result;
                    document.getElementById('image-preview-container').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function cancelImage() {
            document.getElementById('image-upload').value = '';
            document.getElementById('image-preview-container').style.display = 'none';
        }

        // Global Search logic
        const userSearch = document.getElementById('user-search');
        let searchTimeout;

        userSearch.addEventListener('input', function(e) {
            const query = e.target.value.trim().toLowerCase();
            const contactsContainer = document.getElementById('contacts-container');
            const title = document.getElementById('recent-chats-title');
            const loading = document.getElementById('search-loading');
            
            clearTimeout(searchTimeout);

            if (query === '') {
                title.textContent = 'Recent Chats';
                window.location.reload(); 
                return;
            }

            if (query.length < 2) return;

            loading.style.display = 'block';

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`${API_URL}?action=search_contacts&q=${encodeURIComponent(query)}&user_id=${currentUserId}`);
                    const results = await response.json();
                    
                    loading.style.display = 'none';
                    title.textContent = 'Search Results';
                    contactsContainer.innerHTML = '';
                    
                    if (results.length === 0) {
                        contactsContainer.innerHTML = '<div style="padding: 2rem 1rem; text-align: center; color: var(--text-muted);">No users found</div>';
                        return;
                    }

                    results.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'user-item';
                        div.setAttribute('data-id', user.id);
                        div.onclick = () => selectUser(user.id, user.firstname);
                        div.innerHTML = `
                            <div class="user-info">
                                <div style="font-weight: 600;">${user.firstname} ${user.lastname}</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</div>
                            </div>
                            <div class="unread-container"></div>
                        `;
                        contactsContainer.appendChild(div);
                    });
                } catch (err) {
                    loading.style.display = 'none';
                    console.error("Search failed", err);
                }
            }, 300);
        });

        async function pollUnreadCounts() {
            try {
                const response = await fetch(`${API_URL}?action=unread_counts&user_id=${currentUserId}`);
                const counts = await response.json();
                
                // Reset all badges first (logic: if not in the payload, it's 0)
                document.querySelectorAll('.unread-container').forEach(container => {
                    const item = container.closest('.user-item');
                    const userId = item.getAttribute('data-id');
                    
                    // Don't update for current active user (as messages are being read)
                    if (userId == currentReceiverId) {
                        container.innerHTML = '';
                        return;
                    }

                    const countData = counts.find(c => c.sender_id == userId);
                    if (countData && countData.count > 0) {
                        container.innerHTML = `<span class="unread-badge">${countData.count}</span>`;
                    } else {
                        container.innerHTML = '';
                    }
                });
            } catch (err) {
                console.error("Failed to fetch unread counts", err);
            }
        }

        // Poll for new messages every 1 second
        setInterval(fetchMessages, 1000);
        setInterval(pollUnreadCounts, 2000);

        // Auto-select user if passed in URL
        const urlParams = new URLSearchParams(window.location.search);
        const userParam = urlParams.get('user');
        if (userParam) {
            setTimeout(() => {
                const userItem = document.querySelector(`.user-item[onclick*="selectUser(${userParam}"]`);
                if (userItem) {
                    userItem.click();
                } else {
                    // User not in sidebar list? Fetch details and open chat anyway
                    fetch(`${API_URL}?action=get_user_info&id=${userParam}`)
                        .then(res => res.json())
                        .then(user => {
                            if (user && user.id) {
                                selectUser(user.id, user.firstname);
                            }
                        })
                        .catch(err => console.error('Failed to load user', err));
                }
            }, 500);
        }

        // Enter key to send
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });

        // Report Logic
        const reportModal = document.getElementById('reportModal');
        
        function openReportModal() {
            if (!currentReceiverId) return;
            reportModal.style.display = 'block';
        }

        function closeReportModal() {
            reportModal.style.display = 'none';
            document.getElementById('reportForm').reset();
        }

        async function submitReport(e) {
            e.preventDefault();
            if (!currentReceiverId) return;

            const formData = new FormData(e.target);
            formData.append('action', 'submit_report');
            formData.append('reported_id', currentReceiverId);

            try {
                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Report submitted successfully. Admins will review it shortly.');
                    closeReportModal();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred submitting the report.');
            }
        }

        window.onclick = function(event) {
            if (event.target == reportModal) {
                closeReportModal();
            }
        }
    </script>
</body>
</html>
