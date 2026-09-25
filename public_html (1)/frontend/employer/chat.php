<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Messages";

$user_id = getCurrentUserId();
$conn = getDBConnection();

// Get only applicants that the employer can chat with (removed admin and other employers)
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.role, u.email,
           NULL as company_name
    FROM users u
    WHERE u.status = 'active' 
      AND u.role = 'applicant'
      AND u.user_id != ?
    ORDER BY u.first_name, u.last_name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$available_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

include '../includes/header.php';
?>

<!-- Full Width Header Section -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-comments"></i> Messages
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                Connect with job applicants
            </p>
        </div>
    </div>
</div>

<style>
.chat-container {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 0;
    height: 80vh;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.chat-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden; /* Prevent entire sidebar from scrolling */
}

.chat-sidebar-header {
    padding: 1.2rem;
    border-bottom: 1px solid #e0e0e0;
    background: white;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.chat-sidebar-header h3 {
    margin: 0 0 0.8rem 0;
    color: #333;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.search-container {
    position: relative;
    margin-bottom: 0.8rem;
}

.search-input {
    width: 100%;
    padding: 0.6rem 2.5rem 0.6rem 0.8rem;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #0056b3;
    box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.1);
}

.search-icon {
    position: absolute;
    right: 0.8rem;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
}

.chat-user-list {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
    min-height: 0; /* Important for flex child scrolling */
}

.chat-sidebar-footer {
    padding: 1.2rem;
    border-top: 1px solid #e0e0e0;
    background: white;
    flex-shrink: 0; /* Prevent footer from shrinking */
}

.chat-sidebar-footer h4 {
    margin: 0 0 0.8rem 0;
    color: #333;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.available-users-list {
    max-height: 150px;
    overflow-y: auto;
    min-height: 0; /* Important for flex child scrolling */
}

.conversation-item, .available-user-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.8rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 0.4rem;
    border: 1px solid transparent;
}

.conversation-item:hover, .available-user-item:hover {
    background: white;
    border-color: #e0e0e0;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.conversation-item.active {
    background: #e3f2fd;
    border-left: 4px solid #2196F3;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

.conversation-info {
    flex: 1;
    min-width: 0;
}

.conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.2rem;
}

.conversation-name {
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
}

.unread-badge {
    background: #F44336;
    color: white;
    border-radius: 8px;
    padding: 0.15rem 0.4rem;
    font-size: 0.65rem;
    font-weight: 600;
    min-width: 16px;
    text-align: center;
}

.conversation-preview {
    color: #666;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.2rem;
}

.conversation-time {
    color: #999;
    font-size: 0.7rem;
}

.user-info .user-name {
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
    margin-bottom: 0.2rem;
}

.user-info .user-role {
    color: #666;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* FIXED CHAT MAIN LAYOUT */
.chat-main {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}

.chat-header {
    padding: 1.2rem;
    border-bottom: 1px solid #e0e0e0;
    background: white;
    flex-shrink: 0;
}

.chat-header-placeholder {
    text-align: center;
    color: #999;
    padding: 1.5rem;
}

.chat-header-placeholder i {
    font-size: 2.5rem;
    margin-bottom: 0.8rem;
    color: #ddd;
}

.chat-header-active {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.chat-header-info {
    flex: 1;
}

.chat-header-name {
    font-weight: 600;
    color: #333;
    font-size: 1rem;
    margin-bottom: 0.2rem;
}

.chat-header-role {
    color: #666;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* FIXED MESSAGES AREA - PROPERLY SCROLLABLE */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    background: #f8f9fa;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.chat-messages-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 1.2rem;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.chat-messages-placeholder {
    text-align: center;
    color: #999;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    flex: 1;
}

.chat-messages-placeholder i {
    font-size: 3rem;
    margin-bottom: 0.8rem;
    color: #ddd;
}

.message {
    margin-bottom: 0.8rem;
    display: flex;
}

.message-sent {
    justify-content: flex-end;
}

.message-received {
    justify-content: flex-start;
}

.message-content {
    max-width: 70%;
    padding: 0.6rem 0.8rem;
    border-radius: 8px;
    position: relative;
}

.message-sent .message-content {
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    border-bottom-right-radius: 4px;
}

.message-received .message-content {
    background: white;
    color: #333;
    border: 1px solid #e0e0e0;
    border-bottom-left-radius: 4px;
}

.message-text {
    margin-bottom: 0.2rem;
    line-height: 1.4;
    font-size: 0.85rem;
    word-wrap: break-word;
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.8;
    text-align: right;
}

/* FIXED INPUT CONTAINER - STABLE DURING SENDING */
.chat-input-container {
    padding: 1.2rem;
    border-top: 1px solid #e0e0e0;
    background: white;
    flex-shrink: 0;
    display: none;
    position: relative;
}

.chat-input-container.active {
    display: block;
}

#messageForm {
    display: flex;
    gap: 0.6rem;
    align-items: flex-end;
    position: relative;
}

#messageInput {
    flex: 1;
    padding: 0.6rem 0.8rem;
    border: 2px solid #e0e0e0;
    border-radius: 20px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    min-width: 0;
    resize: none;
    line-height: 1.4;
    max-height: 120px;
    overflow-y: auto;
}

