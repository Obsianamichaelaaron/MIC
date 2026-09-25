<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

$pageTitle = "My Profile";
$user_id = getCurrentUserId();

$extracted_skills_from_registration = [];
if (isset($_SESSION['extracted_skills']) && !empty($_SESSION['extracted_skills'])) {
    $extracted_skills_from_registration = $_SESSION['extracted_skills'];
    unset($_SESSION['extracted_skills']);
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone FROM applicants a JOIN users u ON a.user_id = u.user_id WHERE a.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_skills = [];
if (!empty($applicant['skills'])) {
    $current_skills = array_map('trim', explode(',', $applicant['skills']));
}

if (!empty($extracted_skills_from_registration) && !empty($current_skills)) {
    $all_skills = array_unique(array_filter(array_merge($current_skills, $extracted_skills_from_registration)));
    $skills_string = implode(', ', $all_skills);
    $stmt = $conn->prepare("UPDATE applicants SET skills = ? WHERE user_id = ?");
    $stmt->bind_param("si", $skills_string, $user_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone FROM applicants a JOIN users u ON a.user_id = u.user_id WHERE a.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $current_skills = !empty($applicant['skills']) ? array_map('trim', explode(',', $applicant['skills'])) : [];
}

$stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant_data = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant_data['applicant_id'] ?? null;
$stmt->close();

$selected_qualification_name = null;
$selected_qualification_id = null;
if ($applicant_id) {
    $stmt = $conn->prepare("SELECT qualification_id FROM chatbot_answers WHERE applicant_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $chatbot_result = $stmt->get_result()->fetch_assoc();
    if ($chatbot_result && !empty($chatbot_result['qualification_id'])) {
        $selected_qualification_id = $chatbot_result['qualification_id'];
        $qual_stmt = $conn->prepare("SELECT name FROM qualifications WHERE qualification_id = ?");
        $qual_stmt->bind_param("i", $selected_qualification_id);
        $qual_stmt->execute();
        $qual_result = $qual_stmt->get_result()->fetch_assoc();
        if ($qual_result) $selected_qualification_name = $qual_result['name'];
        $qual_stmt->close();
    }
    $stmt->close();
}

if (empty($selected_qualification_name) && !empty($applicant['qualifications'])) {
    $selected_qualification_name = $applicant['qualifications'];
    $qual_stmt = $conn->prepare("SELECT qualification_id FROM qualifications WHERE name = ? AND status = 'active' LIMIT 1");
    $qual_stmt->bind_param("s", $selected_qualification_name);
    $qual_stmt->execute();
    $qual_result = $qual_stmt->get_result()->fetch_assoc();
    if ($qual_result) $selected_qualification_id = $qual_result['qualification_id'];
    $qual_stmt->close();
}

$is_other_qualification = !empty($selected_qualification_name) && empty($selected_qualification_id);

$stmt = $conn->prepare("SELECT qualification_id, name FROM qualifications WHERE status = 'active' ORDER BY name");
$stmt->execute();
$qualifications_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function extractSkillsFromResume($resume_path, $applicant_name, $file_ext = 'pdf') {
    if ($file_ext !== 'pdf') return [];
    $api_url = "https://capstone-api-n66l.onrender.com/upload_resume";
    if (file_exists($resume_path)) {
        $ch = curl_init();
        $post_data = ['applicant_name' => $applicant_name, 'resume' => new CURLFile($resume_path, 'application/pdf', 'resume.pdf')];
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 201) {
            $data = json_decode($response, true);
            return $data['parsed']['skills'] ?? [];
        }
    }
    return [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $experience_years = intval($_POST['experience_years'] ?? 0);
    $education_level = trim($_POST['education_level'] ?? '');

    $qualification_select_raw = $_POST['qualification_select'] ?? '';
    $qualification_id = 0;
    $qualification_name = '';

    if ($qualification_select_raw === 'other') {
        $qualification_name = trim($_POST['qualification_other'] ?? '');
    } elseif (intval($qualification_select_raw) > 0) {
        $qualification_id = intval($qualification_select_raw);
        $qual_stmt = $conn->prepare("SELECT name FROM qualifications WHERE qualification_id = ?");
        $qual_stmt->bind_param("i", $qualification_id);
        $qual_stmt->execute();
        $qual_result = $qual_stmt->get_result()->fetch_assoc();
        if ($qual_result) $qualification_name = $qual_result['name'];
        $qual_stmt->close();
        if ($applicant_id && $qualification_id != ($selected_qualification_id ?? 0)) {
            $stmt_chatbot = $conn->prepare("INSERT INTO chatbot_answers (applicant_id, qualification_id, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE qualification_id = ?");
            $stmt_chatbot->bind_param("iii", $applicant_id, $qualification_id, $qualification_id);
            $stmt_chatbot->execute();
            $stmt_chatbot->close();
        }
    }

    if (!empty($qualification_name)) $qualifications = $qualification_name;

    $resume_file = null;
    $profile_pic_file = null;

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $photo = $_FILES['profile_photo'];
        $photo_ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
        $allowed_photo_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $allowed_photo_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $actual_mime = mime_content_type($photo['tmp_name']);
        if (!in_array($photo_ext, $allowed_photo_exts) || !in_array($actual_mime, $allowed_photo_mimes)) {
            $error = "Profile photo must be an image file (JPG, PNG, GIF, WEBP, or BMP).";
        } elseif ($photo['size'] > 5242880) {
            $error = "Profile photo must be less than 5MB.";
        } else {
            $pic_dir = __DIR__ . '/../uploads/profile_pics/';
            if (!file_exists($pic_dir)) mkdir($pic_dir, 0777, true);
            $stmt_old = $conn->prepare("SELECT profile_pic FROM applicants WHERE user_id = ?");
            $stmt_old->bind_param("i", $user_id);
            $stmt_old->execute();
            $old = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();
            if (!empty($old['profile_pic'])) { $old_path = __DIR__ . '/../' . $old['profile_pic']; if (file_exists($old_path)) @unlink($old_path); }
            $new_photo_name = 'photo_' . $user_id . '_' . time() . '.' . $photo_ext;
            if (move_uploaded_file($photo['tmp_name'], $pic_dir . $new_photo_name)) {
                $profile_pic_file = 'uploads/profile_pics/' . $new_photo_name;
                $success = "Profile photo updated successfully!";
            } else { $error = "Failed to upload profile photo."; }
        }
    }

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resume'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, ['pdf', 'doc', 'docx'])) {
            $error = "Only PDF, DOC, or DOCX files are allowed";
        } elseif ($file['size'] > 5242880) {
            $error = "File size must be less than 5MB";
        } else {
            $upload_dir = __DIR__ . '/../uploads/resumes/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $stmt_check = $conn->prepare("SELECT resume_file FROM applicants WHERE user_id = ?");
            $stmt_check->bind_param("i", $user_id);
            $stmt_check->execute();
            $old_resume = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();
            if (!empty($old_resume['resume_file'])) { $old_file_path = __DIR__ . '/../' . $old_resume['resume_file']; if (file_exists($old_file_path)) @unlink($old_file_path); }
            $new_filename = uniqid('resume_', true) . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $resume_file = 'uploads/resumes/' . $new_filename;
                $applicant_name = $first_name . ' ' . $last_name;
                $newly_extracted_skills = extractSkillsFromResume($upload_path, $applicant_name, $file_ext);
                if (!empty($newly_extracted_skills)) {
                    $existing_skills = !empty($skills) ? array_map('trim', explode(',', $skills)) : [];
                    $all_skills = array_unique(array_filter(array_merge($existing_skills, $newly_extracted_skills)));
                    $skills = implode(', ', $all_skills);
                    $success = "Resume uploaded — " . count($newly_extracted_skills) . " skills auto-extracted.";
                } elseif ($file_ext === 'pdf') {
                    $success = "Resume uploaded. No skills could be extracted automatically.";
                } else {
                    $success = "Resume uploaded. Skill extraction is only available for PDF files.";
                }
            } else { $error = "Failed to upload resume file"; }
        }
    }

    if (!isset($error)) {
        $stmt_user = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt_user->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
        if ($stmt_user->execute()) {
            $is_completed = !empty($skills) && !empty($qualifications);
            if ($resume_file && !empty($profile_pic_file)) {
                $stmt = $conn->prepare("UPDATE applicants SET skills=?, qualifications=?, experience_years=?, education_level=?, resume_file=?, profile_pic=?, profile_completed=? WHERE user_id=?");
                $stmt->bind_param("ssisssii", $skills, $qualifications, $experience_years, $education_level, $resume_file, $profile_pic_file, $is_completed, $user_id);
            } elseif ($resume_file) {
                $stmt = $conn->prepare("UPDATE applicants SET skills=?, qualifications=?, experience_years=?, education_level=?, resume_file=?, profile_completed=? WHERE user_id=?");
                $stmt->bind_param("ssissii", $skills, $qualifications, $experience_years, $education_level, $resume_file, $is_completed, $user_id);
            } elseif (!empty($profile_pic_file)) {
                $stmt = $conn->prepare("UPDATE applicants SET skills=?, qualifications=?, experience_years=?, education_level=?, profile_pic=?, profile_completed=? WHERE user_id=?");
                $stmt->bind_param("ssissii", $skills, $qualifications, $experience_years, $education_level, $profile_pic_file, $is_completed, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE applicants SET skills=?, qualifications=?, experience_years=?, education_level=?, profile_completed=? WHERE user_id=?");
                $stmt->bind_param("ssisii", $skills, $qualifications, $experience_years, $education_level, $is_completed, $user_id);
            }
            if ($stmt->execute()) {
                if (empty($success)) $success = "Profile updated successfully.";
                $stmt_reload = $conn->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone FROM applicants a JOIN users u ON a.user_id = u.user_id WHERE a.user_id = ?");
                $stmt_reload->bind_param("i", $user_id);
                $stmt_reload->execute();
                $applicant = $stmt_reload->get_result()->fetch_assoc();
                $stmt_reload->close();
                $current_skills = !empty($applicant['skills']) ? array_map('trim', explode(',', $applicant['skills'])) : [];
                $selected_qualification_id = $qualification_id;
                if ($qualification_id > 0) {
                    $qual_stmt = $conn->prepare("SELECT name FROM qualifications WHERE qualification_id = ?");
                    $qual_stmt->bind_param("i", $qualification_id);
                    $qual_stmt->execute();
                    $qual_result = $qual_stmt->get_result()->fetch_assoc();
                    if ($qual_result) $selected_qualification_name = $qual_result['name'];
                    $qual_stmt->close();
                }
            } else { $error = "Failed to update profile information"; }
            $stmt->close();
        } else { $error = "Failed to update personal information"; }
        $stmt_user->close();
    }
}

