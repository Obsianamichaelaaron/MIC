<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "Messages";

$user_id = getCurrentUserId();
$conn = getDBConnection();

// Get all users (employers and other admins) that the admin can chat with
// Applicants cannot chat - only employers and admins can chat
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.role, u.email,
           e.company_name
    FROM users u
    LEFT JOIN employers e ON e.user_id = u.user_id
    WHERE u.status = 'active' 
      AND (u.role = 'employer' OR u.role = 'admin')
      AND u.user_id != ?
    ORDER BY u.role, u.first_name, u.last_name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$available_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

include '../includes/header.php';
?>

<link rel="stylesheet" href="../css/chat.css">

<div class="container">
    <div class="card">
        <h1><i class="fas fa-comments"></i> Messages</h1>
        
        <div class="chat-container">
            <div class="chat-sidebar">
                <div class="chat-sidebar-header">
                    <h3><i class="fas fa-users"></i> Chat With</h3>
                    <button class="btn btn-sm btn-primary" onclick="loadConversations()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                
                <div class="chat-user-list" id="conversationsList">
                    <div class="chat-loading">Loading conversations...</div>
                </div>
                
                <div class="chat-sidebar-footer">
                    <h4><i class="fas fa-user-plus"></i> Start New Conversation</h4>
                    <div class="available-users-list" id="availableUsersList">
                        <?php foreach ($available_users as $user): ?>
                            <div class="available-user-item" onclick="startConversation(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>', '<?php echo htmlspecialchars($user['role']); ?>', '<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>')">
                                <div class="user-avatar">
                                    <i class="fas fa-<?php echo $user['role'] === 'employer' ? 'building' : 'user'; ?>"></i>
                                </div>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                    <div class="user-role">
                                        <?php if ($user['role'] === 'employer' && !empty($user['company_name'])): ?>
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($user['company_name']); ?>
                                        <?php else: ?>
                                            <i class="fas fa-briefcase"></i> <?php echo ucfirst($user['role']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="chat-main">
                <div class="chat-header" id="chatHeader">
                    <div class="chat-header-placeholder">
                        <i class="fas fa-comments"></i>
                        <p>Select a conversation or start a new one</p>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-messages-placeholder">
                        <i class="fas fa-comment-dots"></i>
                        <p>No conversation selected</p>
                    </div>
                </div>
                
                <div class="chat-input-container" id="chatInputContainer" style="display: none;">
                    <form id="messageForm" onsubmit="sendMessage(event)">
                        <input type="text" id="messageInput" placeholder="Type your message..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentChatUserId = null;
let refreshInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadConversations();
    loadUnreadCount();
    
    // Refresh conversations every 5 seconds
    refreshInterval = setInterval(function() {
        loadConversations();
        if (currentChatUserId) {
            loadMessages(currentChatUserId);
        }
        loadUnreadCount();
    }, 5000);
});

function loadConversations() {
    fetch('../includes/handlers/message_handler.php?action=get_conversations')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayConversations(data.conversations);
            } else {
                console.error('Error loading conversations:', data.message);
                document.getElementById('conversationsList').innerHTML = 
                    '<div class="chat-empty">Error: ' + (data.message || 'Failed to load conversations') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading conversations:', error);
            document.getElementById('conversationsList').innerHTML = 
                '<div class="chat-empty">Error loading conversations. Please refresh the page.</div>';
        });
}