#messageInput:focus {
    outline: none;
    border-color: #0056b3;
    box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.1);
}

/* FIXED SEND BUTTON - STABLE POSITION */
.send-button-container {
    position: relative;
    flex-shrink: 0;
}

.btn-send {
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
    min-width: 80px;
    justify-content: center;
    height: 38px;
    box-sizing: border-box;
}

.btn-send:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 86, 179, 0.3);
}

.btn-send:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-send-loading {
    background: linear-gradient(135deg, #666 0%, #555 100%);
}

.chat-loading, .chat-empty {
    text-align: center;
    color: #666;
    padding: 1.5rem;
    font-style: italic;
    font-size: 0.85rem;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn-primary {
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.75rem;
}

.no-results {
    text-align: center;
    color: #999;
    padding: 1rem;
    font-style: italic;
    font-size: 0.85rem;
}

/* Error message styling */
.error-message {
    background: #ffebee;
    color: #c62828;
    padding: 0.8rem;
    border-radius: 4px;
    margin: 0.5rem 0;
    border-left: 4px solid #f44336;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.error-message i {
    font-size: 1rem;
}

.success-message {
    background: #e8f5e8;
    color: #2e7d32;
    padding: 0.8rem;
    border-radius: 4px;
    margin: 0.5rem 0;
    border-left: 4px solid #4caf50;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.success-message i {
    font-size: 1rem;
}

/* Date separator */
.date-separator {
    text-align: center;
    margin: 1rem 0;
}

.date-separator span {
    background: #e0e0e0;
    color: #666;
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.75rem;
}

/* Scrollbar styling */
.chat-user-list::-webkit-scrollbar,
.chat-messages::-webkit-scrollbar,
.available-users-list::-webkit-scrollbar {
    width: 6px;
}

.chat-user-list::-webkit-scrollbar-track,
.chat-messages::-webkit-scrollbar-track,
.available-users-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.chat-user-list::-webkit-scrollbar-thumb,
.chat-messages::-webkit-scrollbar-thumb,
.available-users-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.chat-user-list::-webkit-scrollbar-thumb:hover,
.chat-messages::-webkit-scrollbar-thumb:hover,
.available-users-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Auto-expand textarea */
.auto-expand {
    overflow: hidden;
    resize: none;
}

/* ========== MOBILE OPTIMIZATIONS ========== */
.mobile-header {
    display: none;
    padding: 1rem;
    background: white;
    border-bottom: 1px solid #e0e0e0;
    align-items: center;
    gap: 1rem;
    position: sticky;
    top: 0;
    z-index: 100;
}

.mobile-back-btn {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: #0056b3;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    transition: background-color 0.3s;
}

.mobile-back-btn:hover {
    background: #f0f0f0;
}

.mobile-header-title {
    flex: 1;
    font-weight: 600;
    font-size: 1.1rem;
    color: #333;
}

.mobile-tabs {
    display: none;
    position: sticky;
    top: 0;
    z-index: 90;
    background: white;
    border-bottom: 1px solid #e0e0e0;
}

.mobile-tab {
    flex: 1;
    text-align: center;
    padding: 1rem;
    font-weight: 600;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    cursor: pointer;
}

.mobile-tab.active {
    color: #0056b3;
    border-bottom-color: #0056b3;
}

.mobile-fab {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    display: none;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 86, 179, 0.3);
    z-index: 1000;
    cursor: pointer;
    transition: all 0.3s;
}

.mobile-fab:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 86, 179, 0.4);
}

