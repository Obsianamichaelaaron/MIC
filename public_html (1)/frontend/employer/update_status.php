<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Method Not Allowed');
}

$user_id = getCurrentUserId();
$application_id = $_POST['application_id'] ?? 0;
$status = $_POST['status'] ?? '';
$redirect_url = $_POST['redirect_url'] ?? 'candidates.php';

if (!$application_id || !$status) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$conn = getDBConnection();

// Verify the employer owns this application
$stmt = $conn->prepare("
    SELECT a.*, jp.employer_id 
    FROM applications a
    JOIN job_postings jp ON a.job_id = jp.job_id
    WHERE a.application_id = ?
    AND jp.employer_id = (SELECT employer_id FROM employers WHERE user_id = ?)
");
$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Application not found or unauthorized']);
    exit;
}

$application = $result->fetch_assoc();
$stmt->close();

// Update application status
$stmt = $conn->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE application_id = ?");
$stmt->bind_param("si", $status, $application_id);

if ($stmt->execute()) {
    // Add status change to application history
    $history_stmt = $conn->prepare("
        INSERT INTO application_history (application_id, status, notes, changed_by, changed_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $history_notes = $_POST['status_message'] ?? "Status changed to {$status}";
    $history_stmt->bind_param("issi", $application_id, $status, $history_notes, $user_id);
    $history_stmt->execute();
    $history_stmt->close();
    
    $stmt->close();
    $conn->close();
    
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $conn->error]);
}
?>