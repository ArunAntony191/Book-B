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

$users = getAllUsers($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="chat-layout">
                <div class="users-sidebar">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); font-weight: 700; background: white;">Messages</div>
                    <div class="users-list">
                        <?php foreach ($users as $user): ?>
                            <div class="user-item" onclick="selectUser(<?php echo $user['id']; ?>, '<?php echo $user['firstname']; ?>')">
                                <div style="font-weight: 600;"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ucfirst($user['role']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>


                <div class="chat-main">
                    <div id="chat-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); font-weight: 700;">
                        Select a user to start chatting
                    </div>
                    <div id="messages-container">
                        <div style="margin: auto; text-align: center; color: var(--text-muted);">
                            <i class='bx bx-message-square-dots' style="font-size: 4rem; display: block; margin-bottom: 1rem;"></i>
                            <p>Pick a contact to load messages</p>
                        </div>
                    </div>
                    <div class="chat-input-area">
                        <input type="text" id="message-input" class="form-input" placeholder="Type your message..." disabled>
                        <button id="send-btn" class="btn btn-primary" onclick="sendMessage()" disabled>Send</button>
                    </div>
                </div>
            </div>
        </main>

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
            
            // Highlight active user
            document.querySelectorAll('.user-item').forEach(item => {
                item.classList.remove('active');
                if (item.innerText.includes(userName)) item.classList.add('active');
            });

            fetchMessages();
        }

        async function fetchMessages() {
            if (!currentReceiverId) return;
            try {
                const response = await fetch(`${API_URL}/messages?user1=${currentUserId}&user2=${currentReceiverId}`);
                const messages = await response.json();
                renderMessages(messages);
            } catch (err) {
                console.error("Failed to fetch messages", err);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            container.innerHTML = '';
            messages.forEach(msg => {
                const isSent = msg.sender_id == currentUserId;
                const div = document.createElement('div');
                div.className = `message ${isSent ? 'message-sent' : 'message-received'}`;
                div.textContent = msg.message;
                container.appendChild(div);
            });
            container.scrollTop = container.scrollHeight;
        }

        async function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            if (!message || !currentReceiverId) return;

            try {
                await fetch(`${API_URL}/messages`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sender_id: currentUserId,
                        receiver_id: currentReceiverId,
                        message: message
                    })
                });
                input.value = '';
                fetchMessages();
            } catch (err) {
                console.error("Failed to send message", err);
            }
        }

        // Poll for new messages every 1 second
        setInterval(fetchMessages, 1000);

        // Auto-select user if passed in URL
        const urlParams = new URLSearchParams(window.location.search);
        const userParam = urlParams.get('user');
        if (userParam) {
            // Wait for user list to be available
            setTimeout(() => {
                const userItem = document.querySelector(`.user-item[onclick*="selectUser(${userParam}"]`);
                if (userItem) userItem.click();
            }, 500);
        }

        // Enter key to send
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
    </script>
</body>
</html>