@media (max-width: 768px) {
    .container {
        padding: 0;
    }
    
    .chat-container {
        grid-template-columns: 1fr;
        height: calc(100vh - 120px);
        border-radius: 0;
        border: none;
    }
    
    .chat-sidebar {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
        background: white;
    }
    
    .chat-sidebar.active {
        display: flex;
    }
    
    .chat-main {
        display: none;
        height: 100%;
    }
    
    .chat-main.active {
        display: flex;
    }
    
    .mobile-header {
        display: flex;
    }
    
    .mobile-tabs {
        display: flex;
    }
    
    .mobile-fab {
        display: flex;
    }
    
    .chat-sidebar-header {
        padding: 1rem;
        padding-top: 4rem;
    }
    
    .chat-sidebar-footer {
        padding: 1rem;
    }
    
    .chat-header {
        padding: 1rem;
        padding-top: 4rem;
    }
    
    .chat-messages-scroll {
        padding: 1rem;
    }
    
    .chat-input-container {
        padding: 1rem;
        padding-bottom: calc(1rem + env(safe-area-inset-bottom));
    }
    
    .message-content {
        max-width: 85%;
    }
    
    .btn-send {
        min-width: 70px;
        padding: 0.5rem 1rem;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        font-size: 0.8rem;
    }
    
    .conversation-item, .available-user-item {
        padding: 0.7rem;
    }
    
    .conversation-name {
        font-size: 0.85rem;
    }
    
    .conversation-preview {
        font-size: 0.75rem;
    }
    
    .conversation-time {
        font-size: 0.65rem;
    }
    
    /* Improve touch targets */
    .conversation-item, .available-user-item, .btn {
        min-height: 44px;
    }
    
    /* Better message bubbles for mobile */
    .message-content {
        padding: 0.8rem 1rem;
    }
    
    .message-text {
        font-size: 0.9rem;
        line-height: 1.5;
    }
    
    /* Safe area support for notched devices */
    @supports(padding: max(0px)) {
        .chat-input-container {
            padding-bottom: max(1rem, env(safe-area-inset-bottom));
        }
    }
}

