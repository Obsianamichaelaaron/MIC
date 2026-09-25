<?php
/**
 * Message Handler API
 * Handles sending and receiving messages for the chat system
 * RESTRICTION: Only Employers and Applicants can communicate
 * Applicants cannot message other Applicants
 */

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Log the request for debugging
error_log("Message handler called: " . ($_GET['action'] ?? $_POST['action'] ?? 'unknown'));

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $conn = getDBConnection();
    
    // Check if messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($table_check->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Messages table does not exist. Please run the SQL script to create it.']);
        $conn->close();
        exit;
    }

    switch ($action) {
        case 'send':
            handleSendMessage($conn, $user_id);
            break;
        
        case 'get_conversations':
            handleGetConversations($conn, $user_id);
            break;
        
        case 'get_messages':
            handleGetMessages($conn, $user_id);
            break;
        
        case 'mark_read':
            handleMarkRead($conn, $user_id);
            break;
        
        case 'get_unread_count':
            handleGetUnreadCount($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Message handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleSendMessage($conn, $user_id) {
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if (empty($receiver_id) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Receiver ID and message are required']);
        exit;
    }
    
    // Prevent sending message to self
    if ($receiver_id == $user_id) {
        echo json_encode(['success' => false, 'message' => 'Cannot send message to yourself']);
        exit;
    }
    
    // Get user roles
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $sender_result = $stmt->get_result();
    
    if ($sender_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Sender not found']);
        $stmt->close();
        exit;
    }
    
    $sender_role = $sender_result->fetch_assoc()['role'];
    $stmt->close();
    
    // Verify receiver exists and is active
    $stmt = $conn->prepare("SELECT user_id, role, status FROM users WHERE user_id = ? AND status = 'active'");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $receiver_result = $stmt->get_result();
    
    if ($receiver_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Receiver not found or inactive']);
        $stmt->close();
        exit;
    }
    
    $receiver = $receiver_result->fetch_assoc();
    $receiver_role = $receiver['role'];
    $stmt->close();
    
    // STRICT RESTRICTION: Only allow applicant ↔ employer communication
    // BLOCK: applicant ↔ applicant communication
    if (!(
        ($sender_role === 'applicant' && $receiver_role === 'employer') ||
        ($sender_role === 'employer' && $receiver_role === 'applicant')
    )) {
        if ($sender_role === 'applicant' && $receiver_role === 'applicant') {
            echo json_encode(['success' => false, 'message' => 'Applicants cannot message other applicants. You can only message employers.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Chat is only allowed between applicants and employers']);
        }
        exit;
    }
    
    // Insert message
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("iis", $user_id, $receiver_id, $message);
    
    if ($stmt->execute()) {
        $message_id = $stmt->insert_id;
        echo json_encode([
            'success' => true,
            'message_id' => $message_id,
            'message' => 'Message sent successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function handleGetConversations($conn, $user_id) {
    // Get user role
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        $stmt->close();
        return;
    }
    
    $user_role = $user_result->fetch_assoc()['role'];
    $stmt->close();
    
    // Get all unique conversations with optimized query
    $query = "
        SELECT 
            u.user_id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.role,
            e.company_name,
            last_msg.message as last_message,
            last_msg.created_at as last_message_time,
            COALESCE(unread.count, 0) as unread_count
        FROM (
            SELECT DISTINCT
                CASE 
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as other_user_id
            FROM messages
            WHERE sender_id = ? OR receiver_id = ?
        ) as conversations
        JOIN users u ON u.user_id = conversations.other_user_id
        LEFT JOIN employers e ON e.user_id = u.user_id
        LEFT JOIN (
            SELECT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as other_user_id,
                message,
                created_at
            FROM messages m1
            WHERE (sender_id = ? OR receiver_id = ?)
            AND created_at = (
                SELECT MAX(created_at) 
                FROM messages m2 
                WHERE (m2.sender_id = m1.sender_id AND m2.receiver_id = m1.receiver_id) 
                   OR (m2.sender_id = m1.receiver_id AND m2.receiver_id = m1.sender_id)
            )
        ) as last_msg ON last_msg.other_user_id = u.user_id
        LEFT JOIN (
            SELECT sender_id, COUNT(*) as count
            FROM messages 
            WHERE receiver_id = ? AND is_read = FALSE
            GROUP BY sender_id
        ) as unread ON unread.sender_id = u.user_id
        WHERE u.status = 'active'
    ";
    
    // Add role-based filtering
    if ($user_role === 'applicant') {
        $query .= " AND u.role = 'employer'";
    } else if ($user_role === 'employer') {
        $query .= " AND u.role = 'applicant'";
    }
    
    $query .= " ORDER BY last_msg.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    // Bind parameters (7 user_id parameters for the query)
    $stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $conversations[] = [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'role' => $row['role'],
            'company_name' => $row['company_name'],
            'last_message' => $row['last_message'],
            'last_message_time' => $row['last_message_time'],
            'unread_count' => intval($row['unread_count'])
        ];
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'conversations' => $conversations]);
}

function handleGetMessages($conn, $user_id) {
    $other_user_id = intval($_GET['user_id'] ?? 0);
    
    if (empty($other_user_id)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    // Get user roles to verify chat permission
    $stmt = $conn->prepare("SELECT user_id, role FROM users WHERE user_id IN (?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("ii", $user_id, $other_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    
    if (count($users) !== 2) {
        echo json_encode(['success' => false, 'message' => 'One or both users not found']);
        exit;
    }
    
    $user_roles = [];
    foreach ($users as $user) {
        $user_roles[$user['user_id']] = $user['role'];
    }
    
    // STRICT VERIFICATION: Only allow applicant ↔ employer communication
    // BLOCK: applicant ↔ applicant communication
    if (!(
        ($user_roles[$user_id] === 'applicant' && $user_roles[$other_user_id] === 'employer') ||
        ($user_roles[$user_id] === 'employer' && $user_roles[$other_user_id] === 'applicant')
    )) {
        if ($user_roles[$user_id] === 'applicant' && $user_roles[$other_user_id] === 'applicant') {
            echo json_encode(['success' => false, 'message' => 'Access denied. Applicants cannot access conversations with other applicants.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Access denied. Chat is only allowed between applicants and employers.']);
        }
        exit;
    }
    
    // Get all messages between current user and other user
    $stmt = $conn->prepare("
        SELECT 
            m.*, 
            u.first_name as sender_first_name,
            u.last_name as sender_last_name,
            u.role as sender_role
        FROM messages m
        JOIN users u ON u.user_id = m.sender_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'message_id' => $row['message_id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'message' => $row['message'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'sender_name' => $row['sender_first_name'] . ' ' . $row['sender_last_name'],
            'sender_role' => $row['sender_role'],
            'is_sender' => ($row['sender_id'] == $user_id)
        ];
    }
    
    $stmt->close();
    
    // Mark messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
    if ($stmt) {
        $stmt->bind_param("ii", $other_user_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function handleMarkRead($conn, $user_id) {
    $message_id = intval($_POST['message_id'] ?? 0);
    
    if (empty($message_id)) {
        echo json_encode(['success' => false, 'message' => 'Message ID is required']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE message_id = ? AND receiver_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("ii", $message_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark message as read']);
    }
    
    $stmt->close();
}

function handleGetUnreadCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = FALSE");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode(['success' => true, 'count' => intval($row['count'] ?? 0)]);
}
?>