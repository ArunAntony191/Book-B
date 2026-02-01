<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .community-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 100px);
        }
        
        /* Left Sidebar: List */
        .comm-sidebar {
            background: white;
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
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.2s;
        }
        .comm-item:hover, .comm-item.active { background: #f8fafc; }
        .comm-img { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; background: #e2e8f0; }
        
        /* Main Chat Area */
        .chat-area {
            background: white;
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
        .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc; display: flex; flex-direction: column; gap: 1rem; }
        .chat-input-area {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            align-items: center;
            background: white;
        }
        
        .message { max-width: 70%; padding: 0.8rem 1rem; border-radius: 12px; font-size: 0.95rem; line-height: 1.5; position: relative; }
        .message.mine { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 2px; }
        .message.theirs { align-self: flex-start; background: white; border: 1px solid var(--border-color); border-bottom-left-radius: 2px; }
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
            background: white;
            padding: 2rem;
            border-radius: var(--radius-md);
            width: 100%; width: 500px;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="community-layout">
                <!-- Sidebar -->
                <div class="comm-sidebar">
                    <div class="comm-header">
                        <h2 style="font-size: 1.2rem; margin: 0;">Communities</h2>
                        <button class="btn btn-primary" onclick="showModal('create')"><i class='bx bx-plus'></i></button>
                    </div>
                    <div style="padding: 1rem;">
                        <input type="text" class="form-input" placeholder="Find communities..." oninput="searchCommunity(this.value)">
                    </div>
                    <div class="comm-list" id="community-list" style="flex: 0.5; border-bottom: 1px solid var(--border-color);">
                        <!-- My Communities -->
                    </div>
                    <div style="padding: 0.8rem 1.5rem; background: #f1f5f9; font-weight: 700; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
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
                <button type="button" class="btn" style="background: #dc2626; color: white;" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let currentCommId = null;
        let currentCommCreator = null;
        let userId = <?php echo $_SESSION['user_id']; ?>;

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
                                <img src="${c.cover_image ? '../' + c.cover_image : '../assets/images/book-placeholder.jpg'}" class="comm-img">
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
                                <img src="${c.cover_image ? '../' + c.cover_image : '../assets/images/book-placeholder.jpg'}" class="comm-img">
                                <div style="flex:1;">
                                    <div style="font-weight:600;">${c.name}</div>
                                    <div style="font-size:0.8rem; color:#64748b;">${c.member_count} members</div>
                                </div>
                                <button class="btn btn-primary" style="padding:0.2rem 0.6rem; font-size:0.7rem;" onclick="joinCommunity(${c.id})">Join</button>
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
            document.getElementById('active-img').src = img ? '../' + img : '../assets/images/book-placeholder.jpg';
            
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
                // Member button: Leave
                buttonContainer.innerHTML = `
                    <button class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="leaveCommunity()">
                        <i class='bx bx-exit'></i> Leave
                    </button>
                `;
            }
        }
        
        // 3. Load Messages
        function loadMessages() {
            if (!currentCommId) return;
            fetch(`../community/api.php?action=messages&community_id=${currentCommId}`)
                .then(res => res.json())
                .then(msgs => {
                    const box = document.getElementById('messages-box');
                    box.innerHTML = '';
                    msgs.forEach(m => {
                        const isMine = m.user_id == userId;
                        let content = `<span class="msg-sender">${m.firstname} ${m.lastname}</span>`;
                        if (m.message) content += `<div>${m.message}</div>`;
                        if (m.attachment_url) content += `<img src="../${m.attachment_url}" style="max-width:200px; border-radius:8px; margin-top:0.5rem;">`;
                        
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

        // 4. Send Message
        function sendMessage() {
            const input = document.getElementById('msg-input');
            const fileInput = document.getElementById('file-upload');
            if ((!input.value.trim() && !fileInput.files[0]) || !currentCommId) return;
            
            const formData = new FormData();
            formData.append('community_id', currentCommId);
            formData.append('message', input.value);
            if (fileInput.files[0]) formData.append('image', fileInput.files[0]);
            
            fetch('../community/api.php?action=send_message', { method: 'POST', body: formData })
                .then(() => {
                    input.value = '';
                    fileInput.value = '';
                    loadMessages();
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
                        const btn = c.is_member ? '<span style="color:var(--primary); font-size:0.8rem;">Joined</span>' : `<button class="btn btn-primary" style="padding:0.2rem 0.6rem; font-size:0.7rem;" onclick="joinCommunity(${c.id})">Join</button>`;
                        
                        list.innerHTML += `
                            <div class="comm-item">
                                <img src="${c.cover_image ? '../' + c.cover_image : '../assets/images/book-placeholder.jpg'}" class="comm-img">
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
            const formData = new FormData();
            formData.append('community_id', id);
            fetch('../community/api.php?action=join', { method: 'POST', body: formData })
                .then(() => {
                    alert('Joined!');
                    loadCommunities(); // reload list logic will probably clear search
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
                        alert('Community created successfully!');
                    } else {
                        alert('Error: ' + (data.error || 'Failed to create community'));
                        console.error('Create error:', data);
                    }
                })
                .catch(err => {
                    alert('Network or Server Error. Check console.');
                    console.error('Fetch error:', err);
                });
        }
        
        // 7. Leave Community
        function leaveCommunity() {
            if (!confirm('Are you sure you want to leave this community?')) return;
            
            const formData = new FormData();
            formData.append('community_id', currentCommId);
            fetch('../community/api.php?action=leave', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Left community successfully');
                        currentCommId = null;
                        document.getElementById('chat-view').style.display = 'none';
                        document.getElementById('empty-choice').style.display = 'flex';
                        loadCommunities();
                    }
                })
                .catch(err => console.error('Leave error:', err));
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
                        alert('Profile picture updated successfully!');
                        document.getElementById('edit-cover-modal').style.display = 'none';
                        // Update image in UI
                        if (data.cover_image) {
                            document.getElementById('active-img').src = '../' + data.cover_image;
                        }
                        loadCommunities(); // Refresh list
                        e.target.reset();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to update'));
                    }
                })
                .catch(err => {
                    alert('Error updating cover image');
                    console.error('Update error:', err);
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
                        alert('Community deleted successfully');
                        document.getElementById('delete-modal').style.display = 'none';
                        currentCommId = null;
                        document.getElementById('chat-view').style.display = 'none';
                        document.getElementById('empty-choice').style.display = 'flex';
                        loadCommunities();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to delete'));
                    }
                })
                .catch(err => {
                    alert('Error deleting community');
                    console.error('Delete error:', err);
                });
        }

        // Init
        loadCommunities();
    </script>
</body>
</html>