@media (max-width: 480px) {
    .message-content {
        max-width: 90%;
    }
    
    .chat-header {
        padding: 0.8rem;
        padding-top: 3.5rem;
    }
    
    .chat-messages-scroll {
        padding: 0.8rem;
    }
    
    .chat-input-container {
        padding: 0.8rem;
        padding-bottom: calc(0.8rem + env(safe-area-inset-bottom));
    }
    
    .mobile-fab {
        bottom: 1.5rem;
        right: 1.5rem;
        width: 56px;
        height: 56px;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
}
</style>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        
        <!-- Mobile Header -->
        <div class="mobile-header" id="mobileHeader">
            <button class="mobile-back-btn" id="mobileBackBtn" style="display: none;">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="mobile-header-title" id="mobileHeaderTitle">Messages</div>
        </div>
        
        <!-- Mobile Tabs -->
        <div class="mobile-tabs" id="mobileTabs">
            <div class="mobile-tab active" data-tab="conversations">Conversations</div>
            <div class="mobile-tab" data-tab="contacts">Contacts</div>
        </div>
        
        <!-- Chat Interface -->
        <div class="chat-container">
            <div class="chat-sidebar" id="chatSidebar">
                <div class="chat-sidebar-header">
                    <h3><i class="fas fa-users"></i> Active Conversations</h3>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search conversations...">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="loadConversations()" 
                            style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.4rem 0.8rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease;"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(0, 86, 179, 0.3)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                
                <div class="chat-user-list" id="conversationsList">
                    <div class="chat-loading">Loading conversations...</div>
                </div>
                
                <div class="chat-sidebar-footer">
                    <h4><i class="fas fa-user-plus"></i> Start New Conversation</h4>
                    <div class="available-users-list" id="availableUsersList">
                        <?php if (count($available_users) > 0): ?>
                            <?php foreach ($available_users as $user): ?>
                                <div class="available-user-item" onclick="startConversation(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>', '<?php echo htmlspecialchars($user['role']); ?>', '')">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                        <div class="user-role">
                                            <i class="fas fa-user-graduate"></i> Applicant
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="chat-empty">
                                <i class="fas fa-users"></i>
                                <p>No applicants available for chat</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="chat-main" id="chatMain">
                <div class="chat-header" id="chatHeader">
                    <div class="chat-header-placeholder">
                        <i class="fas fa-comments"></i>
                        <p>Select a conversation or start a new one</p>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-messages-scroll" id="chatMessagesScroll">
                        <div class="chat-messages-placeholder">
                            <i class="fas fa-comment-dots"></i>
                            <p>No conversation selected</p>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input-container" id="chatInputContainer">
                    <div id="messageStatus"></div>
                    <form id="messageForm" onsubmit="sendMessage(event)">
                        <textarea id="messageInput" class="auto-expand" placeholder="Type your message..." rows="1" required></textarea>
                        <div class="send-button-container">
                            <button type="submit" id="sendButton" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Mobile Floating Action Button -->
        <div class="mobile-fab" id="mobileFab">
            <i class="fas fa-comment"></i>
        </div>
    </div>
</div>

<script>
let currentChatUserId = null;
let refreshInterval = null;
let allConversations = [];
let allAvailableUsers = [];
let isSendingMessage = false;
let isMobile = window.innerWidth <= 768;

document.addEventListener('DOMContentLoaded', function() {
    loadConversations();
    loadUnreadCount();
    updateStats();
    
    // Set up search functionality
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function() {
        filterConversations(this.value);
    });
    
    // Set up auto-expand textarea
    const messageInput = document.getElementById('messageInput');
    messageInput.addEventListener('input', autoExpand);
    
    // NEW: Add Enter key event listener for sending messages
    messageInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault(); // Prevent new line
            sendMessage(event); // Send the message
        }
    });
    
    // Set up mobile navigation
    setupMobileNavigation();
    
    // Refresh conversations every 5 seconds
    refreshInterval = setInterval(function() {
        loadConversations();
        if (currentChatUserId) {
            loadMessages(currentChatUserId);
        }
        loadUnreadCount();
        updateStats();
    }, 5000);
    
    // Check for mobile view on resize
    window.addEventListener('resize', function() {
        isMobile = window.innerWidth <= 768;
    });
});

function setupMobileNavigation() {
    // Mobile back button
    const backBtn = document.getElementById('mobileBackBtn');
    backBtn.addEventListener('click', function() {
        if (isChatOpen()) {
            closeChat();
        } else {
            showConversations();
        }
    });
    
    // Mobile tabs
    const tabs = document.querySelectorAll('.mobile-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show appropriate content
            if (tabName === 'conversations') {
                showConversations();
            } else if (tabName === 'contacts') {
                showContacts();
            }
        });
    });
    
    // Mobile FAB
    const fab = document.getElementById('mobileFab');
    fab.addEventListener('click', function() {
        if (isChatOpen()) {
            // If chat is open, focus on message input
            const messageInput = document.getElementById('messageInput');
            messageInput.focus();
        } else {
            // Otherwise, show contacts to start new conversation
            showContacts();
            const contactsTab = document.querySelector('.mobile-tab[data-tab="contacts"]');
            tabs.forEach(t => t.classList.remove('active'));
            contactsTab.classList.add('active');
        }
    });
    
    // Initialize view based on screen size
    if (isMobile) {
        showConversations();
    }
}

function isChatOpen() {
    return document.getElementById('chatMain').classList.contains('active');
}

function showConversations() {
    if (isMobile) {
        document.getElementById('chatSidebar').classList.add('active');
        document.getElementById('chatMain').classList.remove('active');
        document.getElementById('mobileBackBtn').style.display = 'none';
        document.getElementById('mobileHeaderTitle').textContent = 'Messages';
        document.getElementById('mobileFab').innerHTML = '<i class="fas fa-comment"></i>';
    }
}

