<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Post New Job";
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Get active qualifications
$qualifications = [];
$qual_result = $conn->query("SELECT * FROM qualifications WHERE status = 'active' ORDER BY name ASC");
if ($qual_result) {
    $qualifications = $qual_result->fetch_all(MYSQLI_ASSOC);
}

// Initialize form data
$form_data = [
    'title' => '',
    'description' => '',
    'requirements' => '',
    'skills_required' => '',
    'location' => '',
    'employment_type' => 'full-time',
    'salary_range' => '',
    'status' => 'active',
    'target_qualifications' => [],
    'custom_qualifications' => []
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $skills_required = trim($_POST['skills_required'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $employment_type = $_POST['employment_type'] ?? 'full-time';
    $salary_range = trim($_POST['salary_range'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $target_qualifications = $_POST['target_qualifications'] ?? [];

    // Custom qualifications typed manually
    $custom_qualifications_raw = trim($_POST['custom_qualifications'] ?? '');
    $custom_qualifications = [];
    if (!empty($custom_qualifications_raw)) {
        foreach (explode(',', $custom_qualifications_raw) as $cq) {
            $cq = trim($cq);
            if ($cq !== '') $custom_qualifications[] = $cq;
        }
    }

    if (empty($title) || empty($description)) {
        $error = "Please fill in all required fields (Title and Description are required)";
        $form_data = compact('title', 'description', 'requirements', 'skills_required', 'location', 'employment_type', 'salary_range', 'status', 'target_qualifications', 'custom_qualifications');
    } else {
        $stmt = $conn->prepare("INSERT INTO job_postings (employer_id, title, description, requirements, skills_required, location, employment_type, salary_range, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssss", $employer_id, $title, $description, $requirements, $skills_required, $location, $employment_type, $salary_range, $status);

        if ($stmt->execute()) {
            $job_id = $conn->insert_id;
            $success = "Job posted successfully!";

            // Save checkbox qualification mappings
            if (!empty($target_qualifications)) {
                foreach ($target_qualifications as $qual_id) {
                    $qual_id = intval($qual_id);
                    if ($qual_id > 0) {
                        $map_stmt = $conn->prepare("INSERT INTO job_qualification_mapping (job_id, qualification_id) VALUES (?, ?)");
                        $map_stmt->bind_param("ii", $job_id, $qual_id);
                        $map_stmt->execute();
                        $map_stmt->close();
                    }
                }
            }

            // Save custom (manually typed) qualifications:
            // Insert into qualifications table if not already there, then map
            if (!empty($custom_qualifications)) {
                foreach ($custom_qualifications as $cq_name) {
                    // Check if qualification already exists (case-insensitive)
                    $check = $conn->prepare("SELECT qualification_id FROM qualifications WHERE LOWER(name) = LOWER(?) LIMIT 1");
                    $check->bind_param("s", $cq_name);
                    $check->execute();
                    $check_result = $check->get_result()->fetch_assoc();
                    $check->close();

                    if ($check_result) {
                        $cq_id = $check_result['qualification_id'];
                    } else {
                        // Insert as new qualification
                        $ins = $conn->prepare("INSERT INTO qualifications (name, status) VALUES (?, 'active')");
                        $ins->bind_param("s", $cq_name);
                        $ins->execute();
                        $cq_id = $conn->insert_id;
                        $ins->close();
                    }

                    // Map to job
                    $map_stmt = $conn->prepare("INSERT IGNORE INTO job_qualification_mapping (job_id, qualification_id) VALUES (?, ?)");
                    $map_stmt->bind_param("ii", $job_id, $cq_id);
                    $map_stmt->execute();
                    $map_stmt->close();
                }
            }

            // Reset form
            $form_data = [
                'title' => '', 'description' => '', 'requirements' => '',
                'skills_required' => '', 'location' => '', 'employment_type' => 'full-time',
                'salary_range' => '', 'status' => 'active',
                'target_qualifications' => [], 'custom_qualifications' => []
            ];
        } else {
            $error = "Failed to post job";
            $form_data = compact('title', 'description', 'requirements', 'skills_required', 'location', 'employment_type', 'salary_range', 'status', 'target_qualifications', 'custom_qualifications');
        }
        $stmt->close();
    }
}

// Get unread message count
$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $unread_count = $row['unread_count'];
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
}

$conn->close();
include '../includes/header.php';
?>

<!-- Full Width Header -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-plus-circle"></i> Post New Job
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                System Overview &amp; Management
            </p>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">

        <?php if (isset($success)): ?>
            <div style="background:#d4edda;color:#155724;padding:1rem;border-radius:6px;margin-bottom:1.5rem;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:1rem;border-radius:6px;margin-bottom:1.5rem;border:1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="jobPostForm">
            <div style="display:grid;gap:1rem;">

                <!-- Job Title -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Job Title <span style="color:#dc3545;">*</span></label>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($form_data['title']); ?>"
                           placeholder="e.g., Senior Web Developer"
                           style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;">
                </div>

                <!-- Job Description -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Job Description <span style="color:#dc3545;">*</span></label>
                    <textarea name="description" rows="4" required
                              placeholder="Provide a detailed description of the job position..."
                              style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;resize:vertical;"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                </div>

                <!-- Requirements -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Requirements <span style="color:#6c757d;font-weight:normal;">(Optional)</span></label>
                    <textarea name="requirements" rows="3"
                              placeholder="List the requirements for this position..."
                              style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;resize:vertical;"><?php echo htmlspecialchars($form_data['requirements']); ?></textarea>
                </div>

                <!-- Required Skills -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Required Skills <span style="color:#6c757d;font-weight:normal;">(Optional)</span></label>
                    <input type="text" name="skills_required" value="<?php echo htmlspecialchars($form_data['skills_required']); ?>"
                           placeholder="e.g., PHP, JavaScript, MySQL, React"
                           style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;">
                    <small style="color:#6c757d;font-size:.75rem;display:block;margin-top:.25rem;">Separate skills with commas</small>
                </div>

                <!-- Location + Employment Type -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
                    <div>
                        <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Location <span style="color:#6c757d;font-weight:normal;">(Optional)</span></label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($form_data['location']); ?>"
                               placeholder="e.g., New York, NY"
                               style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;">
                    </div>
                    <div>
                        <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Employment Type</label>
                        <select name="employment_type" style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;">
                            <option value="full-time"  <?php echo $form_data['employment_type']==='full-time'  ?'selected':''; ?>>Full-time</option>
                            <option value="part-time"  <?php echo $form_data['employment_type']==='part-time'  ?'selected':''; ?>>Part-time</option>
                            <option value="contract"   <?php echo $form_data['employment_type']==='contract'   ?'selected':''; ?>>Contract</option>
                            <option value="internship" <?php echo $form_data['employment_type']==='internship' ?'selected':''; ?>>Internship</option>
                        </select>
                    </div>
                </div>

                <!-- Salary Range -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Salary Range <span style="color:#6c757d;font-weight:normal;">(Optional)</span></label>
                    <input type="text" name="salary_range" value="<?php echo htmlspecialchars($form_data['salary_range']); ?>"
                           placeholder="e.g., 50,000 - 70,000"
                           style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;">
                </div>

                <!-- ═══ TARGET QUALIFICATIONS ═══ -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">
                        Target Qualifications <span style="color:#6c757d;font-weight:normal;">(Optional)</span>
                    </label>

                    <div style="background:#f8f9fa;padding:1rem;border-radius:8px;border:1px solid #e0e0e0;">

                        <!-- ── Custom input row ── -->
                        <div style="display:flex;gap:.5rem;margin-bottom:.85rem;align-items:center;">
                            <input type="text" id="customQualInput"
                                   placeholder="Type a qualification and press Add…"
                                   style="flex:1;padding:.5rem .75rem;border:2px solid #d0d0d0;border-radius:6px;
                                          font-size:.85rem;outline:none;transition:border-color .2s;"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addCustomQual();}">
                            <button type="button" onclick="addCustomQual()"
                                    style="background:#1866a3;color:#fff;border:none;border-radius:6px;
                                           padding:.5rem 1rem;font-size:.83rem;font-weight:600;
                                           cursor:pointer;white-space:nowrap;transition:background .2s;"
                                    onmouseover="this.style.background='#145591'"
                                    onmouseout="this.style.background='#1866a3'">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>

                        <!-- ── Custom tags display ── -->
                        <div id="customTagsContainer" style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.75rem;min-height:0;">
                            <?php foreach ($form_data['custom_qualifications'] as $cq): ?>
                                <span class="qual-tag" data-value="<?php echo htmlspecialchars($cq); ?>"
                                      style="display:inline-flex;align-items:center;gap:.35rem;
                                             background:#e8f0fe;color:#1a5296;border:1px solid #b3caf7;
                                             border-radius:20px;padding:.28rem .75rem;font-size:.8rem;font-weight:500;">
                                    <?php echo htmlspecialchars($cq); ?>
                                    <button type="button" onclick="removeCustomTag(this)"
                                            style="background:none;border:none;color:#1a5296;cursor:pointer;
                                                   font-size:.9rem;padding:0;line-height:1;" title="Remove">×</button>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <!-- Hidden field carries custom values to PHP -->
                        <input type="hidden" name="custom_qualifications" id="customQualHidden"
                               value="<?php echo htmlspecialchars(implode(',', $form_data['custom_qualifications'])); ?>">

                        <!-- ── Divider when there are DB qualifications ── -->
                        <?php if (count($qualifications) > 0): ?>
                            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem;">
                                <div style="flex:1;height:1px;background:#dde3ea;"></div>
                                <span style="font-size:.75rem;color:#888;white-space:nowrap;">or choose from list</span>
                                <div style="flex:1;height:1px;background:#dde3ea;"></div>
                            </div>
                        <?php endif; ?>

                        <!-- ── Checkbox list ── -->
                        <?php if (count($qualifications) > 0): ?>
                            <div style="display:grid;gap:.4rem;max-height:160px;overflow-y:auto;">
                                <?php foreach ($qualifications as $qual): ?>
                                    <div style="display:flex;align-items:center;gap:.5rem;padding:.45rem .6rem;
                                                background:white;border-radius:5px;border:1px solid #e0e0e0;">
                                        <input type="checkbox" name="target_qualifications[]"
                                               value="<?php echo $qual['qualification_id']; ?>"
                                               id="qual_<?php echo $qual['qualification_id']; ?>"
                                               <?php echo in_array($qual['qualification_id'], $form_data['target_qualifications']) ? 'checked' : ''; ?>>
                                        <label for="qual_<?php echo $qual['qualification_id']; ?>"
                                               style="margin:0;cursor:pointer;font-size:.85rem;color:#333;">
                                            <?php echo htmlspecialchars($qual['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align:center;padding:.5rem;color:#888;font-size:.83rem;margin:0;">
                                <i class="fas fa-inbox"></i> No preset qualifications — use the field above to add your own.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- ═══ END QUALIFICATIONS ═══ -->

                <!-- Status -->
                <div>
                    <label style="display:block;color:#333;font-weight:600;margin-bottom:.4rem;font-size:.9rem;">Status</label>
                    <select name="status" style="width:100%;padding:.6rem;border:2px solid #e0e0e0;border-radius:6px;font-size:.9rem;">
                        <option value="active" <?php echo $form_data['status']==='active' ?'selected':''; ?>>Active</option>
                        <option value="draft"  <?php echo $form_data['status']==='draft'  ?'selected':''; ?>>Draft</option>
                    </select>
                </div>

                <!-- Submit -->
                <div style="display:flex;gap:.8rem;justify-content:flex-end;margin-top:.5rem;">
                    <button type="button" onclick="resetForm()"
                            style="background:#6c757d;color:white;padding:.6rem 1.2rem;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:.85rem;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="submit"
                            style="background:#1866a3;color:white;padding:.6rem 1.5rem;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:.9rem;">
                        <i class="fas fa-paper-plane"></i> Post Job
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Floating Message Icon -->
<style>
.floating-message-container{position:fixed;bottom:25px;right:25px;z-index:10000;transition:all .3s ease}
.floating-message-btn{width:65px;height:65px;border-radius:50%;background:linear-gradient(135deg,#0056b3,#004494);color:white;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,86,179,.4);cursor:pointer;transition:all .3s ease;text-decoration:none;border:none;font-size:1.4rem;position:relative}
.floating-message-btn:hover{transform:translateY(-3px) scale(1.05);box-shadow:0 8px 25px rgba(0,86,179,.5);color:white;text-decoration:none}
.message-badge{position:absolute;top:-3px;right:-3px;background:#F44336;color:white;border-radius:50%;width:24px;height:24px;font-size:.75rem;display:flex;align-items:center;justify-content:center;font-weight:bold;border:3px solid white;animation:pulse 2s infinite}
@keyframes pulse{0%{transform:scale(1);box-shadow:0 0 0 0 rgba(244,67,54,.7)}50%{transform:scale(1.05);box-shadow:0 0 0 10px rgba(244,67,54,0)}100%{transform:scale(1);box-shadow:0 0 0 0 rgba(244,67,54,0)}}
.message-tooltip{position:absolute;right:75px;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.8);color:white;padding:8px 12px;border-radius:6px;font-size:.8rem;white-space:nowrap;opacity:0;visibility:hidden;transition:all .3s ease;pointer-events:none}
.message-tooltip::after{content:'';position:absolute;top:50%;left:100%;transform:translateY(-50%);border-width:6px;border-style:solid;border-color:transparent transparent transparent rgba(0,0,0,.8)}
.floating-message-btn:hover .message-tooltip{opacity:1;visibility:visible;right:80px}
@media(max-width:768px){.floating-message-container{bottom:20px;right:20px}.floating-message-btn{width:60px;height:60px;font-size:1.3rem}.message-tooltip{display:none}}
@media print{.floating-message-container{display:none!important}}
</style>

<div class="floating-message-container">
    <a href="chat.php" class="floating-message-btn" title="Chat">
        <i class="fas fa-comments"></i>
        <?php if ($unread_count > 0): ?>
            <span class="message-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
        <span class="message-tooltip">
            <?php echo $unread_count > 0 ? "You have $unread_count unread message(s)" : "Go to Chat"; ?>
        </span>
    </a>
</div>

<script>
// ── Custom qualifications tag manager ──────────────────────────────────────

function addCustomQual() {
    const input     = document.getElementById('customQualInput');
    const val       = input.value.trim();
    if (!val) { input.focus(); return; }

    // Prevent duplicates (case-insensitive)
    const existing = Array.from(document.querySelectorAll('.qual-tag'))
                          .map(t => t.dataset.value.toLowerCase());
    if (existing.includes(val.toLowerCase())) {
        input.style.borderColor = '#dc3545';
        input.title = 'Already added';
        setTimeout(() => { input.style.borderColor = '#d0d0d0'; input.title = ''; }, 1500);
        return;
    }

    // Create tag
    const tag = document.createElement('span');
    tag.className   = 'qual-tag';
    tag.dataset.value = val;
    tag.style.cssText = 'display:inline-flex;align-items:center;gap:.35rem;background:#e8f0fe;color:#1a5296;border:1px solid #b3caf7;border-radius:20px;padding:.28rem .75rem;font-size:.8rem;font-weight:500;';
    tag.innerHTML   = `${escHtml(val)}<button type="button" onclick="removeCustomTag(this)"
                        style="background:none;border:none;color:#1a5296;cursor:pointer;font-size:.9rem;padding:0;line-height:1;" title="Remove">×</button>`;
    document.getElementById('customTagsContainer').appendChild(tag);

    input.value = '';
    input.focus();
    syncHidden();
}

function removeCustomTag(btn) {
    btn.closest('.qual-tag').remove();
    syncHidden();
}

function syncHidden() {
    const vals = Array.from(document.querySelectorAll('.qual-tag')).map(t => t.dataset.value);
    document.getElementById('customQualHidden').value = vals.join(',');
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Focus ring on custom input
document.getElementById('customQualInput')?.addEventListener('focus', function() {
    this.style.borderColor = '#1866a3';
});
document.getElementById('customQualInput')?.addEventListener('blur', function() {
    this.style.borderColor = '#d0d0d0';
});

// ── Form reset ──────────────────────────────────────────────────────────────
function resetForm() {
    if (confirm('Are you sure you want to reset the form?')) {
        document.getElementById('jobPostForm').reset();
        document.getElementById('customTagsContainer').innerHTML = '';
        document.getElementById('customQualHidden').value = '';
        document.getElementById('customQualInput').value = '';
    }
}

// ── Floating message badge auto-update ────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    function updateMessageBadge(count) {
        const badge   = document.querySelector('.message-badge');
        const tooltip = document.querySelector('.message-tooltip');
        const btn     = document.querySelector('.floating-message-btn');
        if (count > 0) {
            if (badge) { badge.textContent = count > 9 ? '9+' : count; }
            else {
                const nb = document.createElement('span');
                nb.className   = 'message-badge';
                nb.textContent = count > 9 ? '9+' : count;
                btn.appendChild(nb);
            }
            if (tooltip) tooltip.textContent = `You have ${count} unread message(s)`;
        } else {
            if (badge)   badge.remove();
            if (tooltip) tooltip.textContent = 'Go to Chat';
        }
    }

    if (document.querySelector('.floating-message-btn')) {
        setInterval(() => {
            fetch('../includes/handlers/message_handler.php?action=get_unread_count')
                .then(r => r.json())
                .then(d => { if (d.success) updateMessageBadge(d.count); })
                .catch(() => {});
        }, 30000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>