$all_feedback = [];
$accepted_jobs = [];
if ($applicant_id) {
    $table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt_feedback = $conn->prepare("SELECT cf.*, e.company_name, u.first_name as employer_first, u.last_name as employer_last, jp.title as job_title, a.status as application_status FROM candidate_feedback cf JOIN employers e ON cf.employer_id = e.employer_id JOIN users u ON e.user_id = u.user_id JOIN applications a ON cf.application_id = a.application_id JOIN job_postings jp ON a.job_id = jp.job_id WHERE cf.applicant_id = ? ORDER BY cf.created_at DESC");
        $stmt_feedback->bind_param("i", $applicant_id);
        $stmt_feedback->execute();
        $all_feedback = $stmt_feedback->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_feedback->close();
    }
    $stmt = $conn->prepare("SELECT a.*, jp.job_id, jp.title, jp.description, jp.location, jp.employment_type, jp.salary_range, jp.posted_at, e.company_name, e.company_address, e.company_website, a.applied_at, a.updated_at, a.reviewed_by_name FROM applications a JOIN job_postings jp ON a.job_id = jp.job_id LEFT JOIN employers e ON jp.employer_id = e.employer_id WHERE a.applicant_id = ? AND a.status = 'accepted' ORDER BY a.updated_at DESC");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $accepted_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $unread_count = $row['unread_count'];
    $stmt->close();
} catch (Exception $e) { error_log("Error getting unread count: " . $e->getMessage()); }

$conn->close();

// Derived display values
$full_name = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
$initials = strtoupper(substr($applicant['first_name'] ?? 'U', 0, 1) . substr($applicant['last_name'] ?? '', 0, 1));

include '../includes/header.php';
?>

<!-- ═══════════════════ PROFILE PAGE STYLES (EXACTLY MATCHING DASHBOARD) ═══════════════════ -->
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap');

/* ── Base ── */
.pr-page { font-family: 'DM Sans', sans-serif; background: #f0f4f9; min-height: 100vh; }

/* ── Hero Banner (EXACT MATCH) ── */
.pr-hero {
    background: linear-gradient(135deg, #0a3d6b 0%, #1866a3 55%, #2196f3 100%);
    padding: 2.5rem 0 4.5rem;
    position: relative;
    overflow: hidden;
}
.pr-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url('../images/bg.jpg') center/cover no-repeat;
    
}
.pr-hero::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0; right: 0;
    height: 60px;
    background: #f0f4f9;
    clip-path: ellipse(55% 100% at 50% 100%);
}
.pr-hero-inner {
    position: relative;
    z-index: 1;
    text-align: center;
    color: #fff;
}
.pr-hero-inner h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    font-weight: 400;
    margin: 0 0 .4rem;
    letter-spacing: .3px;
}
.pr-hero-inner p {
    font-size: .92rem;
    color: rgba(255,255,255,.8);
    margin: 0;
}