function showContacts() {
    if (isMobile) {
        document.getElementById('chatSidebar').classList.add('active');
        document.getElementById('chatMain').classList.remove('active');
        document.getElementById('mobileBackBtn').style.display = 'flex';
        document.getElementById('mobileHeaderTitle').textContent = 'Contacts';
        document.getElementById('mobileFab').innerHTML = '<i class="fas fa-comment"></i>';
        
        // Scroll to contacts section
        const contactsSection = document.querySelector('.chat-sidebar-footer');
        if (contactsSection) {
            contactsSection.scrollIntoView({ behavior: 'smooth' });
        }
    }
}

function openConversation(userId, userName, userRole, companyName) {
    currentChatUserId = userId;
    
    // Update header
    const header = document.getElementById('chatHeader');
    
    header.innerHTML = `
        <div class="chat-header-active">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="chat-header-info">
                <div class="chat-header-name">${userName}</div>
                <div class="chat-header-role">
                    <i class="fas fa-user-graduate"></i> Applicant
                </div>
            </div>
        </div>
    `;
    
    // Show input container
    const inputContainer = document.getElementById('chatInputContainer');
    inputContainer.style.display = 'block';
    inputContainer.classList.add('active');
    
    // Clear any previous status messages
    document.getElementById('messageStatus').innerHTML = '';
    
    // Load messages
    loadMessages(userId);
    
    // Focus on message input
    setTimeout(() => {
        const messageInput = document.getElementById('messageInput');
        messageInput.focus();
        messageInput.style.height = 'auto';
        messageInput.style.height = (messageInput.scrollHeight) + 'px';
    }, 100);
    
    // Mobile-specific behavior
    if (isMobile) {
        document.getElementById('chatSidebar').classList.remove('active');
        document.getElementById('chatMain').classList.add('active');
        document.getElementById('mobileBackBtn').style.display = 'flex';
        document.getElementById('mobileHeaderTitle').textContent = userName;
        document.getElementById('mobileFab').innerHTML = '<i class="fas fa-keyboard"></i>';
    }
}

function closeChat() {
    if (isMobile) {
        document.getElementById('chatMain').classList.remove('active');
        document.getElementById('chatSidebar').classList.add('active');
        document.getElementById('mobileBackBtn').style.display = 'none';
        document.getElementById('mobileHeaderTitle').textContent = 'Messages';
        document.getElementById('mobileFab').innerHTML = '<i class="fas fa-comment"></i>';
        
        // Reset chat view
        const header = document.getElementById('chatHeader');
        header.innerHTML = `
            <div class="chat-header-placeholder">
                <i class="fas fa-comments"></i>
                <p>Select a conversation or start a new one</p>
            </div>
        `;
        
        const messagesContainer = document.getElementById('chatMessagesScroll');
        messagesContainer.innerHTML = `
            <div class="chat-messages-placeholder">
                <i class="fas fa-comment-dots"></i>
                <p>No conversation selected</p>
            </div>
        `;
        
        const inputContainer = document.getElementById('chatInputContainer');
        inputContainer.style.display = 'none';
        inputContainer.classList.remove('active');
        
        currentChatUserId = null;
    }
}

function startConversation(userId, userName, userRole, companyName) {
    openConversation(userId, userName, userRole, companyName);
    loadMessages(userId);
}

function autoExpand() {
    const textarea = this;
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';
    
    // Limit maximum height
    if (textarea.scrollHeight > 120) {
        textarea.style.overflowY = 'auto';
        textarea.style.height = '120px';
    } else {
        textarea.style.overflowY = 'hidden';
    }
}

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
                allConversations = data.conversations;
                displayConversations(allConversations);
                updateStats();
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

function filterConversations(searchTerm) {
    const searchLower = searchTerm.toLowerCase().trim();
    
    if (!searchLower) {
        // If search is empty, show all conversations
        displayConversations(allConversations);
        return;
    }
    
    // Filter conversations by name or last message
    const filtered = allConversations.filter(conv => {
        const nameMatch = conv.name.toLowerCase().includes(searchLower);
        const messageMatch = conv.last_message && conv.last_message.toLowerCase().includes(searchLower);
        return nameMatch || messageMatch;
    });
    
    displayConversations(filtered);
}

