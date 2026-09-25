<?php
// includes/components/floating_messages.php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';

if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    $unread_count = 0;
    
    // Get unread message count from database
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as unread_count 
            FROM messages 
            WHERE receiver_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $unread_count = $row['unread_count'];
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Silently fail - don't break the page if message count fails
        error_log("Error getting unread count: " . $e->getMessage());
    }
    ?>
    <style>
    .floating-message-container {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 10000;
        transition: all 0.3s ease;
    }
    
    .floating-message-btn {
        width: 65px;
        height: 65px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 20px rgba(0, 86, 179, 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        font-size: 1.4rem;
        position: relative;
    }
    
    .floating-message-btn:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 8px 25px rgba(0, 86, 179, 0.5);
        color: white;
        text-decoration: none;
    }
    
    .floating-message-btn:active {
        transform: translateY(-1px) scale(1.02);
    }
    
    .message-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #F44336;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 3px solid white;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { 
            transform: scale(1); 
            box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
        }
        50% { 
            transform: scale(1.05);
            box-shadow: 0 0 0 10px rgba(244, 67, 54, 0);
        }
        100% { 
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
        }
    }
    
    .message-tooltip {
        position: absolute;
        right: 75px;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        pointer-events: none;
    }
    
    .message-tooltip::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 100%;
        transform: translateY(-50%);
        border-width: 6px;
        border-style: solid;
        border-color: transparent transparent transparent rgba(0, 0, 0, 0.8);
    }
    
    .floating-message-btn:hover .message-tooltip {
        opacity: 1;
        visibility: visible;
        right: 80px;
    }
    
    /* Ensure it stays above all other content */
    .floating-message-container * {
        box-sizing: border-box;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .floating-message-container {
            bottom: 20px;
            right: 20px;
        }
        
        .floating-message-btn {
            width: 60px;
            height: 60px;
            font-size: 1.3rem;
        }
        
        .message-badge {
            width: 22px;
            height: 22px;
            font-size: 0.7rem;
            border-width: 2px;
        }
        
        .message-tooltip {
            display: none; /* Hide tooltip on mobile */
        }
    }
    
    @media (max-width: 480px) {
        .floating-message-container {
            bottom: 15px;
            right: 15px;
        }
        
        .floating-message-btn {
            width: 55px;
            height: 55px;
            font-size: 1.2rem;
            box-shadow: 0 3px 15px rgba(0, 86, 179, 0.4);
        }
        
        .message-badge {
            width: 20px;
            height: 20px;
            font-size: 0.65rem;
            top: -2px;
            right: -2px;
        }
    }
    
    /* Print styles - hide when printing */
    @media print {
        .floating-message-container {
            display: none !important;
        }
    }
    </style>
    
    <div class="floating-message-container">
        <a href="/frontend/employer/chat.php" class="floating-message-btn" title="Messages">
            <i class="fas fa-comments"></i>
            <?php if ($unread_count > 0): ?>
                <span class="message-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
            <?php endif; ?>
            <span class="message-tooltip">
                <?php echo $unread_count > 0 ? "You have $unread_count unread message(s)" : "Go to Messages"; ?>
            </span>
        </a>
    </div>
    
    <script>
    // Optional: Add some interactive effects
    document.addEventListener('DOMContentLoaded', function() {
        const floatingBtn = document.querySelector('.floating-message-btn');
        
        if (floatingBtn) {
            // Add click animation
            floatingBtn.addEventListener('click', function(e) {
                // Add ripple effect
                const ripple = document.createElement('span');
                ripple.style.cssText = `
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 0;
                    height: 0;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.5);
                    transform: translate(-50%, -50%);
                    animation: ripple 0.6s ease-out;
                `;
                
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes ripple {
                        to {
                            width: 200%;
                            height: 200%;
                            opacity: 0;
                        }
                    }
                `;
                
                document.head.appendChild(style);
                this.appendChild(ripple);
                
                setTimeout(() => {
                    if (ripple.parentNode) {
                        ripple.parentNode.removeChild(ripple);
                    }
                    if (style.parentNode) {
                        style.parentNode.removeChild(style);
                    }
                }, 600);
            });
            
            // Prevent hiding on scroll (common issue with some layouts)
            let lastScrollTop = 0;
            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const container = document.querySelector('.floating-message-container');
                
                if (container) {
                    if (scrollTop > lastScrollTop) {
                        // Scrolling down
                        container.style.transform = 'translateY(0)';
                    } else {
                        // Scrolling up
                        container.style.transform = 'translateY(0)';
                    }
                }
                lastScrollTop = scrollTop;
            });
        }
    });
    </script>
    <?php
}
?>