/* ── Page shell - 3 COLUMN LAYOUT (EXACT MATCH) ── */
.pr-shell {
    max-width: 1400px;
    margin: -2rem auto 4rem;
    padding: 0 1.25rem;
    display: grid;
    grid-template-columns: 280px 1fr 360px;
    gap: 1.75rem;
    align-items: start;
}

/* ── Sidebar (EXACT MATCH) ── */
.pr-sidebar {
    position: sticky;
    top: 80px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Profile Card (Matches Dashboard ID Card) */
.pr-id-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    overflow: hidden;
}
.pr-id-top {
   background: linear-gradient(135deg, #0a3d6b, #792d80);
    padding: 1.6rem 1.2rem 3.5rem;
    text-align: center;
    position: relative;
}
.pr-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,.18);
    border: 3px solid rgba(255,255,255,.5);
    margin: 0 auto;
    display: flex; align-items: center; justify-content: center;
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    color: #fff;
    letter-spacing: 1px;
    overflow: hidden;
    position: relative;
    cursor: pointer;
}
.pr-avatar img { width: 100%; height: 100%; object-fit: cover; }
.pr-avatar-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.5);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    opacity: 0;
    transition: opacity .2s;
}
.pr-avatar-overlay i { color: white; font-size: 1rem; }
.pr-avatar:hover .pr-avatar-overlay { opacity: 1; }
.pr-id-body {
    padding: 2.2rem 1.2rem 1.4rem;
    text-align: center;
}
.pr-id-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: .2rem;
}
.pr-id-email {
    font-size: .8rem;
    color: #888;
    margin-bottom: .8rem;
}
.pr-id-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    background: #e8f0fe;
    color: #1866a3;
    font-size: .75rem;
    font-weight: 600;
    padding: .3rem .75rem;
    border-radius: 20px;
    margin-bottom: 1rem;
}

/* Completion Bar */
.pr-complete-label {
    display: flex;
    justify-content: space-between;
    font-size: .72rem;
    color: #666;
    margin-bottom: .35rem;
}
.pr-complete-label span:last-child { color: #1866a3; font-weight: 600; }
.pr-bar-track {
    height: 5px;
    background: #e4eaf4;
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.pr-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #0a3d6b, #2196f3);
    border-radius: 99px;
    transition: width .6s ease;
}

/* Stats row (Matches Dashboard) */
.pr-id-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .6rem;
    border-top: 1px solid #f0f0f0;
    padding-top: 1rem;
}
.pr-stat {
    text-align: center;
}
.pr-stat-num {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1866a3;
}
.pr-stat-lbl {
    font-size: .72rem;
    color: #999;
}

/* Sidebar Nav (Matches Dashboard) */
.pr-sidenav {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    overflow: hidden;
}
.pr-sidenav-title {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #aaa;
    padding: 1rem 1.2rem .5rem;
}
.pr-sidenav a {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1.2rem;
    font-size: .87rem;
    font-weight: 500;
    color: #555;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all .18s;
}
.pr-sidenav a:hover, .pr-sidenav a.active {
    background: #f0f5ff;
    color: #1866a3;
    border-left-color: #1866a3;
}
.pr-sidenav a i { width: 18px; text-align: center; font-size: .85rem; }

