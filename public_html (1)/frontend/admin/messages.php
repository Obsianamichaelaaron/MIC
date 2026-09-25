<?php
/**
 * admin/messages.php — Fixed version
 * Adds try/catch around DB queries so the page loads even if
 * contact_inquiries table doesn't exist yet.
 */

require_once '../includes/config/session.php';
require_once '../includes/config/database.php';

requireRole('admin');

$pageTitle = 'Contact Messages';

// Safe unread count — won't crash if table doesn't exist yet
$unreadCount = 0;
try {
    $conn = getDBConnection();
    $unreadResult = $conn->query("SELECT COUNT(*) AS c FROM contact_inquiries WHERE is_read = 0");
    $unreadCount  = $unreadResult ? (int)$unreadResult->fetch_assoc()['c'] : 0;
    $conn->close();
} catch (Exception $e) {
    // Table not created yet — page still loads
}

include '../includes/header.php';
?>
<style>
.msg-container { max-width:1400px; margin:0 auto; padding:1.5rem 1rem 3rem; }
.msg-page-header { background:url('../images/bg.jpg') center/cover no-repeat; padding:1.5rem 0; margin:0 -1rem 1.5rem; text-align:center; color:white; }
.msg-page-header h1 { margin:0; font-size:clamp(1.3rem,4vw,1.5rem); font-weight:700; }
.msg-page-header p  { margin:.3rem 0 0; opacity:.85; font-size:.9rem; }
.filter-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.filter-tab { padding:.4rem .9rem; border:1px solid #e0e0e0; border-radius:20px; background:white; font-size:.8rem; font-weight:600; cursor:pointer; transition:all .2s; color:#555; }
.filter-tab.active, .filter-tab:hover { background:linear-gradient(135deg,#0056b3 0%,#004494 100%); color:white; border-color:#004494; }
.msg-layout { display:grid; grid-template-columns:360px 1fr; gap:1.5rem; align-items:start; }
@media(max-width:900px){ .msg-layout{ grid-template-columns:1fr; } }
.inquiry-list { background:white; border:1px solid #e0e0e0; border-radius:12px; overflow:hidden; }
.inquiry-list-header { display:flex; align-items:center; justify-content:space-between; padding:.875rem 1.25rem; background:#f8f9fa; border-bottom:1px solid #e0e0e0; font-weight:600; font-size:.9rem; color:#0056b3; }
.inquiry-item { padding:.875rem 1.25rem; border-bottom:1px solid #f0f0f0; cursor:pointer; transition:background .15s; position:relative; }
.inquiry-item:last-child { border-bottom:none; }
.inquiry-item:hover { background:#f8f9fc; }
.inquiry-item.active { background:#eff4ff; border-left:3px solid #0056b3; }
.inquiry-item.unread { background:#fafcff; }
.unread-dot { width:8px; height:8px; border-radius:50%; background:#0056b3; display:inline-block; margin-right:6px; }
.inquiry-name { font-weight:600; font-size:.875rem; color:#222; }
.inquiry-sub  { font-size:.78rem; color:#555; margin:2px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.inquiry-time { font-size:.72rem; color:#999; }
.inquiry-status-badge { position:absolute; top:.875rem; right:1rem; }
.empty-list { padding:2rem; text-align:center; color:#999; font-size:.875rem; }
.list-pagination { display:flex; justify-content:center; gap:.4rem; padding:.75rem; background:#f8f9fa; border-top:1px solid #e0e0e0; }
.page-btn { width:30px; height:30px; border-radius:6px; border:1px solid #e0e0e0; background:white; cursor:pointer; font-size:.8rem; font-weight:600; color:#555; transition:all .2s; }
.page-btn.active, .page-btn:hover { background:#0056b3; color:white; border-color:#0056b3; }
.conv-panel { background:white; border:1px solid #e0e0e0; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; min-height:500px; }
.conv-empty { flex:1; display:flex; align-items:center; justify-content:center; color:#bbb; font-size:.9rem; flex-direction:column; gap:.5rem; }
.conv-header { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; background:#f8f9fa; border-bottom:1px solid #e0e0e0; flex-wrap:wrap; gap:.5rem; }
.conv-header-info h3 { margin:0; font-size:1rem; color:#222; }
.conv-header-info p  { margin:2px 0 0; font-size:.8rem; color:#666; }
.conv-header-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
.conv-body { flex:1; padding:1.25rem; overflow-y:auto; max-height:420px; display:flex; flex-direction:column; gap:1rem; }
.msg-bubble { max-width:80%; border-radius:12px; padding:.75rem 1rem; font-size:.875rem; line-height:1.6; position:relative; }
.msg-bubble.user  { align-self:flex-start; background:#f4f6f9; color:#333; border-bottom-left-radius:3px; }
.msg-bubble.admin { align-self:flex-end; background:linear-gradient(135deg,#0056b3 0%,#004494 100%); color:white; border-bottom-right-radius:3px; }
.bubble-meta  { font-size:.7rem; opacity:.7; margin-top:4px; text-align:right; }
.bubble-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; opacity:.6; }
.conv-footer { border-top:1px solid #e0e0e0; padding:1rem 1.25rem; background:#fafafa; }
.reply-textarea { width:100%; min-height:90px; border:1px solid #e0e0e0; border-radius:8px; padding:.65rem .875rem; font-family:inherit; font-size:.875rem; resize:vertical; transition:border-color .2s,box-shadow .2s; box-sizing:border-box; }
.reply-textarea:focus { outline:none; border-color:#0056b3; box-shadow:0 0 0 3px rgba(0,86,179,.1); }
.reply-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:.625rem; }
.btn         { padding:.5rem 1rem; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:.85rem; transition:all .3s; display:inline-flex; align-items:center; gap:.5rem; font-family:inherit; }
.btn-primary { background:linear-gradient(135deg,#0056b3 0%,#004494 100%); color:white; }
.btn-primary:hover  { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,86,179,.3); }
.btn-danger  { background:#dc3545; color:white; }
.btn-danger:hover   { background:#c82333; transform:translateY(-1px); }
.btn-outline { background:transparent; border:1px solid #e0e0e0; color:#666; }
.btn-outline:hover  { background:#f8f9fa; border-color:#0056b3; color:#0056b3; }
.btn-sm      { padding:.35rem .75rem; font-size:.75rem; }
.badge         { display:inline-block; padding:.2rem .6rem; border-radius:15px; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.3px; }
.badge-new     { background:#dbeafe; color:#1e40af; }
.badge-open    { background:#fef3c7; color:#92400e; }
.badge-replied { background:#d1fae5; color:#065f46; }
.badge-closed  { background:#f3f4f6; color:#374151; }
.toast-container { position:fixed; top:1.5rem; right:1.5rem; z-index:9999; display:flex; flex-direction:column; gap:.5rem; }
.toast { padding:.75rem 1.25rem; border-radius:8px; font-size:.875rem; font-weight:500; color:white; box-shadow:0 4px 12px rgba(0,0,0,.15); animation:slideIn .3s ease; max-width:320px; }
.toast.success { background:#10b981; }
.toast.error   { background:#ef4444; }
.toast.info    { background:#3b82f6; }
@keyframes slideIn { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }
.spinner { display:inline-block; width:14px; height:14px; border:2px solid rgba(255,255,255,.4); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to{transform:rotate(360deg)} }

/* Setup notice shown when table doesn't exist */
.setup-notice { background:#fff3cd; border:1px solid #ffc107; border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
.setup-notice h3 { margin:0 0 .5rem; color:#856404; }
.setup-notice p  { margin:0 0 1rem; color:#533f03; font-size:.9rem; }
.setup-notice code { background:#ffeeba; padding:2px 6px; border-radius:4px; font-size:.85rem; }
</style>

<div class="msg-page-header">
    <h1><i class="fas fa-envelope-open-text"></i> Contact Messages</h1>
    <p>View, manage and reply to inquiries from the landing page contact form</p>
</div>

<div class="msg-container">

    <!-- Setup notice (shown via JS if table missing) -->
    <div class="setup-notice" id="setupNotice" style="display:none;">
        <h3><i class="fas fa-exclamation-triangle"></i> Database Setup Required</h3>
        <p>The <code>contact_inquiries</code> table doesn't exist yet. Run the migration first:</p>
        <a href="../database/create_contact_inquiries_table.php" class="btn btn-primary" target="_blank">
            <i class="fas fa-database"></i> Run Database Migration
        </a>
        <p style="margin-top:.75rem; margin-bottom:0; font-size:.8rem;">After running it, reload this page.</p>
    </div>

    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">All Messages</button>
        <button class="filter-tab" data-filter="new">New <span id="newBadge" style="display:none;" class="badge badge-new"></span></button>
        <button class="filter-tab" data-filter="unread">Unread</button>
        <button class="filter-tab" data-filter="replied">Replied</button>
    </div>

    <div class="msg-layout">
        <div class="inquiry-list">
            <div class="inquiry-list-header">
                <span><i class="fas fa-inbox"></i> Inquiries</span>
                <button class="btn btn-sm btn-outline" id="refreshBtn" title="Refresh"><i class="fas fa-sync-alt"></i></button>
            </div>
            <div id="inquiryListBody">
                <div class="empty-list"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
            </div>
            <div class="list-pagination" id="paginationBar" style="display:none;"></div>
        </div>

        <div class="conv-panel" id="convPanel">
            <div class="conv-empty" id="convEmpty">
                <i class="fas fa-comments fa-2x" style="opacity:.3;"></i>
                <span>Select an inquiry to view the conversation</span>
            </div>
            <div id="convContent" style="display:none; flex-direction:column; flex:1;"></div>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
let currentFilter = 'all';
let currentPage   = 1;
let currentId     = null;
const API = 'contact_api.php';

function toast(msg, type = 'info', duration = 3500) {
    const tc = document.getElementById('toastContainer');
    const t  = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    tc.appendChild(t);
    setTimeout(() => t.remove(), duration);
}

function relTime(dateStr) {
    const d   = new Date(dateStr.replace(' ', 'T'));
    const sec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (sec < 60)    return 'just now';
    if (sec < 3600)  return `${Math.floor(sec/60)}m ago`;
    if (sec < 86400) return `${Math.floor(sec/3600)}h ago`;
    return d.toLocaleDateString('en-PH', { month:'short', day:'numeric' });
}

function fullDate(dateStr) {
    return new Date(dateStr.replace(' ', 'T')).toLocaleString('en-PH', { dateStyle:'medium', timeStyle:'short' });
}

const statusBadge = {
    new:     '<span class="badge badge-new">New</span>',
    open:    '<span class="badge badge-open">Open</span>',
    replied: '<span class="badge badge-replied">Replied</span>',
    closed:  '<span class="badge badge-closed">Closed</span>',
};

async function api(body) {
    const form = new FormData();
    Object.entries(body).forEach(([k,v]) => form.append(k, v));
    const res = await fetch(API, { method:'POST', body:form });
    return res.json();
}

async function apiGet(params) {
    const res = await fetch(`${API}?${new URLSearchParams(params)}`);
    return res.json();
}

async function loadList(filter = currentFilter, page = 1) {
    currentFilter = filter;
    currentPage   = page;
    document.getElementById('inquiryListBody').innerHTML =
        '<div class="empty-list"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';

    let data;
    try {
        data = await apiGet({ action:'list_inquiries', filter, page });
    } catch(e) {
        document.getElementById('inquiryListBody').innerHTML =
            '<div class="empty-list">Could not connect to API.</div>';
        return;
    }

    if (!data.success) {
        // Show setup notice if table missing
        if (data.error && data.error.toLowerCase().includes("exist")) {
            document.getElementById('setupNotice').style.display = 'block';
        }
        document.getElementById('inquiryListBody').innerHTML =
            `<div class="empty-list">${escHtml(data.error || 'Failed to load.')}</div>`;
        return;
    }

    document.getElementById('setupNotice').style.display = 'none';
    document.querySelectorAll('.filter-tab').forEach(t =>
        t.classList.toggle('active', t.dataset.filter === filter));

    const rows = data.rows;
    if (!rows.length) {
        document.getElementById('inquiryListBody').innerHTML =
            '<div class="empty-list"><i class="fas fa-inbox"></i><br>No inquiries found.</div>';
        document.getElementById('paginationBar').style.display = 'none';
        return;
    }

    document.getElementById('inquiryListBody').innerHTML = rows.map(r => `
        <div class="inquiry-item ${r.is_read==0?'unread':''} ${currentId==r.id?'active':''}"
             data-id="${r.id}" onclick="openInquiry(${r.id})">
            <div class="inquiry-name">
                ${r.is_read==0?'<span class="unread-dot"></span>':''}
                ${escHtml(r.name)}
            </div>
            <div class="inquiry-sub" title="${escHtml(r.subject)}">${escHtml(r.subject)}</div>
            <div class="inquiry-sub" style="color:#999;">${escHtml(r.excerpt)}…</div>
            <div class="inquiry-time">${relTime(r.created_at)}</div>
            <div class="inquiry-status-badge">${statusBadge[r.status]??''}</div>
        </div>
    `).join('');

    const totalPages = Math.ceil(data.total / data.limit);
    const pb = document.getElementById('paginationBar');
    if (totalPages <= 1) { pb.style.display='none'; return; }
    pb.style.display = 'flex';
    pb.innerHTML = Array.from({length:totalPages},(_,i) =>
        `<button class="page-btn ${i+1===page?'active':''}" onclick="loadList('${filter}',${i+1})">${i+1}</button>`
    ).join('');
}

async function openInquiry(id) {
    currentId = id;
    document.querySelectorAll('.inquiry-item').forEach(el =>
        el.classList.toggle('active', el.dataset.id==id));

    document.getElementById('convEmpty').style.display = 'none';
    const cc = document.getElementById('convContent');
    cc.style.display = 'flex';
    cc.innerHTML = `<div style="flex:1;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-spinner fa-spin" style="color:#0056b3;font-size:1.5rem;"></i></div>`;

    await api({ action:'mark_read', id });
    const data = await apiGet({ action:'get_inquiry', id });
    if (!data.success) { toast('Failed to load inquiry','error'); return; }

    const inq     = data.inquiry;
    const replies = data.replies;

    let bubbles = `
        <div class="msg-bubble user">
            <div class="bubble-label">Visitor</div>
            ${escHtml(inq.message).replace(/\n/g,'<br>')}
            <div class="bubble-meta">${fullDate(inq.created_at)}</div>
        </div>`;

    replies.forEach(rep => {
        bubbles += `
            <div class="msg-bubble admin">
                <div class="bubble-label">${escHtml(rep.admin_name??'Admin')}</div>
                ${escHtml(rep.reply_text).replace(/\n/g,'<br>')}
                <div class="bubble-meta">
                    ${fullDate(rep.sent_at)}
                    ${rep.email_sent
                        ? '<i class="fas fa-paper-plane" title="Email sent" style="margin-left:4px;"></i>'
                        : '<i class="fas fa-exclamation-triangle" title="Email failed" style="margin-left:4px;color:#fbbf24;"></i>'}
                </div>
            </div>`;
    });

    cc.innerHTML = `
        <div class="conv-header">
            <div class="conv-header-info">
                <h3>${escHtml(inq.subject)}</h3>
                <p>
                    <i class="fas fa-user" style="color:#0056b3;margin-right:4px;"></i>${escHtml(inq.name)} &nbsp;
                    <a href="mailto:${escHtml(inq.email)}" style="color:#0056b3;">${escHtml(inq.email)}</a> &nbsp;
                    ${statusBadge[inq.status]??''}
                </p>
            </div>
            <div class="conv-header-actions">
                ${inq.is_read==1
                    ? `<button class="btn btn-sm btn-outline" onclick="markUnread(${inq.id})"><i class="fas fa-envelope"></i> Mark Unread</button>`
                    : `<button class="btn btn-sm btn-outline" onclick="markRead(${inq.id})"><i class="fas fa-envelope-open"></i> Mark Read</button>`}
                <button class="btn btn-sm btn-danger" onclick="deleteInquiry(${inq.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <div class="conv-body" id="convBody">${bubbles}</div>
        <div class="conv-footer">
            <textarea class="reply-textarea" id="replyText"
                placeholder="Type your reply here… It will be sent to ${escHtml(inq.email)}"></textarea>
            <div class="reply-actions">
                <span id="emailNote" style="font-size:.75rem;color:#666;align-self:center;"></span>
                <button class="btn btn-primary" id="sendReplyBtn" onclick="sendReply(${inq.id})">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
            </div>
        </div>`;

    const body = document.getElementById('convBody');
    if (body) body.scrollTop = body.scrollHeight;
    loadList(currentFilter, currentPage);
}

async function sendReply(id) {
    const replyText = document.getElementById('replyText')?.value?.trim();
    if (!replyText) { toast('Please type a reply first.','error'); return; }
    const btn  = document.getElementById('sendReplyBtn');
    const note = document.getElementById('emailNote');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Sending…';
    const data = await api({ action:'send_reply', id, reply_text:replyText });
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reply';
    if (!data.success) { toast('Failed to save reply.','error'); return; }
    if (data.email_sent) {
        toast('Reply sent and email delivered!','success');
        note.textContent = '';
    } else {
        toast('Reply saved, but email delivery failed. Check SMTP settings.','error',6000);
        note.innerHTML = `<i class="fas fa-exclamation-circle" style="color:#f59e0b;"></i> Email not sent`;
    }
    await openInquiry(id);
}

async function markRead(id) {
    await api({ action:'mark_read', id });
    toast('Marked as read','info');
    await openInquiry(id);
}
async function markUnread(id) {
    await api({ action:'mark_unread', id });
    toast('Marked as unread','info');
    loadList(currentFilter, currentPage);
    currentId = null;
    document.getElementById('convContent').style.display = 'none';
    document.getElementById('convEmpty').style.display   = 'flex';
}
async function deleteInquiry(id) {
    if (!confirm('Permanently delete this inquiry?')) return;
    const data = await api({ action:'delete_inquiry', id });
    if (data.success) {
        toast('Inquiry deleted.','success');
        currentId = null;
        document.getElementById('convContent').style.display = 'none';
        document.getElementById('convEmpty').style.display   = 'flex';
        loadList(currentFilter, currentPage);
    } else {
        toast('Delete failed.','error');
    }
}

async function refreshUnreadCount() {
    try {
        const data = await apiGet({ action:'get_unread_count' });
        if (data.success) {
            const nb = document.getElementById('newBadge');
            if (data.count > 0) { nb.textContent=data.count; nb.style.display='inline-block'; }
            else { nb.style.display='none'; }
        }
    } catch(e) {}
}

function escHtml(str) {
    return String(str??'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

document.querySelectorAll('.filter-tab').forEach(tab =>
    tab.addEventListener('click', () => loadList(tab.dataset.filter, 1)));
document.getElementById('refreshBtn').addEventListener('click', () => {
    loadList(currentFilter, currentPage);
    toast('Refreshed','info',1500);
});

loadList('all', 1);
refreshUnreadCount();
setInterval(() => { loadList(currentFilter, currentPage); refreshUnreadCount(); }, 30000);
</script>

<?php include '../includes/footer.php'; ?>