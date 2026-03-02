<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';
?>
<style>
        .community-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 100px);
        }
        
        /* Left Sidebar: List */
        .comm-sidebar {
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .comm-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .comm-list { flex: 1; overflow-y: auto; }
        .comm-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.2s;
        }
        .comm-item:hover, .comm-item.active { background: var(--bg-body); }
        .comm-img { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; background: var(--bg-body); }
        
        /* Main Chat Area */
        .chat-area {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chat-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; background: var(--bg-body); display: flex; flex-direction: column; gap: 1rem; }
        .chat-input-area {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            align-items: center;
            background: var(--bg-card);
        }
        
        .message { max-width: 70%; padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.95rem; line-height: 1.5; position: relative; }
        .message.mine { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 2px; }
        .message.theirs { align-self: flex-start; background: var(--bg-card); border: 1px solid var(--border-color); border-bottom-left-radius: 2px; color: var(--text-main); }
        .msg-sender { font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.3rem; display: block; font-weight: 600; }
        .message.mine .msg-sender { display: none; } /* Don't show name for own msgs */
        
        .empty-state {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-align: center;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            width: 500px;
        }
        /* Tabs */
        .chat-tabs { display: flex; gap: 1rem; padding: 0 1.5rem; border-bottom: 1px solid var(--border-color); margin-top: 1rem; }
        .chat-tab { padding: 0.5rem 1rem; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        .chat-tab:hover { color: var(--primary); background: var(--bg-body); border-top-left-radius: 6px; border-top-right-radius: 6px; }
        .chat-tab.active { border-color: var(--primary); color: var(--primary); }
        
        .tab-content { display: none; flex: 1; overflow-y: auto; padding: 1.5rem; background: var(--bg-body); }
        .tab-content.active { display: flex; flex-direction: column; }

        /* Book Grid */
        .comm-book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1.5rem; }
        .comm-book-card { background: var(--bg-card); border-radius: 12px; padding: 0.8rem; border: 1px solid var(--border-color); transition: all 0.2s; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
        .comm-book-card:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .comm-book-img { width: 100%; aspect-ratio: 2/3; object-fit: cover; border-radius: 8px; margin-bottom: 0.75rem; background: var(--bg-body); }
        .comm-book-title { font-size: 0.95rem; font-weight: 700; color: var(--text-main); line-height: 1.3; margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .comm-book-author { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .comm-book-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; background: var(--bg-body); color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
</style>
<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="community-layout">
                <!-- Sidebar -->
                <div class="comm-sidebar">
                    <div class="comm-header">
                        <h2 style="font-size: 1.2rem; margin: 0;">Communities</h2>
                        <?php if (!$user || $user['role'] !== 'admin'): ?>
                        <button class="btn btn-primary" onclick="showModal('create')"><i class='bx bx-plus'></i></button>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 1rem;">
                        <input type="text" class="form-input" placeholder="Find communities..." oninput="searchCommunity(this.value)">
                    </div>
                    <div class="comm-list" id="community-list" style="flex: 0.5; border-bottom: 1px solid var(--border-color);">
                        <!-- My Communities -->
                    </div>
                    <div style="padding: 0.8rem 1.5rem; background: var(--bg-body); font-weight: 700; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
                        Discover
                    </div>
                    <div class="comm-list" id="discover-list" style="flex: 1;">
                        <!-- Discover -->
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-area">
                    <div id="chat-view" style="display: none; height: 100%; flex-direction: column;">
                        <div class="chat-header">
                            <img id="active-img" class="comm-img">
                            <div style="flex: 1;">
                                <div id="active-name" style="font-size: 1.1rem;"></div>
                                <div id="active-desc" style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);"></div>
                            </div>
                            <div id="action-buttons" style="display: flex; gap: 0.5rem;">
                                <!-- Buttons will be added dynamically -->
                            </div>
                        </div>
                        
                        <!-- Tabs -->
                        <div class="chat-tabs">
                            <div class="chat-tab active" onclick="switchView('chat', this)">Chat</div>
                            <div class="chat-tab" onclick="switchView('books', this)">Books</div>
                        </div>

                        <!-- Chat View -->
                        <div id="chat-content" class="tab-content active" style="padding: 0; background: transparent; overflow: hidden;">
                            <div class="chat-messages" id="messages-box"></div>
                            <div class="chat-input-area">
                                <button class="btn" style="padding: 0.8rem; border-radius: 50%;" onclick="document.getElementById('file-upload').click()">
                                    <i class='bx bx-image'></i>
                                </button>
                                <input type="file" id="file-upload" style="display: none;" accept="image/*">
                                <input type="text" id="msg-input" class="form-input" placeholder="Type a message..." style="border-radius: 20px;">
                                <button class="btn btn-primary" onclick="sendMessage()"><i class='bx bx-send'></i></button>
                            </div>
                        </div>

                        <!-- Books View -->
                        <div id="books-content" class="tab-content">
                            <div id="books-grid" class="comm-book-grid">
                                <!-- Books will be loaded here -->
                            </div>
                            <div id="no-books-msg" style="display: none; text-align: center; color: var(--text-muted); margin-top: 2rem;">
                                <i class='bx bx-book' style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                <p>No books shared in this community yet.</p>
                                <a href="../pages/add_listing.php" class="btn btn-sm btn-primary" style="margin-top: 1rem;">Share a Book</a>
                            </div>
                        </div>
                    </div>
                    <div id="empty-choice" class="empty-state">
                        <i class='bx bx-group' style="font-size: 4rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                        <h3>Select a Community</h3>
                        <p>Join the conversation!</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Modal -->
    <div class="modal" id="create-modal">
        <form class="modal-content" onsubmit="createCommunity(event)">
            <h3>Create a Community</h3>
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Cover Image</label>
                <input type="file" name="cover" class="form-input">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="document.getElementById('create-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>

    <!-- Edit Cover Modal -->
    <div class="modal" id="edit-cover-modal">
        <form class="modal-content" onsubmit="updateCoverImage(event)">
            <h3>Edit Community Profile Picture</h3>
            <div class="form-group">
                <label class="form-label">New Cover Image</label>
                <input type="file" name="cover" class="form-input" accept="image/*" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="document.getElementById('edit-cover-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="delete-modal">
        <div class="modal-content">
            <h3 style="color: #dc2626;">Delete Community?</h3>
            <p style="margin: 1.5rem 0; color: var(--text-muted);">This action cannot be undone. All messages and members will be removed.</p>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="document.getElementById('delete-modal').style.display='none'">Cancel</button>
                <button type="button" class="btn" style="background: #dc2626; color: white; border: none;" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <div class="modal" id="report-modal">
        <form class="modal-content" onsubmit="submitReport(event)">
            <h3>Report Community Group</h3>
            <p style="margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">Please explain why you are reporting this community.</p>
            
            <div class="form-group">
                <label class="form-label">Reason</label>
                <select name="reason" class="form-input" required>
                    <option value="Inappropriate Content">Inappropriate Content</option>
                    <option value="Harassment">Harassment</option>
                    <option value="Spam">Spam</option>
                    <option value="Illegal Activities">Illegal Activities</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" style="height: 100px;" placeholder="Provide additional details..." required></textarea>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="document.getElementById('report-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444;">Submit Report</button>
            </div>
        </form>
    </div>

    <script>
        let currentCommId = null;
        let currentCommCreator = null;
        let userId = <?php echo $_SESSION['user_id']; ?>;
        const isAdmin = <?php echo ($user && $user['role'] === 'admin') ? 'true' : 'false'; ?>;

        // Helper to fix image paths
        function getImgPath(path) {
            if (!path) return '../assets/images/book-placeholder.jpg';
            if (path.startsWith('http') || path.startsWith('../')) return path;
            return '../' + path;
        }

        // 1. Load my communities & Discover
        function loadCommunities() {
            // My Communities
            fetch('../community/api.php?action=my_communities')
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('community-list');
                    list.innerHTML = '';
                    if (data.length === 0) list.innerHTML = '<div style="padding:1rem; text-align:center; color:#94a3b8; font-size: 0.9rem;">You haven\'t joined any communities yet.</div>';
                    
                    data.forEach(c => {
                        list.innerHTML += `
                            <div class="comm-item ${c.id == currentCommId ? 'active' : ''}" onclick="selectCommunity(${c.id}, '${c.name.replace(/'/g, "\\'")
}', '${(c.description || '').replace(/'/g, "\\'")}', '${c.cover_image || ''}', ${c.created_by})">
                                <img src="${getImgPath(c.cover_image)}" class="comm-img">
                                <div>
                                    <div style="font-weight:600;">${c.name}</div>
                                    <div style="font-size:0.8rem; color:#64748b;">${c.member_count} members</div>
                                </div>
                            </div>
                        `;
                    });
                });

            // Discover
            fetch('../community/api.php?action=discover')
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('discover-list');
                    list.innerHTML = '';
                    if (data.length === 0) list.innerHTML = '<div style="padding:1rem; text-align:center; color:#94a3b8; font-size: 0.9rem;">No new communities found.</div>';
                    
                    data.forEach(c => {
                        list.innerHTML += `
                            <div class="comm-item">
                                <img src="${getImgPath(c.cover_image)}" class="comm-img">
                                <div style="flex:1;">
                                    <div style="font-weight:600;">${c.name}</div>
                                    <div style="font-size:0.8rem; color:#64748b;">${c.member_count} members</div>
                                </div>
                                ${isAdmin ? '' : `<button class="btn btn-primary" style="padding:0.2rem 0.6rem; font-size:0.7rem;" onclick="joinCommunity(${c.id})">Join</button>`}
                            </div>
                        `;
                    });
                });
        }
        
        // 2. Select a community
        function selectCommunity(id, name, desc, img, createdBy) {
            currentCommId = id;
            currentCommCreator = createdBy;
            document.getElementById('empty-choice').style.display = 'none';
            document.getElementById('chat-view').style.display = 'flex';
            
            document.getElementById('active-name').textContent = name;
            document.getElementById('active-desc').textContent = desc;
            document.getElementById('active-img').src = getImgPath(img);
            
            // Update action buttons
            updateActionButtons();
            
            loadMessages();
            loadCommunities(); // Refresh active state in list
        }
        
        // Update action buttons based on creator status
        function updateActionButtons() {
            const buttonContainer = document.getElementById('action-buttons');
            buttonContainer.innerHTML = '';
            
            const isCreator = currentCommCreator == userId;
            
            if (isCreator) {
                // Creator buttons: Edit and Delete
                buttonContainer.innerHTML = `
                    <button class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="showEditCoverModal()">
                        <i class='bx bx-edit'></i> Edit Picture
                    </button>
                    <button class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem; background: #dc2626; color: white;" onclick="showDeleteModal()">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                `;
            } else {
                // Member button: Leave & Report
                buttonContainer.innerHTML = `
                    <button class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem; color: #ef4444; border-color: #ef4444;" onclick="showReportModal()">
                        <i class='bx bx-flag'></i> Report
                    </button>
                    <button class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="leaveCommunity()">
                        <i class='bx bx-exit'></i> Leave
                    </button>
                `;
            }
        }
        
        // 3. Load Messages
        function loadMessages() {
            if (!currentCommId || currentView !== 'chat') return;
            fetch(`../community/api.php?action=messages&community_id=${currentCommId}`)
                .then(res => res.json())
                .then(msgs => {
                    const box = document.getElementById('messages-box');
                    box.innerHTML = '';
                    msgs.forEach(m => {
                        const isMine = m.user_id == userId;
                        let content = `<span class="msg-sender">${m.firstname} ${m.lastname}</span>`;
                        if (m.message) content += `<div>${m.message}</div>`;
                        if (m.attachment_url) content += `<img src="${getImgPath(m.attachment_url)}" style="max-width:200px; border-radius:8px; margin-top:0.5rem; display: block; border: 1px solid rgba(0,0,0,0.1);">`;
                        
                        box.innerHTML += `
                            <div class="message ${isMine ? 'mine' : 'theirs'}">
                                ${content}
                            </div>
                        `;
                    });
                    box.scrollTop = box.scrollHeight;
                });
        }
        
        // Poll for messages
        setInterval(loadMessages, 3000);
        
        // --- Tabs & Books ---
        let currentView = 'chat';
        
        function switchView(view, tabEl) {
            currentView = view;
            
            // Update tabs
            document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
            tabEl.classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(`${view}-content`).classList.add('active');
            
            if (view === 'chat') {
                loadMessages();
            } else if (view === 'books') {
                loadBooks();
            }
        }
        
        function loadBooks() {
            if (!currentCommId) return;
            
            const grid = document.getElementById('books-grid');
            const noMsg = document.getElementById('no-books-msg');
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem;"><i class="bx bx-loader-alt bx-spin" style="font-size: 2rem; color: var(--primary);"></i></div>';
            noMsg.style.display = 'none';
            
            fetch(`../community/api.php?action=books&community_id=${currentCommId}`)
                .then(res => res.json())
                .then(books => {
                    grid.innerHTML = '';
                    if (!books || books.length === 0) {
                        noMsg.style.display = 'block';
                        return;
                    }
                    
                    books.forEach(book => {
                        const cover = book.cover_image || '../assets/images/book-placeholder.jpg';
                        grid.innerHTML += `
                            <div class="comm-book-card" onclick="window.location.href='book_details.php?id=${book.id}'">
                                <img src="${cover}" class="comm-book-img">
                                <div class="comm-book-title" title="${book.title}">${book.title}</div>
                                <div class="comm-book-author">by ${book.author}</div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span class="comm-book-badge">${book.listing_type}</span>
                                    <span style="font-size:0.8rem; font-weight:700; color:var(--primary);">${book.listing_type === 'sell' ? '₹'+book.price : book.price}</span>
                                </div>
                            </div>
                        `;
                    });
                })
                .catch(err => {
                    console.error(err);
                    grid.innerHTML = '<div style="color:red; text-align:center;">Failed to load books.</div>';
                });
        }

        // 4. Send Message
        function sendMessage() {
            const input = document.getElementById('msg-input');
            const fileInput = document.getElementById('file-upload');
            if ((!input.value.trim() && !fileInput.files[0]) || !currentCommId || isAdmin) return;
            
            const formData = new FormData();
            formData.append('community_id', currentCommId);
            formData.append('message', input.value);
            if (fileInput.files[0]) formData.append('image', fileInput.files[0]);
            
            fetch('../community/api.php?action=send_message', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = '';
                        fileInput.value = '';
                        loadMessages();
                    } else {
                        showToast('Error sending message: ' + (data.error || 'Unknown error'), 'error', 4000);
                    }
                })
                .catch(err => {
                    showToast('Failed to send message. Please check your connection.', 'error', 4000);
                });
        }

        // 5. Search & Join
        function searchCommunity(q) {
            if (!q) { loadCommunities(); return; }
            fetch(`../community/api.php?action=search&q=${q}`)
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('community-list');
                    list.innerHTML = '';
                    data.forEach(c => {
                        const btn = c.is_member ? '<span style="color:var(--primary); font-size:0.8rem;">Joined</span>' : (isAdmin ? '' : `<button class="btn btn-primary" style="padding:0.2rem 0.6rem; font-size:0.7rem;" onclick="joinCommunity(${c.id})">Join</button>`);
                        
                        list.innerHTML += `
                            <div class="comm-item">
                                <img src="${getImgPath(c.cover_image)}" class="comm-img">
                                <div style="flex:1;">
                                    <div style="font-weight:600;">${c.name}</div>
                                    <div style="font-size:0.8rem; color:#64748b;">${c.member_count} members</div>
                                </div>
                                ${btn}
                            </div>
                        `;
                    });
                });
        }
        
        function joinCommunity(id) {
            if (isAdmin) { showToast('Admins cannot join communities.', 'warning', 3500); return; }
            const formData = new FormData();
            formData.append('community_id', id);
            fetch('../community/api.php?action=join', { method: 'POST', body: formData })
                .then(() => {
                    showToast('🎉 Joined community successfully!', 'success', 3000);
                    loadCommunities();
                });
        }

        // 6. Create Community
        function showModal() { document.getElementById('create-modal').style.display = 'flex'; }
        
        function createCommunity(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            fetch('../community/api.php?action=create', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('create-modal').style.display = 'none';
                        loadCommunities();
                        showToast('🎉 Community created successfully!', 'success', 3000);
                    } else {
                        showToast('Error: ' + (data.error || 'Failed to create community'), 'error', 5000);
                    }
                })
                .catch(err => {
                    showToast('Network or Server Error. Please try again.', 'error', 4000);
                });
        }
        
        // 7. Leave Community
        async function leaveCommunity() {
            const confirmed = await Popup.confirm('Leave Community', 'Are you sure you want to leave this community?', { confirmText: 'Yes, Leave' });
            if (!confirmed) return;
            
            const formData = new FormData();
            formData.append('community_id', currentCommId);
            fetch('../community/api.php?action=leave', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('You have left the community.', 'success', 3000);
                        currentCommId = null;
                        document.getElementById('chat-view').style.display = 'none';
                        document.getElementById('empty-choice').style.display = 'flex';
                        loadCommunities();
                    }
                })
                .catch(err => showToast('Error leaving community.', 'error', 4000));
        }
        
        // 8. Edit Cover Image
        function showEditCoverModal() {
            document.getElementById('edit-cover-modal').style.display = 'flex';
        }
        
        function updateCoverImage(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('community_id', currentCommId);
            
            fetch('../community/api.php?action=update_cover', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('📸 Community picture updated!', 'success', 3000);
                        document.getElementById('edit-cover-modal').style.display = 'none';
                        if (data.cover_image) {
                            document.getElementById('active-img').src = getImgPath(data.cover_image);
                        }
                        loadCommunities();
                        e.target.reset();
                    } else {
                        showToast('Error: ' + (data.error || 'Failed to update'), 'error', 5000);
                    }
                })
                .catch(err => {
                    showToast('Error updating cover image.', 'error', 4000);
                });
        }

        // 9. Delete Community
        function showDeleteModal() {
            document.getElementById('delete-modal').style.display = 'flex';
        }
        
        function confirmDelete() {
            const formData = new FormData();
            formData.append('community_id', currentCommId);
            
            fetch('../community/api.php?action=delete', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('🗑️ Community deleted successfully.', 'success', 3000);
                        document.getElementById('delete-modal').style.display = 'none';
                        currentCommId = null;
                        document.getElementById('chat-view').style.display = 'none';
                        document.getElementById('empty-choice').style.display = 'flex';
                        loadCommunities();
                    } else {
                        showToast('Error: ' + (data.error || 'Failed to delete'), 'error', 5000);
                    }
                })
                .catch(err => {
                    showToast('Error deleting community.', 'error', 4000);
                });
        }

        // 10. Report Community
        function showReportModal() {
            document.getElementById('report-modal').style.display = 'flex';
        }

        async function submitReport(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'submit_report');
            formData.append('reported_community_id', currentCommId);
            formData.append('type', 'community');

            try {
                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast('🚩 Report submitted. Thank you for making the community safe!', 'success', 4000);
                    document.getElementById('report-modal').style.display = 'none';
                    e.target.reset();
                } else {
                    showToast('Error: ' + result.message, 'error', 5000);
                }
            } catch (error) {
                showToast('Failed to submit report. Please try again later.', 'error', 4000);
            }
        }

        // Init
        loadCommunities();
    </script>
</div>