/* ── Center Column (Main Content) ── */
.pr-center {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* ── Right Column (Matches Dashboard Right Column) ── */
.pr-right {
    position: sticky;
    top: 80px;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.right-col-header {
    background: linear-gradient(135deg, #1a2f5a, #942c8b);
    border-radius: 16px 16px 0 0;
    padding: 1rem 1.2rem;
    color: white;
}
.right-col-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.right-col-header p {
    font-size: 0.7rem;
    margin: 4px 0 0;
    opacity: 0.85;
}
.right-content {
    background: #fff;
    border-radius: 0 0 16px 16px;
    padding: 1.2rem;
}

/* Preview Card */
.preview-card {
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 0.9rem;
}
.preview-title {
    font-weight: 700;
    color: #1866a3;
    font-size: 0.85rem;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f0f0f0;
}
.preview-item {
    font-size: 0.7rem;
    color: #666;
    padding: 6px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.preview-item i { width: 18px; color: #1866a3; }

/* Alert Banners (Matches Dashboard) */
.pr-alert {
    border-radius: 10px;
    padding: .9rem 1.1rem;
    font-size: .88rem;
    display: flex;
    align-items: flex-start;
    gap: .65rem;
    animation: fadeIn .3s ease;
}
@keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.pr-alert-success { background: #f0faf0; color: #2e7d32; border: 1px solid #c8e6c9; }
.pr-alert-error { background: #fff5f5; color: #c62828; border: 1px solid #ffcdd2; }
.pr-alert-info { background: #e8f4ff; color: #1565c0; border: 1px solid #bbdefb; }
.pr-alert i { margin-top: .15rem; flex-shrink: 0; }

/* Section Cards (Matches Dashboard) */
.pr-section {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
    overflow: hidden;
    transition: box-shadow .25s;
}
.pr-section:hover { box-shadow: 0 6px 28px rgba(0,0,0,.1); }
.pr-section-head {
    padding: 1.4rem 1.75rem;
    border-bottom: 1px solid #f2f4f8;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20px;
}
.pr-section-head-left {
    display: flex;
    align-items: center;
    gap: .7rem;
}
.pr-section-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: #e8f0fe;
    color: #1866a3;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}
.pr-section-icon.green { background: #e8f5e9; color: #4CAF50; }
.pr-section-icon.purple { background: #f3e5f5; color: #9C27B0; }
.pr-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0;
}
.pr-section-sub {
    font-size: .78rem;
    color: #999;
    margin: .1rem 0 0;
}
.pr-section-body {
    padding: 1.5rem 1.75rem;
}

/* Form Elements */
.pr-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.pr-field {
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.pr-label {
    font-size: .76rem;
    font-weight: 600;
    color: #555;
}
.pr-input, .pr-select, .pr-textarea {
    font-family: 'DM Sans', sans-serif;
    font-size: .88rem;
    color: #1a1a2e;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: .62rem .9rem;
    width: 100%;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.pr-input:focus, .pr-select:focus, .pr-textarea:focus {
    border-color: #1866a3;
    box-shadow: 0 0 0 3px rgba(24,102,163,.1);
}
.pr-textarea {
    resize: vertical;
    min-height: 88px;
}
.pr-hint {
    font-size: .73rem;
    color: #999;
}
.pr-hint i { margin-right: .25rem; }

/* Skills Display */
.pr-skills-display {
    background: #f8fafd;
    border: 1px solid #f0f4f9;
    border-radius: 10px;
    padding: .85rem;
    min-height: 48px;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-content: flex-start;
}
.pr-skill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .28rem .7rem;
    border-radius: 99px;
    font-size: .75rem;
    font-weight: 500;
}
.pr-skill.auto {
    background: #e8f0fe;
    color: #1866a3;
    border: 1px solid #bbdefb;
}
.pr-skill.manual {
    background: #f0f4f9;
    color: #555;
    border: 1px solid #e2e8f0;
}

/* Resume Box */
.pr-resume-box {
    background: #f8fafd;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.pr-resume-file-info {
    display: flex;
    align-items: center;
    gap: .85rem;
}
.pr-resume-file-icon { font-size: 1.75rem; }
.pr-resume-file-name {
    font-weight: 600;
    font-size: .86rem;
    color: #1a1a2e;
}
.pr-resume-file-meta {
    font-size: .74rem;
    color: #999;
}
.pr-resume-actions {
    display: flex;
    gap: .5rem;
}
.pr-btn-xs {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .38rem .8rem;
    border-radius: 6px;
    font-size: .76rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
}
.pr-btn-view { background: #e8f0fe; color: #1866a3; }
.pr-btn-dl { background: #e8f5e9; color: #4CAF50; }
.pr-btn-view:hover, .pr-btn-dl:hover { transform: translateY(-1px); opacity: .85; }

/* File Upload Zone */
.pr-file-zone {
    border: 1.5px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafd;
    padding: 1.2rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    position: relative;
}
.pr-file-zone:hover {
    border-color: #1866a3;
    background: #e8f0fe;
}
.pr-file-zone i { font-size: 1.4rem; color: #888; margin-bottom: .4rem; display: block; }
.pr-file-zone span { font-size: .82rem; color: #666; }
.pr-file-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

/* Qualification Status */
.pr-qual-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .8rem 1rem;
    border-radius: 10px;
    font-size: .84rem;
}
.pr-qual-found { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; }
.pr-qual-missing { background: #fff8e1; border: 1px solid #ffe0b2; color: #e65100; }
.pr-qual-badge {
    font-size: .69rem;
    font-weight: 700;
    padding: .25rem .6rem;
    border-radius: 99px;
    background: #4CAF50;
    color: #fff;
}
.pr-qual-badge.custom { background: #ff9800; }

/* Divider */
.pr-divider {
    height: 1px;
    background: #f0f4f9;
    border: none;
    margin: 0.5rem 0;
}

/* Quick Actions Box (Matches Dashboard) */
.pr-quick-actions {
    background: #f8fafd;
    border-radius: 14px;
    padding: 1rem 1.2rem;
    border-left: 4px solid #9C27B0;
}
.pr-quick-actions h3 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.4rem;
}
.pr-quick-actions p {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 0.8rem;
    line-height: 1.5;
}
.pr-btn-group {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}
.pr-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all .2s;
    font-size: 0.8rem;
}
.pr-btn-primary {
    background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
    color: white;
}
.pr-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
}
.pr-btn-secondary {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
}
.pr-btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}
.pr-avatar-hint {
    text-align: center;
    margin-top: 8px;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.8);
    cursor: pointer;
    display: inline-block;
    width: 100%;
}
.pr-avatar-hint i {
    font-size: 0.65rem;
    margin-right: 4px;
}

/* Submit Bar */
.pr-submit-bar {
    background: #fff;
    border: 1px solid #f0f4f9;
    border-radius: 16px;
    padding: 1.25rem 1.75rem;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
}
.pr-submit-hint {
    font-size: .78rem;
    color: #888;
}
.pr-btn-save {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: .72rem 1.75rem;
    font-size: .9rem;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all .2s;
}
.pr-btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
}

/* AI Extraction Banner */
.pr-ai-banner {
    background: #e8f0fe;
    border: 1px solid #bbdefb;
    border-radius: 12px;
    padding: 1rem 1.2rem;
}
.pr-ai-banner-title {
    font-size: .88rem;
    font-weight: 700;
    color: #1866a3;
    margin-bottom: .6rem;
    display: flex;
    align-items: center;
    gap: .45rem;
}
.pr-ai-skills {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-bottom: .5rem;
}
.pr-ai-note {
    font-size: .73rem;
    color: #1866a3;
}

/* Feedback Cards (Matches Dashboard) */
.pr-feedback-card {
    border: 1px solid #f0f0f0;
    border-radius: 12px;
    padding: 1rem;
    transition: all .2s;
    margin-bottom: 1rem;
}
.pr-feedback-card:hover {
    border-color: #1866a3;
    box-shadow: 0 4px 16px rgba(24,102,163,.1);
}
.pr-feedback-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.8rem;
}
.pr-feedback-job {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1866a3;
}
.pr-feedback-company {
    font-size: 0.75rem;
    color: #666;
    margin-top: 0.2rem;
}
.pr-score-pill {
    text-align: right;
}
.pr-score-big {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1866a3;
    line-height: 1;
}
.pr-score-lbl {
    font-size: 0.65rem;
    color: #999;
}
.pr-status-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 99px;
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
    display: inline-block;
    margin-bottom: 0.25rem;
}
.pr-feedback-body {
    font-size: 0.8rem;
    color: #555;
    line-height: 1.6;
    margin-bottom: 0.8rem;
}
.pr-feedback-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    color: #999;
    padding-top: 0.7rem;
    border-top: 1px solid #f0f4f9;
}

/* Job Cards (Matches Dashboard Compact Cards) */
.pr-job-card {
    border: 1px solid #f0f0f0;
    border-left: 3px solid #4CAF50;
    border-radius: 12px;
    padding: 0.9rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
}
.pr-job-card:hover {
    border-color: #1866a3;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.pr-job-title {
    font-weight: 700;
    color: #1866a3;
    font-size: 0.85rem;
}
.pr-job-co {
    font-size: 0.7rem;
    color: #888;
    margin-top: 0.2rem;
}
.pr-accepted-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.pr-job-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 8px 0;
}
.pr-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.65rem;
    color: #666;
    background: #f8fafd;
    padding: 3px 8px;
    border-radius: 99px;
    border: 1px solid #e2e8f0;
}
.pr-chip.salary { color: #28a745; background: #e8f5e9; }
.pr-job-desc {
    font-size: 0.75rem;
    color: #666;
    line-height: 1.5;
    margin-bottom: 8px;
}
.pr-job-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 8px;
    border-top: 1px solid #f0f4f9;
    font-size: 0.7rem;
    color: #999;
}
.pr-job-date-accepted { color: #28a745; font-weight: 600; }

/* Empty State (Matches Dashboard) */
.pr-empty-state {
    text-align: center;
    padding: 1.5rem;
    color: #666;
}
.pr-empty-state i {
    font-size: 2rem;
    color: #ddd;
    margin-bottom: 0.5rem;
}
.pr-empty-state h3 {
    font-size: 0.85rem;
    color: #333;
    margin-bottom: 0.3rem;
}
.pr-empty-state p {
    font-size: 0.7rem;
}

/* Floating Chat (EXACT MATCH) */
.pr-float {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
}
.pr-float-btn {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1866a3, #0a3d6b);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    text-decoration: none;
    box-shadow: 0 6px 20px rgba(24,102,163,.45);
    transition: all .25s;
    position: relative;
}
.pr-float-btn:hover {
    transform: translateY(-3px) scale(1.06);
    color: #fff;
}
.pr-float-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: #f44336;
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: .7rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2.5px solid #fff;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(244,67,54,.6); }
    70% { box-shadow: 0 0 0 8px rgba(244,67,54,0); }
    100% { box-shadow: 0 0 0 0 rgba(244,67,54,0); }
}
.pr-float-tip {
    position: absolute;
    right: 68px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,.78);
    color: #fff;
    padding: .45rem .9rem;
    border-radius: 7px;
    font-size: .78rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all .2s;
    pointer-events: none;
}
.pr-float-tip::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 100%;
    transform: translateY(-50%);
    border: 5px solid transparent;
    border-left-color: rgba(0,0,0,.78);
}
.pr-float-btn:hover .pr-float-tip {
    opacity: 1;
    visibility: visible;
    right: 72px;
}

/* Dark Mode Support (EXACT MATCH) */
[data-theme="dark"] .pr-page { background: #0f1117; }
[data-theme="dark"] .pr-hero::after { background: #0f1117; }
[data-theme="dark"] .pr-id-card,
[data-theme="dark"] .pr-sidenav,
[data-theme="dark"] .pr-section,
[data-theme="dark"] .right-content,
[data-theme="dark"] .pr-submit-bar { background: #1a1d27; box-shadow: 0 2px 16px rgba(0,0,0,.3); }
[data-theme="dark"] .pr-section-head { border-bottom-color: #2d3348; }
[data-theme="dark"] .pr-section-title,
[data-theme="dark"] .pr-id-name,
[data-theme="dark"] .pr-stat-num,
[data-theme="dark"] .pr-resume-file-name { color: #e2e8f0; }
[data-theme="dark"] .pr-id-email,
[data-theme="dark"] .pr-stat-lbl,
[data-theme="dark"] .pr-section-sub,
[data-theme="dark"] .pr-label,
[data-theme="dark"] .pr-hint { color: #94a3b8; }
[data-theme="dark"] .pr-input,
[data-theme="dark"] .pr-select,
[data-theme="dark"] .pr-textarea { background: #0f1117; border-color: #2d3348; color: #e2e8f0; }
[data-theme="dark"] .pr-skills-display,
[data-theme="dark"] .pr-resume-box,
[data-theme="dark"] .pr-file-zone { background: #0f1117; border-color: #2d3348; }
[data-theme="dark"] .pr-skill.manual { background: #1a1d27; color: #94a3b8; }
[data-theme="dark"] .pr-feedback-card,
[data-theme="dark"] .pr-job-card,
[data-theme="dark"] .preview-card { background: #1a1d27; border-color: #2d3348; }
[data-theme="dark"] .pr-quick-actions { background: #1e2235; }
[data-theme="dark"] .pr-quick-actions h3,
[data-theme="dark"] .pr-quick-actions p { color: #cbd5e1; }
[data-theme="dark"] .pr-sidenav a { color: #94a3b8; }
[data-theme="dark"] .pr-sidenav a:hover,
[data-theme="dark"] .pr-sidenav a.active { background: rgba(99,179,237,.08); color: #7aa2f7; border-left-color: #7aa2f7; }

/* Responsive (EXACT MATCH) */
@media (max-width: 1100px) {
    .pr-shell {
        grid-template-columns: 260px 1fr;
    }
    .pr-right {
        grid-column: span 2;
        position: static;
    }
}
@media (max-width: 768px) {
    .pr-shell {
        grid-template-columns: 1fr;
    }
    .pr-right {
        grid-column: span 1;
    }
    .pr-grid-2 { grid-template-columns: 1fr; gap: 0.8rem; }
}
@media (max-width: 480px) {
    .pr-hero-inner h1 { font-size: 1.5rem; }
    .pr-shell { padding: 0 .75rem; margin-top: -1.5rem; }
    .pr-section-body { padding: 1rem; }
    .pr-float { bottom: 16px; right: 16px; }
}
</style>

<div class="pr-page">

<!-- ── Hero Banner (EXACT MATCH) ── -->
<div class="pr-hero">
    <div class="pr-hero-inner">
        <h1><i class="fas fa-user-circle" style="font-size:1.6rem;vertical-align:middle;margin-right:.4rem;"></i> My Profile</h1>
        <p>Manage your professional information and track your career journey</p>
    </div>
</div>

<!-- ── 3-COLUMN SHELL (EXACT MATCH) ── -->
<div class="pr-shell">

    <!-- ══ COLUMN 1: SIDEBAR (EXACT MATCH) ══ -->
    <aside class="pr-sidebar">

        <!-- Identity Card (Matches Dashboard) -->
        <div class="pr-id-card">
            <div class="pr-id-top">
              <div class="pr-avatar" onclick="document.getElementById('profilePhotoInput').click()">
    <?php if (!empty($applicant['profile_pic'])): ?>
        <img src="../<?php echo htmlspecialchars($applicant['profile_pic']); ?>" alt="Profile">
    <?php else: ?>
        <?php echo $initials; ?>
    <?php endif; ?>
    <div class="pr-avatar-overlay">
        <i class="fas fa-camera"></i>
    </div>
</div>
<div class="pr-avatar-hint">
    <i class="fas fa-mouse-pointer"></i> Click to change profile picture
</div>
                <input type="file" id="profilePhotoInput" accept="image/*" style="display:none;" onchange="handlePhotoChange(this)">
            </div>
            <div class="pr-id-body">
                <div class="pr-id-name"><?php echo htmlspecialchars($full_name); ?></div>
                <div class="pr-id-email"><?php echo htmlspecialchars($applicant['email'] ?? ''); ?></div>
                <div class="pr-id-badge"><i class="fas fa-user-tie"></i> Applicant</div>

                <?php
                $completion = 0;
                if (!empty($applicant['first_name'])) $completion += 20;
                if (!empty($applicant['phone'])) $completion += 10;
                if (!empty($applicant['skills'])) $completion += 25;
                if (!empty($applicant['qualifications'])) $completion += 20;
                if (!empty($applicant['resume_file'])) $completion += 25;
                ?>
                <div class="pr-complete-label">
                    <span>Profile completeness</span>
                    <span><?php echo $completion; ?>%</span>
                </div>
                <div class="pr-bar-track">
                    <div class="pr-bar-fill" style="width:<?php echo $completion; ?>%"></div>
                </div>

                <div class="pr-id-stats">
                    <div class="pr-stat">
                        <div class="pr-stat-num"><?php echo count($current_skills); ?></div>
                        <div class="pr-stat-lbl">Skills</div>
                    </div>
                    <div class="pr-stat">
                        <div class="pr-stat-num"><?php echo $applicant['experience_years'] ?? 0; ?></div>
                        <div class="pr-stat-lbl">Years</div>
                    </div>
                    <div class="pr-stat">
                        <div class="pr-stat-num"><?php echo count($accepted_jobs); ?></div>
                        <div class="pr-stat-lbl">Offers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Navigation (Matches Dashboard) -->
        <nav class="pr-sidenav">
            <div class="pr-sidenav-title">Profile Sections</div>
            <a href="#sec-personal"><i class="fas fa-user"></i> Personal Info</a>
            <a href="#sec-resume"><i class="fas fa-file-alt"></i> Resume</a>
            <a href="#sec-skills"><i class="fas fa-tools"></i> Skills</a>
            <a href="#sec-qualifications"><i class="fas fa-graduation-cap"></i> Qualifications</a>
            <?php if (count($all_feedback) > 0): ?>
                <a href="#sec-feedback"><i class="fas fa-comments"></i> Feedback</a>
            <?php endif; ?>
            <?php if (count($accepted_jobs) > 0): ?>
                <a href="#sec-accepted"><i class="fas fa-check-circle"></i> Offers</a>
            <?php endif; ?>
        </nav>

    </aside>

    <!-- ══ COLUMN 2: CENTER MAIN CONTENT ══ -->
    <div class="pr-center">

        <!-- Toast Notifications (Matches Dashboard) -->
        <?php if (isset($success)): ?>
            <div class="pr-alert pr-alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="pr-alert pr-alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- AI Extraction Banner -->
        <?php if (!empty($extracted_skills_from_registration)): ?>
            <div class="pr-ai-banner">
                <div class="pr-ai-banner-title">
                    <i class="fas fa-magic"></i> Skills extracted from your resume
                </div>
                <div class="pr-ai-skills">
                    <?php foreach ($extracted_skills_from_registration as $sk): ?>
                        <span class="pr-skill auto"><i class="fas fa-check" style="font-size:.65rem;"></i><?php echo htmlspecialchars($sk); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="pr-ai-note"><i class="fas fa-info-circle"></i> These were automatically added to your profile.</div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Box (Matches Dashboard) -->
       

        <form method="POST" action="" enctype="multipart/form-data" id="profileForm">

            <!-- Personal Information Section -->
            <div class="pr-section" id="sec-personal">
                <div class="pr-section-head">
                    <div class="pr-section-head-left">
                        <div class="pr-section-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="pr-section-title">Personal Information</div>
                            <div class="pr-section-sub">Your contact details and public identity</div>
                        </div>
                    </div>
                </div>
                <div class="pr-section-body">
                    <div class="pr-grid-2">
                        <div class="pr-field">
                            <label class="pr-label">First name</label>
                            <input type="text" name="first_name" class="pr-input" value="<?php echo htmlspecialchars($applicant['first_name'] ?? ''); ?>" placeholder="First name">
                        </div>
                        <div class="pr-field">
                            <label class="pr-label">Last name</label>
                            <input type="text" name="last_name" class="pr-input" value="<?php echo htmlspecialchars($applicant['last_name'] ?? ''); ?>" placeholder="Last name">
                        </div>
                    </div>
                    <div class="pr-field">
                        <label class="pr-label">Email address</label>
                        <input type="email" name="email" class="pr-input" value="<?php echo htmlspecialchars($applicant['email'] ?? ''); ?>" placeholder="you@example.com">
                    </div>
                    <div class="pr-field">
                        <label class="pr-label">Phone number</label>
                        <input type="text" name="phone" class="pr-input" value="<?php echo htmlspecialchars($applicant['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000">
                    </div>
                </div>
            </div>

            <!-- Resume Section -->
            <div class="pr-section" id="sec-resume">
                <div class="pr-section-head">
                    <div class="pr-section-head-left">
                        <div class="pr-section-icon"><i class="fas fa-file-alt"></i></div>
                        <div>
                            <div class="pr-section-title">Resume</div>
                            <div class="pr-section-sub">PDF, DOC, DOCX — max 5 MB</div>
                        </div>
                    </div>
                </div>
                <div class="pr-section-body">
                    <?php if (!empty($applicant['resume_file'])): ?>
                        <?php
                            $res_ext = strtolower(pathinfo($applicant['resume_file'], PATHINFO_EXTENSION));
                            $res_icon = in_array($res_ext, ['doc','docx']) ? 'fas fa-file-word' : 'fas fa-file-pdf';
                            $res_color = in_array($res_ext, ['doc','docx']) ? '#2b5797' : '#e74c3c';
                        ?>
                        <div class="pr-resume-box">
                            <div class="pr-resume-file-info">
                                <i class="<?php echo $res_icon; ?> pr-resume-file-icon" style="color:<?php echo $res_color; ?>;"></i>
                                <div>
                                    <div class="pr-resume-file-name">Current resume</div>
                                    <div class="pr-resume-file-meta"><?php echo strtoupper($res_ext); ?> · Uploaded</div>
                                </div>
                            </div>
                            <div class="pr-resume-actions">
                                <a href="../<?php echo htmlspecialchars($applicant['resume_file']); ?>" target="_blank" class="pr-btn-xs pr-btn-view"><i class="fas fa-eye"></i> View</a>
                                <a href="../<?php echo htmlspecialchars($applicant['resume_file']); ?>" download class="pr-btn-xs pr-btn-dl"><i class="fas fa-download"></i> Save</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <label class="pr-file-zone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span><?php echo !empty($applicant['resume_file']) ? 'Upload new resume to replace current' : 'Click to upload your resume'; ?></span>
                        <input type="file" name="resume" accept=".pdf,.doc,.docx">
                    </label>
                    <p class="pr-hint"><i class="fas fa-sparkles" style="color:#1866a3;"></i> Skills are automatically extracted from PDF resumes.</p>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="pr-section" id="sec-skills">
                <div class="pr-section-head">
                    <div class="pr-section-head-left">
                        <div class="pr-section-icon"><i class="fas fa-tools"></i></div>
                        <div>
                            <div class="pr-section-title">Skills</div>
                            <div class="pr-section-sub">Comma-separated — extracted skills shown in blue</div>
                        </div>
                    </div>
                    <?php if (!empty($current_skills)): ?>
                        <span style="font-size:.76rem;font-weight:600;color:#888;"><?php echo count($current_skills); ?> total</span>
                    <?php endif; ?>
                </div>
                <div class="pr-section-body">
                    <?php if (!empty($current_skills)): ?>
                        <div>
                            <label class="pr-label" style="margin-bottom:.5rem;display:block;">Your current skills</label>
                            <div class="pr-skills-display">
                                <?php foreach ($current_skills as $sk): ?>
                                    <?php if (trim($sk) !== ''): ?>
                                        <?php $is_e = in_array($sk, $extracted_skills_from_registration); ?>
                                        <span class="pr-skill <?php echo $is_e ? 'auto' : 'manual'; ?>">
                                            <?php if ($is_e): ?><i class="fas fa-magic" style="font-size:.65rem;"></i><?php endif; ?>
                                            <?php echo htmlspecialchars(trim($sk)); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <hr class="pr-divider">
                    <?php endif; ?>
                    <div class="pr-field">
                        <label class="pr-label">Add or edit skills</label>
                        <textarea name="skills" class="pr-textarea" placeholder="e.g. Python, Project Management, Communication, Excel, Leadership"><?php echo htmlspecialchars($applicant['skills'] ?? ''); ?></textarea>
                        <span class="pr-hint"><i class="fas fa-info-circle"></i> Separate each skill with a comma.</span>
                    </div>
                </div>
            </div>

            <!-- Qualifications Section -->
            <div class="pr-section" id="sec-qualifications">
                <div class="pr-section-head">
                    <div class="pr-section-head-left">
                        <div class="pr-section-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <div class="pr-section-title">Qualifications &amp; Experience</div>
                            <div class="pr-section-sub">Education, credentials, and background</div>
                        </div>
                    </div>
                </div>
                <div class="pr-section-body">

                    <?php if (!empty($selected_qualification_name)): ?>
                        <div class="pr-qual-status pr-qual-found">
                            <span><i class="fas fa-check-circle" style="margin-right:.4rem;"></i><strong>Current:</strong> <?php echo htmlspecialchars($selected_qualification_name); ?></span>
                            <span class="pr-qual-badge <?php echo $is_other_qualification ? 'custom' : ''; ?>">
                                <?php echo $is_other_qualification ? 'Custom' : 'Verified'; ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="pr-qual-status pr-qual-missing">
                            <span><i class="fas fa-exclamation-triangle" style="margin-right:.4rem;"></i> No qualification selected yet.</span>
                        </div>
                    <?php endif; ?>

                    <div class="pr-field">
                        <label class="pr-label">Select qualification</label>
                        <select name="qualification_select" id="qualSelect" class="pr-select">
                            <option value="">— Select a qualification —</option>
                            <?php foreach ($qualifications_list as $q): ?>
                                <option value="<?php echo htmlspecialchars($q['qualification_id']); ?>"
                                    <?php echo (!$is_other_qualification && $selected_qualification_id == $q['qualification_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($q['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other" <?php echo $is_other_qualification ? 'selected' : ''; ?>>Other — specify below</option>
                        </select>
                    </div>

                    <div id="otherWrap" style="display:<?php echo $is_other_qualification ? 'block' : 'none'; ?>;">
                        <div class="pr-field">
                            <label class="pr-label">Please specify</label>
                            <input type="text" name="qualification_other" id="qualOther" class="pr-input"
                                value="<?php echo $is_other_qualification ? htmlspecialchars($selected_qualification_name) : ''; ?>"
                                placeholder="e.g. Vocational Certificate, Diploma in Nursing">
                            <span class="pr-hint"><i class="fas fa-info-circle"></i> Type exactly as it appears on your certificate.</span>
                        </div>
                    </div>

                    <div class="pr-field">
                        <label class="pr-label">Or enter qualifications manually</label>
                        <textarea name="qualifications" class="pr-textarea" placeholder="Describe your qualifications, certifications, or educational background…"><?php echo htmlspecialchars($applicant['qualifications'] ?? ''); ?></textarea>
                    </div>

                    <div class="pr-grid-2">
                        <div class="pr-field">
                            <label class="pr-label">Years of experience</label>
                            <input type="number" name="experience_years" class="pr-input" value="<?php echo $applicant['experience_years'] ?? 0; ?>" min="0" placeholder="0">
                        </div>
                        <div class="pr-field">
                            <label class="pr-label">Education level</label>
                            <select name="education_level" class="pr-select">
                                <option value="">Select level</option>
                                <?php foreach (['High School','Associate','Bachelor','Master','PhD'] as $lvl): ?>
                                    <option value="<?php echo $lvl; ?>" <?php echo ($applicant['education_level'] === $lvl) ? 'selected' : ''; ?>><?php echo $lvl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Submit Bar -->
            <div class="pr-submit-bar">
                <span class="pr-submit-hint"><i class="fas fa-lock" style="margin-right:.3rem;"></i> Your data is saved securely</span>
                <button type="submit" class="pr-btn-save">
                    <i class="fas fa-save"></i> Save changes
                </button>
            </div>

        </form>

        <!-- Employer Feedback Section -->
        <?php if (count($all_feedback) > 0): ?>
            <div class="pr-section" id="sec-feedback">
                <div class="pr-section-head">
                    <div class="pr-section-head-left">
                        <div class="pr-section-icon green"><i class="fas fa-comments"></i></div>
                        <div>
                            <div class="pr-section-title">Employer Feedback</div>
                            <div class="pr-section-sub"><?php echo count($all_feedback); ?> record<?php echo count($all_feedback) !== 1 ? 's' : ''; ?></div>
                        </div>
                    </div>
                </div>
                <div class="pr-section-body">
                    <?php foreach ($all_feedback as $fb): ?>
                        <div class="pr-feedback-card">
                            <div class="pr-feedback-header">
                                <div>
                                    <div class="pr-feedback-job"><?php echo htmlspecialchars($fb['job_title']); ?></div>
                                    <div class="pr-feedback-company"><i class="fas fa-building" style="margin-right:.3rem;"></i><?php echo htmlspecialchars($fb['company_name']); ?></div>
                                </div>
                                <div class="pr-score-pill">
                                    <div class="pr-status-badge"><?php echo ucfirst($fb['application_status']); ?></div>
                                    <div class="pr-score-big"><?php echo number_format($fb['employability_score'], 1); ?><span style="font-size:.8rem;font-weight:400;">%</span></div>
                                    <div class="pr-score-lbl">Match score</div>
                                </div>
                            </div>
                            <div class="pr-feedback-body"><?php echo htmlspecialchars($fb['feedback_message']); ?></div>
                            <div class="pr-feedback-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($fb['employer_first'] . ' ' . $fb['employer_last']); ?></span>
                                <span><?php echo date('M j, Y', strtotime($fb['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Accepted Offers Section -->
        <?php if (count($accepted_jobs) > 0): ?>
            <div class="pr-section" id="sec-accepted">
                <div class="pr-section-head">
                    <div class="pr-section-head-left">
                        <div class="pr-section-icon green"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="pr-section-title">Accepted Offers</div>
                            <div class="pr-section-sub"><?php echo count($accepted_jobs); ?> offer<?php echo count($accepted_jobs) !== 1 ? 's' : ''; ?></div>
                        </div>
                    </div>
                </div>
                <div class="pr-section-body">
                    <?php foreach ($accepted_jobs as $job): ?>
                        <div class="pr-job-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <div class="pr-job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <?php if (!empty($job['company_name'])): ?>
                                        <div class="pr-job-co"><i class="fas fa-building" style="margin-right:.3rem;"></i><?php echo htmlspecialchars($job['company_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="pr-accepted-badge"><i class="fas fa-check-circle"></i> Accepted</span>
                            </div>
                            <div class="pr-job-chips">
                                <?php if (!empty($job['location'])): ?>
                                    <span class="pr-chip"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['employment_type'])): ?>
                                    <span class="pr-chip"><i class="fas fa-briefcase"></i> <?php echo ucfirst(htmlspecialchars($job['employment_type'])); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($job['salary_range'])): ?>
                                    <span class="pr-chip salary"><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($job['description'])): ?>
                                <div class="pr-job-desc"><?php echo htmlspecialchars(substr($job['description'], 0, 160)); ?><?php echo strlen($job['description']) > 160 ? '…' : ''; ?></div>
                            <?php endif; ?>
                            <div class="pr-job-footer">
                                <span>Applied <?php echo date('M j, Y', strtotime($job['applied_at'])); ?></span>
                                <span class="pr-job-date-accepted"><i class="fas fa-check-circle" style="margin-right:.3rem;"></i>Accepted <?php echo date('M j, Y', strtotime($job['updated_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ══ COLUMN 3: RIGHT SIDEBAR (Profile Preview) ══ -->
    <div class="pr-right">
        <div class="right-col-header">
            <h3><i class="fas fa-eye"></i> Profile Preview</h3>
            <p>How employers see you</p>
        </div>
        <div class="right-content">
            <div class="preview-card">
                <div class="preview-title"><i class="fas fa-user"></i> <?php echo htmlspecialchars($full_name); ?></div>
                <div class="preview-item"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($applicant['email'] ?? 'Not set'); ?></div>
                <div class="preview-item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($applicant['phone'] ?? 'Not set'); ?></div>
                <div class="preview-item"><i class="fas fa-tools"></i> <?php echo count($current_skills); ?> skills</div>
                <div class="preview-item"><i class="fas fa-briefcase"></i> <?php echo $applicant['experience_years'] ?? 0; ?> years experience</div>
                <div class="preview-item"><i class="fas fa-graduation-cap"></i> <?php echo !empty($selected_qualification_name) ? htmlspecialchars($selected_qualification_name) : 'Not specified'; ?></div>
                <?php if (!empty($applicant['resume_file'])): ?>
                    <div class="preview-item"><i class="fas fa-file-alt"></i> Resume uploaded ✓</div>
                <?php else: ?>
                    <div class="preview-item"><i class="fas fa-file-alt"></i> No resume yet</div>
                <?php endif; ?>
            </div>
            <div style="margin-top: 1rem; text-align: center;">
                <a href="dashboard.php" style="color: #1866a3; font-size: 0.75rem; text-decoration: none;">← Back to Dashboard</a>
            </div>
        </div>
    </div>

</div><!-- /pr-shell -->
</div><!-- /pr-page -->

<!-- Floating Chat Button (EXACT MATCH) -->
<div class="pr-float">
    <a href="chat.php" class="pr-float-btn" title="Chat with Employers">
        <i class="fas fa-comments"></i>
        <?php if ($unread_count > 0): ?>
            <span class="pr-float-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
        <span class="pr-float-tip">
            <?php echo $unread_count > 0 ? "You have {$unread_count} unread message(s)" : "Chat with Employers"; ?>
        </span>
    </a>
</div>

<script>
// Photo preview & auto-submit - FIXED VERSION
function handlePhotoChange(input) {
    if (!input.files || !input.files[0]) return;
    
    // Validate file type
    var file = input.files[0];
    var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    if (!validTypes.includes(file.type)) {
        alert('Please upload a valid image file (JPG, PNG, GIF, WEBP, or BMP)');
        input.value = '';
        return;
    }
    
    // Validate file size (5MB max)
    if (file.size > 5242880) {
        alert('File size must be less than 5MB');
        input.value = '';
        return;
    }
    
    // Preview the image
    var reader = new FileReader();
    reader.onload = function(e) {
        var avatarDiv = document.querySelector('.pr-avatar');
        if (avatarDiv) {
            avatarDiv.innerHTML = '<img src="' + e.target.result + '" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><div class="pr-avatar-overlay"><i class="fas fa-camera"></i></div>';
        }
    };
    reader.readAsDataURL(file);
    
    // Create a FormData object and submit via AJAX
    var formData = new FormData();
    formData.append('profile_photo', file);
    
    // Also include current form data to preserve other fields
    var form = document.getElementById('profileForm');
    var formElements = form.elements;
    for (var i = 0; i < formElements.length; i++) {
        var el = formElements[i];
        if (el.name && el.name !== 'profile_photo' && el.type !== 'file') {
            formData.append(el.name, el.value);
        }
    }
    
    // Show loading indicator
    var saveBtn = document.querySelector('.pr-btn-save');
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    saveBtn.disabled = true;
    
    // Submit via AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(html) {
        // Reload the page to show updated photo
        window.location.reload();
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('Failed to upload photo. Please try again.');
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}
// Qualification "Other" toggle
(function() {
    var sel = document.getElementById('qualSelect');
    var wrap = document.getElementById('otherWrap');
    var inp = document.getElementById('qualOther');
    if (!sel || !wrap || !inp) return;
    sel.addEventListener('change', function() {
        if (this.value === 'other') { wrap.style.display = 'block'; inp.focus(); }
        else { wrap.style.display = 'none'; inp.value = ''; }
    });
})();

// Active nav link on scroll (Matches Dashboard)
(function() {
    var sections = document.querySelectorAll('[id^="sec-"]');
    var links = document.querySelectorAll('.pr-sidenav a');
    if (!sections.length || !links.length) return;
    function onScroll() {
        var y = window.scrollY + 140;
        var cur = '';
        sections.forEach(function(s) { if (s.offsetTop <= y) cur = s.id; });
        links.forEach(function(a) {
            a.classList.toggle('active', a.getAttribute('href') === '#' + cur);
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

// Refresh unread count (Matches Dashboard)
(function() {
    function refresh() {
        fetch('../includes/handlers/message_handler.php?action=get_unread_count')
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.success) return;
                var badge = document.querySelector('.pr-float-badge');
                var tip = document.querySelector('.pr-float-tip');
                var btn = document.querySelector('.pr-float-btn');
                if (d.count > 0) {
                    if (badge) badge.textContent = d.count > 9 ? '9+' : d.count;
                    else {
                        var b = document.createElement('span');
                        b.className = 'pr-float-badge';
                        b.textContent = d.count > 9 ? '9+' : d.count;
                        btn.appendChild(b);
                    }
                    if (tip) tip.textContent = 'You have ' + d.count + ' unread message(s)';
                } else {
                    if (badge) badge.remove();
                    if (tip) tip.textContent = 'Chat with Employers';
                }
            }).catch(function(){});
    }
    if (document.querySelector('.pr-float-btn')) setInterval(refresh, 30000);
})();

// Smooth scroll for anchor links
document.querySelectorAll('.pr-sidenav a').forEach(function(anchor) {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        var targetId = this.getAttribute('href');
        if (targetId && targetId !== '#') {
            var target = document.querySelector(targetId);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>