function displayConversations(conversations) {
    const list = document.getElementById('conversationsList');
    
    if (conversations.length === 0) {
        const searchTerm = document.getElementById('searchInput').value.trim();
        if (searchTerm) {
            list.innerHTML = '<div class="no-results">No conversations found matching "' + searchTerm + '"</div>';
        } else {
            list.innerHTML = '<div class="chat-empty">No conversations yet. Start a new conversation below.</div>';
        }
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
                    <i class="fas fa-user"></i>
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
                const container = document.getElementById('chatMessagesScroll');
                container.innerHTML = '<div class="chat-messages-placeholder"><i class="fas fa-exclamation-triangle"></i><p>Error: ' + (data.message || 'Failed to load messages') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            const container = document.getElementById('chatMessagesScroll');
            container.innerHTML = '<div class="chat-messages-placeholder"><i class="fas fa-exclamation-triangle"></i><p>Error loading messages. Please try again.</p></div>';
        });
}

function displayMessages(messages) {
    const container = document.getElementById('chatMessagesScroll');
    
    if (messages.length === 0) {
        container.innerHTML = '<div class="chat-messages-placeholder"><i class="fas fa-comment-dots"></i><p>No messages yet. Start the conversation!</p></div>';
        return;
    }
    
    let html = '';
    
    // Group messages by date for better organization
    let currentDate = null;
    
    messages.forEach(msg => {
        const isSender = msg.is_sender;
        const time = formatTime(msg.created_at);
        const messageDate = new Date(msg.created_at).toDateString();
        
        // Add date separator if date changes
        if (currentDate !== messageDate) {
            currentDate = messageDate;
            const displayDate = formatDate(msg.created_at);
            html += `
                <div class="date-separator">
                    <span>${displayDate}</span>
                </div>
            `;
        }
        
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
    scrollToBottom();
}

function scrollToBottom() {
    const container = document.getElementById('chatMessages');
    // Use setTimeout to ensure DOM is updated
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 100);
}

function sendMessage(event) {
    event.preventDefault();
    
    if (isSendingMessage) {
        return; // Prevent multiple simultaneous sends
    }
    
    if (!currentChatUserId) {
        showMessageStatus('Please select a conversation first', 'error');
        return;
    }
    
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    isSendingMessage = true;
    
    // FIXED: Update button without changing layout
    sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    sendButton.disabled = true;
    sendButton.classList.add('btn-send-loading');
    
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
            throw new Error('Network response was not ok. Status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            // Reset textarea height
            messageInput.style.height = 'auto';
            
            loadMessages(currentChatUserId);
            loadConversations();
            updateStats();
            showMessageStatus('Message sent successfully!', 'success');
            
            // Focus back on input after sending
            setTimeout(() => {
                messageInput.focus();
            }, 100);
        } else {
            throw new Error(data.message || 'Failed to send message');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showMessageStatus('Error sending message: ' + error.message, 'error');
    })
    .finally(() => {
        isSendingMessage = false;
        // FIXED: Reset button to original state without layout shift
        sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        sendButton.disabled = false;
        sendButton.classList.remove('btn-send-loading');
    });
}

function showMessageStatus(message, type) {
    const statusDiv = document.getElementById('messageStatus');
    const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
    const className = type === 'error' ? 'error-message' : 'success-message';
    
    statusDiv.innerHTML = `
        <div class="${className}">
            <i class="fas ${icon}"></i>
            ${message}
        </div>
    `;
    
    // Auto-hide success messages after 3 seconds
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.innerHTML = '';
        }, 3000);
    }
}

function loadUnreadCount() {
    fetch('../includes/handlers/message_handler.php?action=get_unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update page title with unread count
                if (data.count > 0) {
                    document.title = `(${data.count}) Messages - MULTIBIZ`;
                } else {
                    document.title = 'Messages - MULTIBIZ';
                }
            }
        })
        .catch(error => console.error('Error loading unread count:', error));
}

function updateStats() {
    // Update total conversations count
    const conversations = document.querySelectorAll('.conversation-item');
    // You can update any stats display here if needed
}

function formatDate(timestamp) {
    const date = new Date(timestamp);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (date.toDateString() === today.toDateString()) {
        return 'Today';
    } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
    } else {
        return date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    
    // If same day, show time, else show date
    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
    } else {
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>