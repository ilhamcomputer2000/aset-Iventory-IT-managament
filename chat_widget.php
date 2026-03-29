<?php
/**
 * chat_widget.php — Floating Live Chat Widget (Enhanced)
 * Features: Reply, Edit, Delete, File/Image attachment with client-side compression
 */
if (!isset($_SESSION['user_id'])) return;

$_cw_user_id  = (int)$_SESSION['user_id'];
$_cw_nama     = htmlspecialchars((string)($_SESSION['Nama_Lengkap'] ?? $_SESSION['username'] ?? 'User'));
$_cw_role     = (string)($_SESSION['role'] ?? 'user');
$_cw_is_admin = in_array($_cw_role, ['super_admin', 'admin']);
$_cw_initial  = mb_strtoupper(mb_substr($_cw_nama, 0, 1));

require_once __DIR__ . '/app_url.php';
$_cw_endpoint = app_abs_path('chat.php');
// Web base for attachments
$_cw_web_base = rtrim(app_abs_path(''), '/');
?>

<style>
/* ===== Chat Widget Styles ===== */
#cw-bubble {
    position:fixed; bottom:48px; right:24px; z-index:99998;
    width:56px; height:56px; border-radius:50%;
    background:linear-gradient(135deg,#f97316,#ea580c);
    box-shadow:0 4px 20px rgba(249,115,22,.45);
    border:none; cursor:pointer; display:flex;
    align-items:center; justify-content:center;
    transition:transform .2s, box-shadow .2s; color:white;
}
#cw-bubble:hover{transform:scale(1.08);box-shadow:0 6px 28px rgba(249,115,22,.55)}
#cw-bubble-pulse{position:absolute;inset:-4px;border-radius:50%;background:rgba(249,115,22,.3);animation:cwPulse 2.5s ease-out infinite}
@keyframes cwPulse{0%{opacity:.8;transform:scale(1)}100%{opacity:0;transform:scale(1.6)}}
#cw-badge{position:absolute;top:-3px;right:-3px;background:#ef4444;color:white;font-size:10px;font-weight:700;border-radius:999px;min-width:18px;height:18px;display:none;align-items:center;justify-content:center;padding:0 4px;border:2px solid white}
#cw-panel{position:fixed;bottom:116px;right:24px;z-index:99999;width:370px;max-width:calc(100vw - 32px);max-height:min(580px,calc(100vh - 130px));background:rgba(255,255,255,.97);backdrop-filter:blur(20px);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.18),0 4px 16px rgba(0,0,0,.08);display:none;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.5);animation:cwSlideIn .25s cubic-bezier(.34,1.56,.64,1) forwards}
@keyframes cwSlideIn{from{opacity:0;transform:scale(.85) translateY(12px)}to{opacity:1;transform:scale(1) translateY(0)}}
/* Header */
#cw-header{background:linear-gradient(135deg,#f97316,#ea580c);padding:14px 16px 12px;color:white;flex-shrink:0}
#cw-header h3{font-size:15px;font-weight:700;margin:0 0 2px}
#cw-header p{font-size:11px;margin:0;opacity:.85}
#cw-online-dot{display:inline-block;width:7px;height:7px;background:#4ade80;border-radius:50%;margin-right:4px;box-shadow:0 0 6px #4ade80}
/* Messages area */
#cw-messages{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:6px;scroll-behavior:smooth;background:#f9fafb}
#cw-messages::-webkit-scrollbar{width:4px}
#cw-messages::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:2px}
/* Message rows */
.cw-msg{display:flex;gap:8px;align-items:flex-end;position:relative}
.cw-msg.own{flex-direction:row-reverse}
.cw-avatar{width:28px;height:28px;border-radius:50%;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white}
.cw-bubble-wrap{max-width:78%;position:relative}
.cw-sender{font-size:10px;font-weight:600;color:#6b7280;margin-bottom:2px;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.cw-msg.own .cw-sender{justify-content:flex-end}
/* IT badge (admin) */
.cw-it-badge{display:inline-block;background:#f97316;color:white;font-size:9px;font-weight:700;padding:1px 6px;border-radius:999px;letter-spacing:.3px}
/* Jabatan badge */
.cw-jabatan-badge{display:inline-block;font-size:9px;font-weight:600;padding:1px 6px;border-radius:999px;opacity:.95}
/* Reply snippet inside bubble */
.cw-reply-snippet{background:rgba(0,0,0,.06);border-left:3px solid #f97316;border-radius:6px;padding:5px 8px;margin-bottom:4px;font-size:11px;color:#374151}
.cw-reply-snippet strong{font-weight:600;color:#f97316;display:block;margin-bottom:1px}
.cw-msg.own .cw-reply-snippet{background:rgba(255,255,255,.25);border-color:rgba(255,255,255,.7);color:rgba(255,255,255,.9)}
.cw-msg.own .cw-reply-snippet strong{color:white}
/* Text bubble */
.cw-text{background:white;border:1px solid #e5e7eb;color:#1f2937;font-size:13px;padding:8px 12px;border-radius:16px 16px 16px 4px;word-break:break-word;line-height:1.45;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.cw-msg.own .cw-text{background:linear-gradient(135deg,#f97316,#ea580c);color:white;border-color:transparent;border-radius:16px 16px 4px 16px}
.cw-time{font-size:9px;color:#9ca3af;margin-top:3px;padding:0 4px;display:flex;align-items:center;gap:3px}
.cw-msg.own .cw-time{justify-content:flex-end}
.cw-edited-tag{font-size:9px;color:#9ca3af;font-style:italic}
/* Image attachment */
.cw-img-attach{max-width:100%;max-height:200px;border-radius:10px;cursor:pointer;display:block;object-fit:cover;margin-top:4px;transition:opacity .15s}
.cw-img-attach:hover{opacity:.9}
/* File attachment */
.cw-file-attach{display:flex;align-items:center;gap:8px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;margin-top:4px;cursor:pointer;text-decoration:none;color:#374151;font-size:12px;transition:background .15s}
.cw-file-attach:hover{background:#e9ecef}
.cw-msg.own .cw-file-attach{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:white}
.cw-file-icon{font-size:20px;flex-shrink:0}
/* Dropdown menu */
.cw-dropdown{position:absolute;background:white;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.12);z-index:100;display:none;min-width:130px;overflow:hidden}
.cw-msg.own .cw-dropdown{right:0}
.cw-msg:not(.own) .cw-dropdown{left:0}
.cw-dropdown button{display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;border:none;background:none;cursor:pointer;font-size:12px;font-weight:500;color:#374151;text-align:left;transition:background .12s}
.cw-dropdown button:hover{background:#f9fafb}
.cw-dropdown button.danger{color:#ef4444}
.cw-dropdown button.danger:hover{background:#fef2f2}
/* Message action dots button */
.cw-action-btn{background:none;border:none;cursor:pointer;color:#9ca3af;font-size:13px;padding:2px 5px;border-radius:6px;opacity:0;transition:opacity .2s;flex-shrink:0}
.cw-msg:hover .cw-action-btn{opacity:1}
.cw-action-btn:hover{background:#f3f4f6;color:#374151}
/* Date divider */
.cw-date-divider{text-align:center;font-size:10px;color:#9ca3af;background:#f3f4f6;border-radius:999px;padding:2px 12px;align-self:center;margin:4px 0}
/* Empty state */
#cw-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 16px;color:#9ca3af;font-size:13px;gap:8px;text-align:center}
/* Reply preview bar */
#cw-reply-bar{display:none;background:#fff7ed;border-top:1px solid #fed7aa;padding:8px 14px;flex-shrink:0;position:relative}
#cw-reply-bar .rp-name{font-size:11px;font-weight:600;color:#ea580c}
#cw-reply-bar .rp-text{font-size:11px;color:#6b7280;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px}
#cw-reply-close{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:14px;color:#9ca3af;cursor:pointer}
/* Attach preview bar */
#cw-attach-bar{display:none;padding:6px 12px;background:#f9fafb;border-top:1px solid #f3f4f6;flex-shrink:0}
#cw-attach-preview{display:flex;align-items:center;gap:8px;font-size:12px;color:#374151}
#cw-attach-thumb{max-height:48px;max-width:80px;border-radius:6px;object-fit:cover;display:none}
/* Footer */
#cw-footer{padding:10px 12px;background:white;border-top:1px solid #f3f4f6;flex-shrink:0}
#cw-input-row{display:flex;gap:8px;align-items:flex-end}
#cw-input{flex:1;border:1.5px solid #e5e7eb;border-radius:12px;padding:8px 12px;font-size:13px;resize:none;outline:none;max-height:100px;min-height:38px;line-height:1.4;background:#f9fafb;transition:border-color .15s;font-family:inherit}
#cw-input:focus{border-color:#f97316;background:white}
.cw-foot-btn{width:36px;height:38px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#6b7280;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;font-size:14px}
.cw-foot-btn:hover{border-color:#f97316;color:#f97316;background:#fff7ed}
#cw-send{background:linear-gradient(135deg,#f97316,#ea580c);border-color:transparent;color:white;}
#cw-send:hover{transform:scale(1.06);border-color:transparent}
#cw-send:disabled{opacity:.45;cursor:default;transform:none}
#cw-char{font-size:10px;color:#d1d5db;text-align:right;margin-top:3px}
/* Lightbox */
#cw-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:99999;display:none;align-items:center;justify-content:center}
#cw-lightbox img{max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain}
#cw-lightbox-close{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);border:none;color:white;font-size:20px;width:36px;height:36px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center}
/* Edit mode indicator */
#cw-edit-bar{display:none;background:#eff6ff;border-top:1px solid #bfdbfe;padding:6px 14px;flex-shrink:0;position:relative}
#cw-edit-bar span{font-size:11px;color:#2563eb;font-weight:500}
#cw-edit-close{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:14px;color:#9ca3af;cursor:pointer}
/* Read receipts */
.cw-read-receipt{display:inline-flex;align-items:center;gap:1px;cursor:pointer;position:relative;margin-left:3px;vertical-align:middle}
.cw-check{font-size:11px;font-weight:700;line-height:1}
.cw-check.sent{color:#9ca3af}
.cw-check.seen{color:#f97316}
/* Readers tooltip */
.cw-readers-tip{position:absolute;bottom:calc(100% + 6px);right:0;background:#1f2937;color:white;border-radius:8px;padding:6px 10px;font-size:10px;min-width:120px;max-width:200px;white-space:normal;box-shadow:0 4px 16px rgba(0,0,0,.2);display:none;z-index:200;line-height:1.5}
.cw-readers-tip::after{content:'';position:absolute;top:100%;right:8px;border:5px solid transparent;border-top-color:#1f2937}
.cw-read-receipt:hover .cw-readers-tip{display:block}
.cw-readers-tip strong{display:block;margin-bottom:3px;color:#fb923c;font-size:10px}
@media(max-width:480px){
    #cw-panel{width:calc(100vw - 24px);right:12px;bottom:76px}
    #cw-bubble{right:12px;bottom:68px}
}
/* Extra small viewport (zoom-out on small monitors) */
@media(max-height:600px){
    #cw-panel{max-height:calc(100vh - 100px);bottom:76px}
}
/* Ensure bubble stays inside viewport */
#cw-bubble{max-width:56px;max-height:56px;}
</style>

<!-- Bubble -->
<button id="cw-bubble" onclick="cwToggle()" aria-label="Obrolan">
    <div id="cw-bubble-pulse"></div>
    <i class="fas fa-comment-dots" id="cw-bubble-icon" style="font-size:22px"></i>
    <span id="cw-badge"></span>
</button>

<!-- Lightbox -->
<div id="cw-lightbox" onclick="document.getElementById('cw-lightbox').style.display='none'">
    <button id="cw-lightbox-close"><i class="fas fa-times"></i></button>
    <img id="cw-lightbox-img" src="" alt="Preview">
</div>

<!-- Chat Panel -->
<div id="cw-panel">
    <!-- Header -->
    <div id="cw-header">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <div>
                <h3>💬 Obrolan Umum</h3>
                <p><span id="cw-online-dot"></span><span id="cw-online-count">1</span> pengguna online</p>
            </div>
            <button onclick="cwToggle()" style="background:rgba(255,255,255,.2);border:none;color:white;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-times" style="font-size:12px"></i>
            </button>
        </div>
    </div>

    <!-- Messages -->
    <div id="cw-messages">
        <div id="cw-empty">
            <i class="fas fa-comments" style="font-size:32px;color:#e5e7eb"></i>
            <span>Belum ada pesan. Mulai obrolan!</span>
        </div>
    </div>

    <!-- Edit bar -->
    <div id="cw-edit-bar">
        <span><i class="fas fa-pencil-alt" style="margin-right:5px"></i>Mode edit pesan</span>
        <button id="cw-edit-close"><i class="fas fa-times"></i></button>
    </div>

    <!-- Reply bar -->
    <div id="cw-reply-bar">
        <div class="rp-name" id="cw-reply-name"></div>
        <div class="rp-text" id="cw-reply-text"></div>
        <button id="cw-reply-close"><i class="fas fa-times"></i></button>
    </div>

    <!-- Attach preview bar -->
    <div id="cw-attach-bar">
        <div id="cw-attach-preview">
            <img id="cw-attach-thumb" src="" alt="">
            <i class="fas fa-paperclip" id="cw-attach-icon" style="color:#f97316"></i>
            <span id="cw-attach-label"></span>
            <button onclick="cwClearAttach()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:#9ca3af;font-size:14px"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- Footer -->
    <div id="cw-footer">
        <div id="cw-input-row">
            <button class="cw-foot-btn" onclick="document.getElementById('cw-file-input').click()" title="Lampirkan file/foto">
                <i class="fas fa-paperclip"></i>
            </button>
            <textarea id="cw-input" placeholder="Ketik pesan..." rows="1" maxlength="1000"></textarea>
            <button id="cw-send" onclick="cwSend()" disabled>
                <i class="fas fa-paper-plane" style="font-size:13px"></i>
            </button>
        </div>
        <div id="cw-char">0 / 1000</div>
        <input type="file" id="cw-file-input" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
    </div>
</div>

<script>
(function() {
    const ENDPOINT   = <?php echo json_encode($_cw_endpoint); ?>;
    const WEB_BASE   = <?php echo json_encode($_cw_web_base); ?>;
    const MY_ID      = <?php echo $_cw_user_id; ?>;
    const MY_NAMA    = <?php echo json_encode($_cw_nama); ?>;
    const IS_ADMIN   = <?php echo $_cw_is_admin ? 'true' : 'false'; ?>;

    let panelOpen   = false;
    let lastId      = 0;
    let unread      = 0;
    let pollTimer   = null;
    let lastDateStr = '';
    let replyToId   = 0;
    let editMsgId   = 0;
    let pendingFile = null; // { blob, name, type ('image'|'file'), dataUrl }
    let openDropdown = null;

    const panel     = document.getElementById('cw-panel');
    const msgs      = document.getElementById('cw-messages');
    const empty     = document.getElementById('cw-empty');
    const badge     = document.getElementById('cw-badge');
    const input     = document.getElementById('cw-input');
    const sendBtn   = document.getElementById('cw-send');
    const charEl    = document.getElementById('cw-char');
    const onlineEl  = document.getElementById('cw-online-count');
    const bubIcon   = document.getElementById('cw-bubble-icon');
    const replyBar  = document.getElementById('cw-reply-bar');
    const editBar   = document.getElementById('cw-edit-bar');
    const attachBar = document.getElementById('cw-attach-bar');
    const fileInput = document.getElementById('cw-file-input');
    const lbImg     = document.getElementById('cw-lightbox-img');
    const lightbox  = document.getElementById('cw-lightbox');

    // ---- Avatar color ----
    function avatarColor(uid) {
        const c = ['#f97316','#8b5cf6','#06b6d4','#10b981','#f43f5e','#3b82f6','#ec4899','#84cc16'];
        return c[Math.abs(uid) % c.length];
    }

    function formatDate(d) {
        const today = new Date();
        const tStr = `${today.getDate()} ${['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][today.getMonth()]} ${today.getFullYear()}`;
        return d === tStr ? 'Hari ini' : d;
    }

    function fileIcon(name) {
        const ext = (name||'').split('.').pop().toLowerCase();
        if (['pdf'].includes(ext)) return '📄';
        if (['doc','docx'].includes(ext)) return '📝';
        if (['xls','xlsx'].includes(ext)) return '📊';
        if (['ppt','pptx'].includes(ext)) return '📑';
        if (['zip','rar'].includes(ext)) return '🗜️';
        return '📎';
    }

    function fileSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
        return (bytes/1048576).toFixed(1) + ' MB';
    }

    // ---- Build message HTML ----
    function buildMsgEl(m) {
        const isOwn   = parseInt(m.user_id) === MY_ID;
        const isAdmin = m.is_admin;
        const color   = avatarColor(parseInt(m.user_id));
        const initial = (m.nama_display || '?').charAt(0).toUpperCase();

        // Date divider
        let divHtml = '';
        if (m.date_display && m.date_display !== lastDateStr) {
            lastDateStr = m.date_display;
            divHtml = `<div class="cw-date-divider">${formatDate(m.date_display)}</div>`;
        }

        // Reply snippet
        let replyHtml = '';
        if (m.reply_snippet) {
            const rs = m.reply_snippet;
            const rText = rs.has_file ? `📎 ${rs.file}` : rs.text;
            replyHtml = `<div class="cw-reply-snippet"><strong>${rs.name}</strong>${rText}</div>`;
        }

        // Attachment
        let attachHtml = '';
        if (m.attachment_path && m.attachment_type === 'image') {
            const url = WEB_BASE + '/' + m.attachment_path;
            attachHtml = `<img class="cw-img-attach" src="${url}" alt="${m.attachment_name||''}" onclick="cwOpenLightbox('${url}')">`;
        } else if (m.attachment_path && m.attachment_type === 'file') {
            const url = WEB_BASE + '/' + m.attachment_path;
            const ico = fileIcon(m.attachment_name);
            const sz  = fileSize(parseInt(m.attachment_size||0));
            attachHtml = `<a class="cw-file-attach" href="${url}" target="_blank" download>
                <span class="cw-file-icon">${ico}</span>
                <div>
                    <div style="font-weight:600">${m.attachment_name||'File'}</div>
                    <div style="font-size:10px;opacity:.7">${sz}</div>
                </div>
                <i class="fas fa-download" style="margin-left:auto;font-size:12px;opacity:.6"></i>
            </a>`;
        }

        // Message text (empty if only attachment)
        const msgText = m.message ? `<div class="cw-text">${replyHtml}${m.message}${attachHtml}</div>` : `<div class="cw-text">${replyHtml}${attachHtml}</div>`;

        // Edit tag
        const editedTag = m.is_edited ? '<span class="cw-edited-tag">✎ diedit</span>' : '';

        // Read receipt (only for own messages)
        let readHtml = '';
        if (isOwn) {
            const cnt     = parseInt(m.read_count || 0);
            const readers = m.read_by || [];
            const isSeen  = cnt > 0;
            const tipName = readers.length > 0 ? readers.slice(0, 5).join(', ') + (readers.length > 5 ? ` +${readers.length-5}` : '') : '';
            const tipHtml = isSeen
                ? `<div class="cw-readers-tip"><strong>Dilihat oleh:</strong>${tipName}</div>`
                : `<div class="cw-readers-tip"><strong>Belum dilihat</strong></div>`;
            readHtml = `<span class="cw-read-receipt" data-rid="${m.id}">${tipHtml}
                <span class="cw-check ${isSeen?'seen':'sent'}">✓</span>
                <span class="cw-check ${isSeen?'seen':'sent'}">✓</span>
            </span>`;
        }

        // Dropdown
        const canEdit   = isOwn;
        const canDelete = isOwn || IS_ADMIN;
        let ddHtml = '';
        if (canEdit || canDelete) {
            const editBtn   = canEdit ? `<button onclick="cwEditMsg(${m.id},this)"><i class="fas fa-pencil-alt"></i> Edit</button>` : '';
            const deleteBtn = canDelete ? `<button class="danger" onclick="cwDeleteMsg(${m.id},this)"><i class="fas fa-trash"></i> Hapus</button>` : '';
            ddHtml = `<div class="cw-dropdown" id="cwd-${m.id}">
                <button onclick="cwReplyTo(${m.id},'${(m.nama_display||'').replace(/'/g,'\\\'')}',${'`'+m.message.replace(/`/g,'\\`')+'`'})"><i class="fas fa-reply"></i> Balas</button>
                ${editBtn}${deleteBtn}
            </div>`;
        } else {
            ddHtml = `<div class="cw-dropdown" id="cwd-${m.id}">
                <button onclick="cwReplyTo(${m.id},'${(m.nama_display||'').replace(/'/g,'\\\'')}',${'`'+m.message.replace(/`/g,'\\`')+'`'})"><i class="fas fa-reply"></i> Balas</button>
            </div>`;
        }

        const actionBtn = `<button class="cw-action-btn" onclick="cwToggleDropdown(${m.id},event)"><i class="fas fa-ellipsis-h"></i></button>${ddHtml}`;

        const sender = isOwn ? 'Kamu' : m.nama_display;

        // IT badge for admin/super_admin
        const itBadge = isAdmin ? '<span class="cw-it-badge">IT</span>' : '';

        // Jabatan badge with smart color
        let jabatanBadge = '';
        if (m.jabatan) {
            const jab   = m.jabatan.toUpperCase();
            let bg, fg;
            if (jab.includes('IT') || jab.includes('TEKNOLOGI') || jab.includes('SYSTEM') || jab.includes('NETWORK')) {
                bg='#fff7ed'; fg='#ea580c'; // orange tint
            } else if (jab.includes('HRD') || jab.includes('HR ') || jab.includes('SDM') || jab.includes('HUMAN')) {
                bg='#f0fdf4'; fg='#16a34a'; // green
            } else if (jab.includes('GA') || jab.includes('GENERAL') || jab.includes('ASSET')) {
                bg='#eff6ff'; fg='#2563eb'; // blue
            } else if (jab.includes('FINANCE') || jab.includes('AKUNTANSI') || jab.includes('KEUANGAN')) {
                bg='#fdf4ff'; fg='#9333ea'; // purple
            } else if (jab.includes('MARKETING') || jab.includes('SALES')) {
                bg='#fff1f2'; fg='#e11d48'; // rose
            } else if (jab.includes('MANAGER') || jab.includes('KEPALA') || jab.includes('SUPERVISOR')) {
                bg='#fefce8'; fg='#ca8a04'; // amber
            } else if (jab.includes('SECURITY') || jab.includes('SATPAM')) {
                bg='#f1f5f9'; fg='#475569'; // slate
            } else {
                bg='#f3f4f6'; fg='#4b5563'; // gray
            }
            jabatanBadge = `<span class="cw-jabatan-badge" style="background:${bg};color:${fg};border:1px solid ${fg}22">${m.jabatan}</span>`;
        }

        const html = `
            ${divHtml}
            <div class="cw-msg${isOwn?' own':''}" data-mid="${m.id}">
                <div class="cw-avatar" style="background:${color}">${initial}</div>
                <div class="cw-bubble-wrap">
                    <div class="cw-sender">${itBadge}<span>${sender}</span>${jabatanBadge}</div>
                    ${msgText}
                    <div class="cw-time">${m.time_display}${editedTag}${readHtml}</div>
                </div>
                ${actionBtn}
            </div>`;
        return html;
    }

    function appendMessages(messages, prepend) {
        if (!messages || messages.length === 0) return;
        empty.style.display = 'none';
        const wasBottom = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 80;
        let html = '';
        messages.forEach(m => { html += buildMsgEl(m); });
        if (prepend) msgs.insertAdjacentHTML('afterbegin', html);
        else msgs.insertAdjacentHTML('beforeend', html);
        const last = messages[messages.length - 1];
        if (wasBottom || (last && parseInt(last.user_id) === MY_ID)) {
            msgs.scrollTop = msgs.scrollHeight;
        }
    }

    // ---- Toggle panel ----
    window.cwToggle = function() {
        panelOpen = !panelOpen;
        panel.style.display = panelOpen ? 'flex' : 'none';
        bubIcon.className = panelOpen ? 'fas fa-times' : 'fas fa-comment-dots';
        bubIcon.style.fontSize = panelOpen ? '18px' : '22px';
        if (panelOpen) {
            unread = 0; badge.style.display = 'none';
            if (lastId === 0) loadInitial(); else startPoll();
            input.focus();
        } else {
            stopPoll();
        }
    };

    // ---- Load & poll ----
    function loadInitial() {
        fetch(`${ENDPOINT}?action=get`)
            .then(r=>r.json())
            .then(data => {
                if (data.messages && data.messages.length) {
                    lastId = data.last_id;
                    appendMessages(data.messages, false);
                    msgs.scrollTop = msgs.scrollHeight;
                }
                if (data.online !== undefined) onlineEl.textContent = data.online;
                startPoll();
            }).catch(() => startPoll());
    }

    function poll() {
        fetch(`${ENDPOINT}?action=get&after_id=${lastId}`)
            .then(r=>r.json())
            .then(data => {
                if (data.messages && data.messages.length) {
                    const fromOthers = data.messages.filter(m => parseInt(m.user_id) !== MY_ID).length;
                    if (fromOthers > 0) {
                        // Play notification sound
                        cwPlayNotif();
                        if (!panelOpen) {
                            unread += fromOthers;
                            badge.textContent = unread > 99 ? '99+' : unread;
                            badge.style.display = 'flex';
                        }
                    }
                    lastId = data.last_id;
                    appendMessages(data.messages, false);
                    if (panelOpen) { unread = 0; badge.style.display = 'none'; }
                }
                if (data.online !== undefined) onlineEl.textContent = data.online;
                // ---- Live update read receipts on existing own messages ----
                if (data.messages && data.messages.length) {
                    data.messages.forEach(m => {
                        if (parseInt(m.user_id) !== MY_ID) {
                            // Someone else's message was fetched — means WE just read it.
                            // Also update existing own messages' receipt counters via a separate reads check.
                        }
                    });
                }
                // Refresh receipts on own visible messages
                cwRefreshReceipts();
            }).catch(()=>{});
    }

    function startPoll() { stopPoll(); pollTimer = setInterval(poll, 3000); }
    function stopPoll()  { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    // ---- Notification Sound (Web Audio API — no external file needed) ----
    let _audioCtx = null;
    function cwPlayNotif() {
        try {
            if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const ctx = _audioCtx;
            const now = ctx.currentTime;
            // Note 1: 880 Hz (A5) — short ping
            const o1 = ctx.createOscillator();
            const g1 = ctx.createGain();
            o1.type = 'sine'; o1.frequency.setValueAtTime(880, now);
            g1.gain.setValueAtTime(0.0, now);
            g1.gain.linearRampToValueAtTime(0.28, now + 0.01);
            g1.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);
            o1.connect(g1); g1.connect(ctx.destination);
            o1.start(now); o1.stop(now + 0.22);
            // Note 2: 1100 Hz — higher follow-up
            const o2 = ctx.createOscillator();
            const g2 = ctx.createGain();
            o2.type = 'sine'; o2.frequency.setValueAtTime(1100, now + 0.12);
            g2.gain.setValueAtTime(0.0, now + 0.12);
            g2.gain.linearRampToValueAtTime(0.22, now + 0.13);
            g2.gain.exponentialRampToValueAtTime(0.0001, now + 0.38);
            o2.connect(g2); g2.connect(ctx.destination);
            o2.start(now + 0.12); o2.stop(now + 0.38);
        } catch(e) {}
    }

    // ---- Live Read Receipt Refresh ----
    // Collect IDs of own messages visible in panel, fetch their read info
    function cwRefreshReceipts() {
        if (!panelOpen) return;
        const receipts = msgs.querySelectorAll('.cw-read-receipt[data-rid]');
        if (!receipts.length) return;
        const ids = Array.from(receipts).map(el => el.getAttribute('data-rid')).filter(Boolean);
        if (!ids.length) return;

        fetch(`${ENDPOINT}?action=get_reads&ids=${ids.join(',')}`)
            .then(r => r.json())
            .then(data => {
                if (!data.reads) return;
                receipts.forEach(el => {
                    const mid = el.getAttribute('data-rid');
                    const info = data.reads[mid];
                    if (!info) return;
                    const isSeen = info.count > 0;
                    const checks = el.querySelectorAll('.cw-check');
                    checks.forEach(c => {
                        c.className = 'cw-check ' + (isSeen ? 'seen' : 'sent');
                    });
                    const tip = el.querySelector('.cw-readers-tip');
                    if (tip) {
                        if (isSeen) {
                            const names = (info.names || []).slice(0, 5).join(', ') + (info.names.length > 5 ? ` +${info.names.length-5}` : '');
                            tip.innerHTML = `<strong>Dilihat oleh:</strong>${names}`;
                        } else {
                            tip.innerHTML = '<strong>Belum dilihat</strong>';
                        }
                    }
                });
            }).catch(() => {});
    }

    // Refresh receipts every 5 seconds when panel is open
    setInterval(() => { if (panelOpen) cwRefreshReceipts(); }, 5000);

    // Background poll for unread badge
    setInterval(() => { if (!panelOpen) poll(); }, 8000);
    // Presence ping every 60s (within 90s server window)
    setInterval(() => { fetch(`${ENDPOINT}?action=online`).catch(()=>{}); }, 60000);

    // ---- Dropdown ----
    window.cwToggleDropdown = function(mid, e) {
        e.stopPropagation();
        const dd = document.getElementById('cwd-' + mid);
        if (!dd) return;
        if (openDropdown && openDropdown !== dd) openDropdown.style.display = 'none';
        dd.style.display = dd.style.display === 'block' ? 'none' : 'block';
        openDropdown = dd.style.display === 'block' ? dd : null;
        // Position above or below
        const rect = dd.parentElement.getBoundingClientRect();
        if (rect.bottom + 150 > window.innerHeight) dd.style.bottom = '100%';
    };
    document.addEventListener('click', () => { if (openDropdown) { openDropdown.style.display='none'; openDropdown=null; } });

    // ---- Reply ----
    window.cwReplyTo = function(id, name, text) {
        if (openDropdown) { openDropdown.style.display='none'; openDropdown=null; }
        replyToId = id;
        editMsgId = 0;
        editBar.style.display = 'none';
        document.getElementById('cw-reply-name').textContent = 'Membalas ' + name;
        document.getElementById('cw-reply-text').textContent = text || '📎 Lampiran';
        replyBar.style.display = 'block';
        input.focus();
    };
    document.getElementById('cw-reply-close').onclick = function() {
        replyToId = 0; replyBar.style.display = 'none';
    };

    // ---- Edit ----
    window.cwEditMsg = function(id, btn) {
        if (openDropdown) { openDropdown.style.display='none'; openDropdown=null; }
        const msgEl = document.querySelector(`[data-mid="${id}"] .cw-text`);
        const text = msgEl ? (msgEl.textContent || '').trim() : '';
        editMsgId = id;
        replyToId = 0;
        replyBar.style.display = 'none';
        editBar.style.display = 'block';
        input.value = text;
        charEl.textContent = `${text.length} / 1000`;
        sendBtn.disabled = text.trim().length === 0;
        autoResize();
        input.focus();
    };
    document.getElementById('cw-edit-close').onclick = function() {
        editMsgId = 0; editBar.style.display = 'none'; input.value = ''; charEl.textContent = '0 / 1000'; sendBtn.disabled = true; autoResize();
    };

    // ---- Delete ----
    window.cwDeleteMsg = function(id, btn) {
        if (openDropdown) { openDropdown.style.display='none'; openDropdown=null; }
        if (!confirm('Hapus pesan ini?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_msg'); fd.append('id', id);
        fetch(ENDPOINT, { method:'POST', body:fd })
            .then(r=>r.json())
            .then(data => {
                if (data.success) {
                    const el = document.querySelector(`[data-mid="${id}"]`);
                    if (el) el.remove();
                }
            }).catch(()=>{});
    };

    // ---- Lightbox ----
    window.cwOpenLightbox = function(url) {
        lbImg.src = url;
        lightbox.style.display = 'flex';
    };

    // ---- File Attachment ----
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const ext = file.name.split('.').pop().toLowerCase();
        const isImg = ['jpg','jpeg','png','gif','webp'].includes(ext);
        if (isImg) {
            cwCompressImage(file, (blob, dataUrl) => {
                pendingFile = { blob, name: file.name, type: 'image', dataUrl };
                showAttachPreview();
            });
        } else {
            // Non-image: use as-is
            pendingFile = { blob: file, name: file.name, type: 'file', dataUrl: null };
            showAttachPreview();
        }
        this.value = '';
    });

    function showAttachPreview() {
        if (!pendingFile) return;
        attachBar.style.display = 'block';
        document.getElementById('cw-attach-label').textContent = pendingFile.name;
        const thumb = document.getElementById('cw-attach-thumb');
        const icon  = document.getElementById('cw-attach-icon');
        if (pendingFile.type === 'image' && pendingFile.dataUrl) {
            thumb.src = pendingFile.dataUrl; thumb.style.display = 'block'; icon.style.display = 'none';
        } else {
            thumb.style.display = 'none'; icon.style.display = 'inline';
        }
        updateSendBtn();
    }

    window.cwClearAttach = function() {
        pendingFile = null;
        attachBar.style.display = 'none';
        document.getElementById('cw-attach-thumb').style.display = 'none';
        updateSendBtn();
    };

    // Client-side image compression
    function cwCompressImage(file, callback) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                const MAX = 1280;
                let w = img.width, h = img.height;
                if (w > MAX || h > MAX) {
                    if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                    else       { w = Math.round(w * MAX / h); h = MAX; }
                }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob(blob => {
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.75);
                    callback(blob, dataUrl);
                }, 'image/jpeg', 0.75);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    // ---- Send ----
    window.cwSend = function() {
        const msg = input.value.trim();
        if (!msg && !pendingFile) return;
        if (msg.length > 1000) return;
        sendBtn.disabled = true;

        if (editMsgId > 0) {
            // Edit mode
            const fd = new FormData();
            fd.append('action','edit'); fd.append('id', editMsgId); fd.append('message', msg);
            fetch(ENDPOINT, { method:'POST', body:fd })
                .then(r=>r.json())
                .then(data => {
                    if (data.success) {
                        // Update DOM immediately
                        const el = document.querySelector(`[data-mid="${editMsgId}"] .cw-text`);
                        if (el) {
                            // crude: just update text node, will be refreshed on next poll anyway
                            el.childNodes.forEach(n => { if (n.nodeType === 3) n.textContent = msg; });
                            const et = document.querySelector(`[data-mid="${editMsgId}"] .cw-edited-tag`);
                            if (et) et.textContent = '✎ diedit';
                            else {
                                const t = document.querySelector(`[data-mid="${editMsgId}"] .cw-time`);
                                if (t) t.insertAdjacentHTML('beforeend','<span class="cw-edited-tag"> ✎ diedit</span>');
                            }
                        }
                        document.getElementById('cw-edit-close').click();
                    }
                    sendBtn.disabled = input.value.trim().length === 0 && !pendingFile;
                }).catch(()=>{ sendBtn.disabled = false; });
            return;
        }

        // Normal send
        const fd = new FormData();
        fd.append('action','send');
        fd.append('message', msg);
        if (replyToId > 0) fd.append('reply_to_id', replyToId);
        if (pendingFile) fd.append('attachment', pendingFile.blob, pendingFile.name);

        fetch(ENDPOINT, { method:'POST', body:fd })
            .then(r=>r.json())
            .then(data => {
                if (data.success) {
                    input.value = ''; charEl.textContent = '0 / 1000';
                    autoResize();
                    if (replyToId) { replyToId=0; replyBar.style.display='none'; }
                    if (pendingFile) cwClearAttach();
                    poll();
                }
                sendBtn.disabled = input.value.trim().length === 0 && !pendingFile;
            }).catch(()=>{ sendBtn.disabled = false; });
    };

    function updateSendBtn() {
        sendBtn.disabled = input.value.trim().length === 0 && !pendingFile;
    }

    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    }

    input.addEventListener('input', function() {
        charEl.textContent = `${input.value.length} / 1000`;
        updateSendBtn();
        autoResize();
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sendBtn.disabled) cwSend();
        }
    });
})();
</script>
<!-- ===== END CHAT WIDGET ===== -->