function displayConversations(conversations) {
    const list = document.getElementById('conversationsList');
    
    if (conversations.length === 0) {
        list.innerHTML = '<div class="chat-empty">No conversations yet. Start a new conversation below.</div>';
        return;
    }
    
    let html = '';
    conversations.forEach(conv => {
        const unreadBadge = conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
        const lastMsg = conv.last_message ? (conv.last_message.length > 50 ? conv.last_message.substring(0, 50) + '...' : conv.last_message) : 'No messages';
        const time = conv.last_message_time ? formatTime(conv.last_message_time) : '';
        
        html += `
            <div class="conversation-item" onclick="openConversation(${conv.user_id}, '${conv.name.replace(/'/g, "\\'")}', '${conv.role}', '${(conv.company_name || '').replace(/'/g, "\\'")}')">
                <div class="user-avatar">
                    <i class="fas fa-${conv.role === 'admin' ? 'user-shield' : conv.role === 'employer' ? 'building' : 'user'}"></i>
                </div>
                <div class="conversation-info">
                    <div class="conversation-header">
                        <span class="conversation-name">${conv.name}</span>
                        ${unreadBadge}
                    </div>
                    <div class="conversation-preview">${lastMsg}</div>
                    <div class="conversation-time">${time}</div>
                </div>
            </div>
        `;
    });
    
    list.innerHTML = html;
}

function openConversation(userId, userName, userRole, companyName) {
    currentChatUserId = userId;
    
    // Update header
    const header = document.getElementById('chatHeader');
    const roleIcon = userRole === 'admin' ? 'user-shield' : userRole === 'employer' ? 'building' : 'user';
    const roleText = userRole === 'employer' && companyName ? companyName : userRole.charAt(0).toUpperCase() + userRole.slice(1);
    
    header.innerHTML = `
        <div class="chat-header-active">
            <div class="user-avatar">
                <i class="fas fa-${roleIcon}"></i>
            </div>
            <div class="chat-header-info">
                <div class="chat-header-name">${userName}</div>
                <div class="chat-header-role">
                    <i class="fas fa-${userRole === 'employer' ? 'building' : 'briefcase'}"></i> ${roleText}
                </div>
            </div>
        </div>
    `;
    
    // Show input
    document.getElementById('chatInputContainer').style.display = 'block';
    
    // Load messages
    loadMessages(userId);
}

function startConversation(userId, userName, userRole, companyName) {
    openConversation(userId, userName, userRole, companyName);
    loadMessages(userId);
}

function loadMessages(userId) {
    fetch(`../includes/handlers/message_handler.php?action=get_messages&user_id=${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayMessages(data.messages);
            } else {
                console.error('Error loading messages:', data.message);
                const container = document.getElementById('chatMessages');
                container.innerHTML = '<div class="chat-messages-placeholder"><i class="fas fa-exclamation-triangle"></i><p>Error: ' + (data.message || 'Failed to load messages') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            const container = document.getElementById('chatMessages');
            container.innerHTML = '<div class="chat-messages-placeholder"><i class="fas fa-exclamation-triangle"></i><p>Error loading messages. Please try again.</p></div>';
        });
}

function displayMessages(messages) {
    const container = document.getElementById('chatMessages');
    
    if (messages.length === 0) {
        container.innerHTML = '<div class="chat-messages-placeholder"><i class="fas fa-comment-dots"></i><p>No messages yet. Start the conversation!</p></div>';
        return;
    }
    
    let html = '';
    messages.forEach(msg => {
        const isSender = msg.is_sender;
        const time = formatTime(msg.created_at);
        
        html += `
            <div class="message ${isSender ? 'message-sent' : 'message-received'}">
                <div class="message-content">
                    <div class="message-text">${escapeHtml(msg.message)}</div>
                    <div class="message-time">${time}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function sendMessage(event) {
    event.preventDefault();
    
    if (!currentChatUserId) {
        alert('Please select a conversation first');
        return;
    }
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', currentChatUserId);
    formData.append('message', message);
    
    fetch('../includes/handlers/message_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            loadMessages(currentChatUserId);
            loadConversations();
        } else {
            alert('Failed to send message: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        alert('Error sending message. Please check your connection and try again.');
    });
}

function loadUnreadCount() {
    fetch('../includes/handlers/message_handler.php?action=get_unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                // Update page title with unread count
                document.title = `(${data.count}) Messages - MULTIBIZ`;
            } else {
                document.title = 'Messages - MULTIBIZ';
            }
        })
        .catch(error => console.error('Error loading unread count:', error));
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)}h ago`;
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>

