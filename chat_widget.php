<?php
/**
 * chat_widget.php - Floating Live Chat Widget (Enhanced)
 * Features: Reply, Edit, Delete, File/Image attachment with client-side compression
 */
if (!isset($_SESSION['user_id']))
    return;

$_cw_user_id = (int) $_SESSION['user_id'];
$_cw_nama = htmlspecialchars((string) ($_SESSION['Nama_Lengkap'] ?? $_SESSION['username'] ?? 'User'));
$_cw_role = (string) ($_SESSION['role'] ?? 'user');
$_cw_is_admin = in_array($_cw_role, ['super_admin', 'admin']);
$_cw_initial = mb_strtoupper(mb_substr($_cw_nama, 0, 1));

require_once __DIR__ . '/app_url.php';
$_cw_endpoint = app_abs_path('chat.php');
// Web base for attachments
$_cw_web_base = rtrim(app_abs_path(''), '/');

// Token untuk go_offline — pakai HMAC statis (TIDAK bergantung session_id)
// agar tetap valid bahkan setelah session_destroy() di logout.php (penting untuk hosting)
if (!defined('CW_OFFLINE_SECRET'))
    define('CW_OFFLINE_SECRET', 'cw_offline_s3cr3t_2025_aset_it');
$_cw_offline_token = hash_hmac('sha256', $_cw_user_id . '|' . floor(time() / 600), CW_OFFLINE_SECRET);
?>

<style>
    /* ===== Chat Widget Styles ===== */
    #cw-bubble {
        position: fixed;
        bottom: 48px;
        right: 24px;
        z-index: 99998;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f97316, #ea580c);
        box-shadow: 0 4px 20px rgba(249, 115, 22, .45);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform .2s, box-shadow .2s;
        color: white;
    }

    #cw-bubble:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 28px rgba(249, 115, 22, .55)
    }

    #cw-bubble-pulse {
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        background: rgba(249, 115, 22, .3);
        animation: cwPulse 2.5s ease-out infinite
    }

    @keyframes cwPulse {
        0% {
            opacity: .8;
            transform: scale(1)
        }

        100% {
            opacity: 0;
            transform: scale(1.6)
        }
    }

    #cw-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 700;
        border-radius: 999px;
        min-width: 18px;
        height: 18px;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        border: 2px solid white
    }

    #cw-panel {
        position: fixed;
        bottom: 116px;
        right: 24px;
        z-index: 99999;
        width: 370px;
        max-width: calc(100vw - 32px);
        max-height: min(580px, calc(100vh - 130px));
        background: rgba(255, 255, 255, .97);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .18), 0 4px 16px rgba(0, 0, 0, .08);
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .5);
        animation: cwSlideIn .25s cubic-bezier(.34, 1.56, .64, 1) forwards
    }

    /* Desktop: full screen */
    @media (min-width: 481px) {
        #cw-panel {
            inset: 0;
            width: 100%;
            max-width: 100%;
            max-height: 100%;
            bottom: 0;
            right: 0;
            border-radius: 0;
        }
    }

    @keyframes cwSlideIn {
        from {
            opacity: 0;
            transform: scale(.85) translateY(12px)
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0)
        }
    }

    /* Header */
    #cw-header {
        background: linear-gradient(135deg, #f97316, #ea580c);
        padding: 14px 16px 12px;
        color: white;
        flex-shrink: 0
    }

    #cw-header h3 {
        font-size: 15px;
        font-weight: 700;
        margin: 0 0 2px
    }

    #cw-header p {
        font-size: 11px;
        margin: 0;
        opacity: .85
    }

    #cw-online-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        background: #4ade80;
        border-radius: 50%;
        margin-right: 4px;
        box-shadow: 0 0 6px #4ade80
    }

    /* Messages area */
    #cw-messages {
        flex: 1;
        overflow-y: auto;
        padding: 12px 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        scroll-behavior: smooth;
        background: #f9fafb
    }

    #cw-messages::-webkit-scrollbar {
        width: 4px
    }

    #cw-messages::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px
    }

    /* Message rows */
    .cw-msg {
        display: flex;
        gap: 8px;
        align-items: flex-end;
        position: relative
    }

    .cw-msg.own {
        flex-direction: row-reverse
    }

    .cw-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white
    }

    .cw-bubble-wrap {
        max-width: 78%;
        position: relative
    }

    .cw-sender {
        font-size: 10px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 2px;
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap
    }

    .cw-msg.own .cw-sender {
        justify-content: flex-end
    }

    /* IT badge (admin) */
    .cw-it-badge {
        display: inline-block;
        background: #f97316;
        color: white;
        font-size: 9px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 999px;
        letter-spacing: .3px
    }

    /* Jabatan badge */
    .cw-jabatan-badge {
        display: inline-block;
        font-size: 9px;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 999px;
        opacity: .95
    }

    /* Reply snippet inside bubble */
    .cw-reply-snippet {
        background: rgba(0, 0, 0, .06);
        border-left: 3px solid #f97316;
        border-radius: 6px;
        padding: 5px 8px;
        margin-bottom: 4px;
        font-size: 11px;
        color: #374151
    }

    .cw-reply-snippet strong {
        font-weight: 600;
        color: #f97316;
        display: block;
        margin-bottom: 1px
    }

    .cw-msg.own .cw-reply-snippet {
        background: rgba(255, 255, 255, .25);
        border-color: rgba(255, 255, 255, .7);
        color: rgba(255, 255, 255, .9)
    }

    .cw-msg.own .cw-reply-snippet strong {
        color: white
    }

    /* Text bubble */
    .cw-text {
        background: white;
        border: 1px solid #e5e7eb;
        color: #1f2937;
        font-size: 13px;
        padding: 8px 12px;
        border-radius: 16px 16px 16px 4px;
        word-break: break-word;
        line-height: 1.45;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .05)
    }

    .cw-msg.own .cw-text {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        border-color: transparent;
        border-radius: 16px 16px 4px 16px
    }

    .cw-time {
        font-size: 9px;
        color: #9ca3af;
        margin-top: 3px;
        padding: 0 4px;
        display: flex;
        align-items: center;
        gap: 3px
    }

    .cw-msg.own .cw-time {
        justify-content: flex-end
    }

    .cw-edited-tag {
        font-size: 9px;
        color: #9ca3af;
        font-style: italic
    }

    /* Image attachment */
    .cw-img-attach {
        max-width: 100%;
        max-height: 200px;
        border-radius: 10px;
        cursor: pointer;
        display: block;
        object-fit: cover;
        margin-top: 4px;
        transition: opacity .15s
    }

    .cw-img-attach:hover {
        opacity: .9
    }

    /* File attachment */
    .cw-file-attach {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 8px 10px;
        margin-top: 4px;
        cursor: pointer;
        text-decoration: none;
        color: #374151;
        font-size: 12px;
        transition: background .15s
    }

    .cw-file-attach:hover {
        background: #e9ecef
    }

    .cw-msg.own .cw-file-attach {
        background: rgba(255, 255, 255, .2);
        border-color: rgba(255, 255, 255, .3);
        color: white
    }

    .cw-file-icon {
        font-size: 20px;
        flex-shrink: 0
    }

    /* Dropdown menu */
    .cw-dropdown {
        position: absolute;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .12);
        z-index: 100;
        display: none;
        min-width: 130px;
        overflow: hidden
    }

    .cw-msg.own .cw-dropdown {
        right: 0
    }

    .cw-msg:not(.own) .cw-dropdown {
        left: 0
    }

    .cw-dropdown button {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 9px 14px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        color: #374151;
        text-align: left;
        transition: background .12s
    }

    .cw-dropdown button:hover {
        background: #f9fafb
    }

    .cw-dropdown button.danger {
        color: #ef4444
    }

    .cw-dropdown button.danger:hover {
        background: #fef2f2
    }

    /* Message action dots button */
    .cw-action-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        font-size: 13px;
        padding: 2px 5px;
        border-radius: 6px;
        opacity: 0;
        transition: opacity .2s;
        flex-shrink: 0
    }

    .cw-msg:hover .cw-action-btn {
        opacity: 1
    }

    .cw-action-btn:hover {
        background: #f3f4f6;
        color: #374151
    }

    /* Date divider */
    .cw-date-divider {
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
        background: #f3f4f6;
        border-radius: 999px;
        padding: 2px 12px;
        align-self: center;
        margin: 4px 0
    }

    /* Empty state */
    #cw-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
        color: #9ca3af;
        font-size: 13px;
        gap: 8px;
        text-align: center
    }

    /* Reply preview bar */
    #cw-reply-bar {
        display: none;
        background: #fff7ed;
        border-top: 1px solid #fed7aa;
        padding: 8px 14px;
        flex-shrink: 0;
        position: relative
    }

    #cw-reply-bar .rp-name {
        font-size: 11px;
        font-weight: 600;
        color: #ea580c
    }

    #cw-reply-bar .rp-text {
        font-size: 11px;
        color: #6b7280;
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 280px
    }

    #cw-reply-close {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 14px;
        color: #9ca3af;
        cursor: pointer
    }

    /* Attach preview bar */
    #cw-attach-bar {
        display: none;
        padding: 6px 12px;
        background: #f9fafb;
        border-top: 1px solid #f3f4f6;
        flex-shrink: 0
    }

    #cw-attach-preview,
    #cw-dm-attach-preview {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: #374151;
        overflow-x: auto;
        padding: 2px 0;
    }

    #cw-attach-preview::-webkit-scrollbar,
    #cw-dm-attach-preview::-webkit-scrollbar {
        height: 3px;
    }

    #cw-attach-preview::-webkit-scrollbar-thumb,
    #cw-dm-attach-preview::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px;
    }

    .cw-attach-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 4px 8px;
        flex-shrink: 0;
        max-width: 150px;
    }

    .cw-attach-chip-thumb {
        height: 30px;
        width: 30px;
        border-radius: 4px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .cw-attach-chip-name {
        font-size: 11px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 80px;
    }

    .cw-attach-clear-all {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        font-size: 14px;
        flex-shrink: 0;
        padding: 0 2px;
        transition: color .15s;
    }

    .cw-attach-clear-all:hover {
        color: #ef4444;
    }

    /* Footer */
    #cw-footer {
        padding: 10px 12px;
        background: white;
        border-top: 1px solid #f3f4f6;
        flex-shrink: 0
    }

    #cw-input-row {
        display: flex;
        gap: 8px;
        align-items: flex-end
    }

    #cw-input {
        flex: 1;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        padding: 8px 12px;
        font-size: 13px;
        resize: none;
        outline: none;
        max-height: 100px;
        min-height: 38px;
        line-height: 1.4;
        background: #f9fafb;
        transition: border-color .15s;
        font-family: inherit
    }

    #cw-input:focus {
        border-color: #f97316;
        background: white
    }

    .cw-foot-btn {
        width: 36px;
        height: 38px;
        border-radius: 10px;
        border: 1.5px solid #e5e7eb;
        background: white;
        color: #6b7280;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all .15s;
        font-size: 14px
    }

    .cw-foot-btn:hover {
        border-color: #f97316;
        color: #f97316;
        background: #fff7ed
    }

    #cw-send {
        background: linear-gradient(135deg, #f97316, #ea580c);
        border-color: transparent;
        color: white;
    }

    #cw-send:hover {
        transform: scale(1.06);
        border-color: transparent
    }

    #cw-send:disabled {
        opacity: .45;
        cursor: default;
        transform: none
    }

    #cw-char {
        font-size: 10px;
        color: #d1d5db;
        text-align: right;
        margin-top: 3px
    }

    /* Lightbox */
    #cw-lightbox {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .88);
        z-index: 200000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 60px 20px 20px;
        box-sizing: border-box
    }

    #cw-lightbox img {
        max-width: min(90vw, 800px);
        max-height: calc(100vh - 100px);
        border-radius: 12px;
        object-fit: contain;
        box-shadow: 0 8px 40px rgba(0, 0, 0, .5);
        display: block
    }

    #cw-lightbox-close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: rgba(255, 255, 255, .2);
        border: none;
        color: white;
        font-size: 20px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
        backdrop-filter: blur(4px)
    }

    #cw-lightbox-close:hover {
        background: rgba(255, 255, 255, .35)
    }

    /* Edit mode indicator */
    #cw-edit-bar {
        display: none;
        background: #eff6ff;
        border-top: 1px solid #bfdbfe;
        padding: 6px 14px;
        flex-shrink: 0;
        position: relative
    }

    #cw-edit-bar span {
        font-size: 11px;
        color: #2563eb;
        font-weight: 500
    }

    #cw-edit-close {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 14px;
        color: #9ca3af;
        cursor: pointer
    }

    /* Read receipts */
    .cw-read-receipt {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        cursor: pointer;
        position: relative;
        margin-left: 3px;
        vertical-align: middle;
        transition: transform .15s;
    }

    .cw-read-receipt:hover {
        transform: scale(1.15);
    }

    .cw-check {
        font-size: 11px;
        font-weight: 700;
        line-height: 1
    }

    .cw-check.sent {
        color: #9ca3af
    }

    .cw-check.seen {
        color: #f97316
    }

    /* ===== Seen By Modal (WhatsApp-style) ===== */
    #cw-seen-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 200100;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
        backdrop-filter: blur(4px);
        animation: cwFadeIn .18s ease forwards;
    }

    #cw-seen-modal-overlay.active {
        display: flex;
    }

    @keyframes cwFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    #cw-seen-modal {
        background: white;
        border-radius: 20px;
        width: 320px;
        max-width: calc(100vw - 40px);
        max-height: min(480px, calc(100vh - 80px));
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 24px 64px rgba(0, 0, 0, .3);
        animation: cwSeenModalIn .22s cubic-bezier(.34, 1.56, .64, 1) forwards;
    }

    @keyframes cwSeenModalIn {
        from {
            transform: scale(.88) translateY(16px);
            opacity: 0;
        }

        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    #cw-seen-modal-header {
        background: linear-gradient(135deg, #f97316, #ea580c);
        padding: 16px 18px 14px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    #cw-seen-modal-header h4 {
        font-size: 15px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    #cw-seen-modal-close {
        background: rgba(255, 255, 255, .2);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: background .15s;
        flex-shrink: 0;
    }

    #cw-seen-modal-close:hover {
        background: rgba(255, 255, 255, .35);
    }

    #cw-seen-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 8px 0;
    }

    #cw-seen-modal-body::-webkit-scrollbar {
        width: 4px;
    }

    #cw-seen-modal-body::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px;
    }

    .cw-seen-section-title {
        font-size: 10px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .6px;
        padding: 8px 18px 4px;
    }

    .cw-seen-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 18px;
        transition: background .12s;
    }

    .cw-seen-row:hover {
        background: #f9fafb;
    }

    .cw-seen-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 14px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
    }

    .cw-seen-info {
        flex: 1;
        min-width: 0;
    }

    .cw-seen-name {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cw-seen-time {
        font-size: 10px;
        color: #9ca3af;
        margin-top: 1px;
    }

    .cw-seen-check-icon {
        font-size: 14px;
        color: #f97316;
        flex-shrink: 0;
    }

    #cw-seen-modal-empty {
        padding: 32px 18px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    #cw-seen-msg-preview {
        padding: 10px 18px;
        border-bottom: 1px solid #f3f4f6;
        flex-shrink: 0;
    }

    #cw-seen-msg-preview .preview-bubble {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        font-size: 12px;
        padding: 7px 12px;
        border-radius: 12px 12px 4px 12px;
        display: inline-block;
        max-width: 100%;
        word-break: break-word;
        line-height: 1.4;
    }

    #cw-seen-counter {
        background: rgba(255, 255, 255, .25);
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
    }

    @media(max-width:480px) {
        #cw-panel {
            width: calc(100vw - 24px);
            right: 12px;
            bottom: 76px
        }

        #cw-bubble {
            right: 12px;
            bottom: 68px
        }
    }

    /* Extra small viewport (zoom-out on small monitors) */
    @media(max-height:600px) {
        #cw-panel {
            max-height: calc(100vh - 100px);
            bottom: 76px
        }
    }

    /* Ensure bubble stays inside viewport */
    #cw-bubble {
        max-width: 56px;
        max-height: 56px;
    }

    /* ===== Tab navigation ===== */
    #cw-tabs {
        display: flex;
        gap: 4px;
        margin-top: 2px;
    }

    .cw-tab {
        flex: 1;
        padding: 5px 8px;
        border: none;
        border-radius: 8px;
        background: rgba(255, 255, 255, .15);
        color: rgba(255, 255, 255, .85);
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: background .15s;
    }

    .cw-tab:hover {
        background: rgba(255, 255, 255, .25);
        color: white;
    }

    .cw-tab.active {
        background: rgba(255, 255, 255, .3);
        color: white;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .15);
    }

    /* Online users view (inside panel) */
    #cw-online-view {
        flex: 1;
        overflow-y: auto;
        display: none;
        flex-direction: column;
        background: #f9fafb;
    }

    #cw-online-view.active {
        display: flex;
    }

    #cw-online-list {
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    #cw-online-list::-webkit-scrollbar {
        width: 3px;
    }

    #cw-online-list::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px;
    }

    position: fixed;
    bottom: 116px;
    right: 406px;
    z-index: 99999;
    width: 220px;
    max-height: min(580px, calc(100vh - 130px));
    background: rgba(255, 255, 255, .97);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .18),
    0 4px 16px rgba(0, 0, 0, .08);
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .5);
    animation: cwSlideIn .25s cubic-bezier(.34, 1.56, .64, 1) forwards;
    }

    #cw-online-header {
        background: linear-gradient(135deg, #f97316, #ea580c);
        padding: 12px 14px 10px;
        color: white;
        flex-shrink: 0;
    }

    #cw-online-header h4 {
        font-size: 13px;
        font-weight: 700;
        margin: 0 0 2px;
    }

    #cw-online-header p {
        font-size: 10px;
        margin: 0;
        opacity: .85;
    }

    #cw-online-list {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        background: #f9fafb;
    }

    #cw-online-list::-webkit-scrollbar {
        width: 3px;
    }

    #cw-online-list::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px;
    }

    .cw-online-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 12px;
        cursor: pointer;
        transition: background .15s;
        background: white;
        border: 1px solid #f3f4f6;
        position: relative;
    }

    .cw-online-item:hover {
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .cw-online-item.is-me {
        opacity: .65;
        cursor: default;
    }

    .cw-online-item.is-me:hover {
        background: white;
        border-color: #f3f4f6;
    }

    .cw-online-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
        position: relative;
    }

    .cw-online-dot-indicator {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 9px;
        height: 9px;
        background: #4ade80;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 0 4px #4ade80;
    }

    .cw-online-info {
        flex: 1;
        min-width: 0;
    }

    .cw-online-name {
        font-size: 12px;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cw-online-jab {
        font-size: 10px;
        color: #9ca3af;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
    }

    .cw-online-unread {
        background: #ef4444;
        color: white;
        font-size: 9px;
        font-weight: 700;
        border-radius: 999px;
        min-width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        flex-shrink: 0;
    }

    #cw-online-empty {
        padding: 24px 12px;
        text-align: center;
        color: #9ca3af;
        font-size: 12px;
    }

    /* Online/Offline dot indicator */
    .cw-online-dot-indicator.offline {
        background: #d1d5db;
        box-shadow: none;
    }

    /* Section header inside online list */
    .cw-online-section-hdr {
        font-size: 10px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .6px;
        padding: 8px 10px 4px;
    }

    /* Last seen label */
    .cw-online-lastseen {
        font-size: 10px;
        color: #9ca3af;
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cw-online-lastseen.online-now {
        color: #16a34a;
        font-weight: 600;
    }

    /* Dim offline users slightly */
    .cw-online-item.offline-user {
        opacity: .82;
    }

    /* Search box inside online tab */
    #cw-user-search-wrap {
        padding: 8px 8px 4px;
        flex-shrink: 0;
    }

    #cw-user-search {
        width: 100%;
        border: 1.5px solid #e5e7eb;
        border-radius: 10px;
        padding: 6px 10px;
        font-size: 12px;
        outline: none;
        background: #f9fafb;
        transition: border-color .15s;
        box-sizing: border-box;
    }

    #cw-user-search:focus {
        border-color: #f97316;
        background: white;
    }

    /* ===== DM Panel (floats over main chat panel) ===== */
    #cw-dm-panel {
        position: fixed;
        bottom: 116px;
        right: 24px;
        z-index: 100000;
        width: 370px;
        max-width: calc(100vw - 32px);
        max-height: min(560px, calc(100vh - 130px));
        background: rgba(255, 255, 255, .97);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .5);
        animation: cwSlideIn .25s cubic-bezier(.34, 1.56, .64, 1) forwards;
    }

    #cw-dm-header {
        background: linear-gradient(135deg, #f97316, #ea580c);
        padding: 11px 14px 10px;
        color: white;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #cw-dm-header-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
        background: rgba(255, 255, 255, .25);
    }

    #cw-dm-header-info {
        flex: 1;
        min-width: 0;
    }

    #cw-dm-header-name {
        font-size: 13px;
        font-weight: 700;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #cw-dm-header-sub {
        font-size: 10px;
        opacity: .85;
        margin: 0;
    }

    #cw-dm-back-btn {
        background: rgba(255, 255, 255, .2);
        border: none;
        color: white;
        width: 26px;
        height: 26px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 11px;
        transition: background .15s;
    }

    #cw-dm-back-btn:hover {
        background: rgba(255, 255, 255, .35);
    }

    #cw-dm-close-btn {
        background: rgba(255, 255, 255, .2);
        border: none;
        color: white;
        width: 26px;
        height: 26px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 11px;
    }

    #cw-dm-call-btn {
        background: rgba(255, 255, 255, .2);
        border: none;
        color: white;
        width: 26px;
        height: 26px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 11px;
        transition: background .15s;
    }

    #cw-dm-call-btn:hover {
        background: rgba(255, 255, 255, .35);
    }

    #cw-dm-messages {
        flex: 1;
        overflow-y: auto;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        scroll-behavior: smooth;
        background: #f9fafb;
    }

    #cw-dm-messages::-webkit-scrollbar {
        width: 4px;
    }

    #cw-dm-messages::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px;
    }

    #cw-dm-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 28px 12px;
        color: #9ca3af;
        font-size: 12px;
        gap: 8px;
        text-align: center;
    }

    .cw-dm-msg {
        display: flex;
        gap: 4px;
        align-items: flex-end;
    }

    .cw-dm-msg.own {
        flex-direction: row-reverse;
        justify-content: flex-start;
    }

    .cw-dm-bubble {
        background: white;
        border: 1px solid #e5e7eb;
        color: #1f2937;
        font-size: 13px;
        padding: 8px 12px;
        border-radius: 16px 16px 16px 4px;
        word-break: break-word;
        line-height: 1.45;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        min-width: 60px;
    }

    .cw-dm-msg.own .cw-dm-bubble {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        border-color: transparent;
        border-radius: 16px 16px 4px 16px;
    }

    .cw-dm-time {
        font-size: 9px;
        color: #9ca3af;
        margin-top: 3px;
        padding: 0 4px;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .cw-dm-msg.own .cw-dm-time {
        justify-content: flex-end;
    }

    .cw-dm-date-div {
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
        background: #f3f4f6;
        border-radius: 999px;
        padding: 2px 10px;
        align-self: center;
        margin: 3px 0;
    }

    #cw-dm-footer {
        padding: 8px 10px;
        background: white;
        border-top: 1px solid #f3f4f6;
        flex-shrink: 0;
    }

    #cw-dm-input-row {
        display: flex;
        gap: 6px;
        align-items: flex-end;
    }

    #cw-dm-input {
        flex: 1;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        padding: 7px 11px;
        font-size: 12px;
        resize: none;
        outline: none;
        max-height: 80px;
        min-height: 34px;
        line-height: 1.4;
        background: #f9fafb;
        transition: border-color .15s;
        font-family: inherit;
    }

    #cw-dm-input:focus {
        border-color: #f97316;
        background: white;
    }

    #cw-dm-send {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        flex-shrink: 0;
        background: linear-gradient(135deg, #f97316, #ea580c);
        border: none;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: transform .15s;
    }

    #cw-dm-send:hover {
        transform: scale(1.06);
    }

    #cw-dm-send:disabled {
        opacity: .45;
        cursor: default;
        transform: none;
    }

    #cw-dm-input {
        max-height: 120px;
    }

    /* DM Attach button */
    #cw-dm-attach-btn {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: 1.5px solid #e5e7eb;
        background: white;
        color: #6b7280;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 13px;
        transition: all .15s;
    }

    #cw-dm-attach-btn:hover {
        border-color: #f97316;
        color: #f97316;
        background: #fff7ed;
    }

    /* DM Attach preview bar */
    #cw-dm-attach-bar {
        display: none;
        padding: 6px 10px;
        background: #f9fafb;
        border-top: 1px solid #f3f4f6;
        flex-shrink: 0;
    }

    #cw-dm-attach-preview {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #374151;
    }

    #cw-dm-attach-thumb {
        max-height: 44px;
        max-width: 70px;
        border-radius: 6px;
        object-fit: cover;
        display: none;
    }

    #cw-dm-total-badge {
        background: #ef4444;
        color: white;
        font-size: 9px;
        font-weight: 700;
        border-radius: 999px;
        min-width: 16px;
        height: 16px;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0 3px;
    }

    @media(max-width:660px) {
        #cw-dm-panel {
            right: 12px;
            bottom: 76px;
            width: calc(100vw - 24px);
        }
    }

    /* ===== DM Enhanced Styles ===== */
    #cw-dm-reply-bar {
        display: none;
        background: #fff7ed;
        border-top: 1px solid #fed7aa;
        padding: 7px 12px 7px 14px;
        flex-shrink: 0;
        position: relative;
    }

    #cw-dm-reply-bar .dm-rp-name {
        font-size: 10px;
        font-weight: 700;
        color: #ea580c;
    }

    #cw-dm-reply-bar .dm-rp-text {
        font-size: 10px;
        color: #6b7280;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 270px;
    }

    #cw-dm-reply-close {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 13px;
        color: #9ca3af;
        cursor: pointer;
    }

    .cw-dm-reply-snippet {
        background: rgba(0, 0, 0, .07);
        border-left: 3px solid #f97316;
        border-radius: 5px;
        padding: 4px 8px;
        margin-bottom: 5px;
        font-size: 10px;
        color: #374151;
    }

    .cw-dm-reply-snippet strong {
        display: block;
        font-size: 10px;
        font-weight: 700;
        color: #f97316;
        margin-bottom: 1px;
    }

    .cw-dm-msg.own .cw-dm-reply-snippet {
        background: rgba(255, 255, 255, .22);
        border-color: rgba(255, 255, 255, .65);
        color: rgba(255, 255, 255, .9);
    }

    .cw-dm-msg.own .cw-dm-reply-snippet strong {
        color: white;
    }

    .cw-dm-receipt {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        cursor: pointer;
        position: relative;
        margin-left: 3px;
        vertical-align: middle;
    }

    .cw-dm-check {
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
    }

    .cw-dm-check.sent {
        color: #9ca3af;
    }

    .cw-dm-check.read {
        color: #f97316;
    }

    .cw-dm-receipt-tip {
        position: absolute;
        bottom: calc(100% + 5px);
        right: 0;
        background: #1f2937;
        color: white;
        border-radius: 7px;
        padding: 4px 9px;
        font-size: 10px;
        white-space: nowrap;
        box-shadow: 0 3px 12px rgba(0, 0, 0, .18);
        display: none;
        z-index: 210;
    }

    .cw-dm-receipt-tip::after {
        content: '';
        position: absolute;
        top: 100%;
        right: 8px;
        border: 4px solid transparent;
        border-top-color: #1f2937;
    }

    .cw-dm-receipt:hover .cw-dm-receipt-tip {
        display: block;
    }

    .cw-dm-reactions {
        display: flex;
        flex-wrap: wrap;
        gap: 3px;
        margin-top: 3px;
    }

    .cw-dm-react-pill {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        padding: 2px 7px;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 3px;
        transition: all .12s;
        line-height: 1;
    }

    .cw-dm-react-pill.mine {
        background: #fff7ed;
        border-color: #f97316;
    }

    .cw-dm-react-pill span {
        font-size: 10px;
        color: #6b7280;
        font-weight: 600;
    }

    .cw-dm-react-picker {
        position: absolute;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .14);
        padding: 5px 6px;
        display: flex;
        gap: 2px;
        z-index: 215;
        bottom: calc(100% + 5px);
        white-space: nowrap;
    }

    .cw-dm-msg.own .cw-dm-react-picker {
        right: 0;
    }

    .cw-dm-msg:not(.own) .cw-dm-react-picker {
        left: 0;
    }

    .cw-dm-pick-emoji {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        border-radius: 6px;
        padding: 3px 4px;
        transition: background .1s;
        line-height: 1;
    }

    .cw-dm-pick-emoji:hover {
        background: #f3f4f6;
    }

    .cw-dm-msg-wrap {
        position: relative;
        max-width: 75%;
        min-width: 0;
    }

    .cw-dm-actions {
        display: flex;
        align-items: flex-end;
        gap: 2px;
        opacity: 0;
        transition: opacity .2s;
        flex-shrink: 0;
    }

    .cw-dm-msg:hover .cw-dm-actions {
        opacity: 1;
    }

    .cw-dm-action-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: #9ca3af;
        font-size: 11px;
        padding: 3px 5px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        transition: all .12s;
    }

    .cw-dm-action-btn:hover {
        background: #f3f4f6;
        color: #f97316;
    }

    .cw-dm-bubble .dm-para+.dm-para {
        margin-top: 5px;
    }

    /* ===== Call System ===== */
    .cw-call-overlay {
        position: fixed;
        inset: 0;
        z-index: 100002;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, .78);
        backdrop-filter: blur(8px);
    }

    .cw-call-overlay.active {
        display: flex;
    }

    /* Active call overlay: stretch card ke full screen */
    #cw-call-active.active {
        display: flex;
        align-items: stretch;
        padding: 0;
        background: #0a0f1a;
        backdrop-filter: none;
    }

    .cw-call-card {
        background: linear-gradient(145deg, #1e293b, #0f172a);
        border-radius: 24px;
        padding: 32px 28px;
        width: 300px;
        max-width: calc(100vw - 32px);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 16px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, .55);
        border: 1px solid rgba(255, 255, 255, .08);
        animation: cwSlideIn .3s ease forwards;
    }

    .cw-call-avatar-wrap {
        position: relative;
        width: 84px;
        height: 84px;
    }

    .cw-call-avatar {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        font-size: 30px;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 1;
    }

    @keyframes cwRingPulse {

        0%,
        100% {
            transform: scale(1);
            opacity: .5;
        }

        50% {
            transform: scale(1.65);
            opacity: 0;
        }
    }

    .cw-call-ring1,
    .cw-call-ring2 {
        position: absolute;
        inset: -8px;
        border-radius: 50%;
        background: rgba(249, 115, 22, .35);
        animation: cwRingPulse 1.8s ease-out infinite;
    }

    .cw-call-ring2 {
        inset: -20px;
        animation-delay: .7s;
    }

    .cw-call-name {
        font-size: 18px;
        font-weight: 700;
        color: white;
    }

    .cw-call-status {
        font-size: 13px;
        color: rgba(255, 255, 255, .55);
    }

    .cw-call-duration {
        font-size: 15px;
        color: #4ade80;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .cw-call-actions {
        display: flex;
        gap: 18px;
        align-items: center;
        margin-top: 6px;
    }

    .cw-call-btn {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        transition: transform .15s, box-shadow .15s;
    }

    .cw-call-btn:hover {
        transform: scale(1.1);
    }

    .cw-call-btn.accept {
        background: #16a34a;
        box-shadow: 0 4px 16px rgba(22, 163, 74, .4);
    }

    .cw-call-btn.accept-video {
        background: #2563eb;
        box-shadow: 0 4px 16px rgba(37, 99, 235, .4);
    }

    .cw-call-btn.decline {
        background: #dc2626;
        box-shadow: 0 4px 16px rgba(220, 38, 38, .4);
    }

    .cw-call-btn.hangup {
        background: #dc2626;
        box-shadow: 0 4px 16px rgba(220, 38, 38, .4);
    }

    .cw-call-btn.ctrl {
        background: rgba(255, 255, 255, .14);
    }

    .cw-call-btn.ctrl.off {
        background: #dc2626;
    }

    /* Active call card — full screen */
    #cw-call-active .cw-call-card {
        flex: 1;
        width: 100%;
        max-width: 100%;
        height: 100%;
        max-height: 100%;
        padding: 0;
        overflow: hidden;
        border-radius: 0;
        display: flex;
        flex-direction: column;
        animation: none;
    }

    #cw-call-video-wrap {
        width: 100%;
        flex: 1;
        min-height: 0;
        background: #0a0f1a;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    #cw-call-remote-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }

    #cw-call-no-video {
        font-size: 70px;
        opacity: .25;
    }

    #cw-call-local-wrap {
        position: absolute;
        bottom: 16px;
        right: 16px;
        width: 160px;
        height: 120px;
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid rgba(255, 255, 255, .35);
        box-shadow: 0 4px 20px rgba(0, 0, 0, .4);
        display: none;
    }

    #cw-call-local-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scaleX(-1);
    }

    .cw-call-active-body {
        padding: 16px 20px 28px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
        background: linear-gradient(to top, rgba(10, 15, 26, .95) 0%, rgba(10, 15, 26, .6) 60%, transparent 100%);
        flex-shrink: 0;
    }

    /* Call button on user list */
    .cw-online-call-btn {
        background: rgba(249, 115, 22, .12);
        border: 1px solid rgba(249, 115, 22, .3);
        color: #f97316;
        width: 28px;
        height: 28px;
        border-radius: 7px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        flex-shrink: 0;
        transition: all .15s;
    }

    .cw-online-call-btn:hover {
        background: #f97316;
        border-color: #f97316;
        color: white;
        transform: scale(1.08);
    }

    .cw-online-call-btn.video {
        background: rgba(37, 99, 235, .1);
        border-color: rgba(37, 99, 235, .3);
        color: #2563eb;
    }

    .cw-online-call-btn.video:hover {
        background: #2563eb;
        border-color: #2563eb;
        color: white;
    }

    /* ===== Video Call Button in DM Header ===== */
    #cw-dm-video-btn {
        background: rgba(255, 255, 255, .2);
        border: none;
        color: white;
        width: 26px;
        height: 26px;
        border-radius: 8px;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 11px;
        transition: background .15s;
    }

    #cw-dm-video-btn:hover {
        background: rgba(255, 255, 255, .35);
    }

    /* ===== GPS Panel ===== */
    #cw-gps-panel {
        margin: 6px 8px 0;
        border-radius: 12px;
        overflow: hidden;
        flex-shrink: 0;
        transition: all .25s;
    }

    #cw-gps-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        background: linear-gradient(135deg, #1e40af, #1d4ed8);
        color: white;
        cursor: pointer;
        user-select: none;
    }

    #cw-gps-header-left {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 11px;
        font-weight: 700;
    }

    #cw-gps-header-left i {
        font-size: 12px;
        color: #93c5fd;
    }

    #cw-gps-header-right {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    #cw-gps-refresh-btn {
        background: rgba(255, 255, 255, .18);
        border: none;
        color: white;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        transition: background .15s;
    }

    #cw-gps-refresh-btn:hover {
        background: rgba(255, 255, 255, .3);
    }

    #cw-gps-toggle-icon {
        font-size: 9px;
        transition: transform .25s;
    }

    #cw-gps-toggle-icon.collapsed {
        transform: rotate(-180deg);
    }

    #cw-gps-body {
        background: linear-gradient(160deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
        border-top: none;
        border-radius: 0 0 12px 12px;
        overflow: hidden;
        transition: max-height .25s ease, padding .2s;
        max-height: 300px;
    }

    #cw-gps-body.collapsed {
        max-height: 0;
    }

    #cw-gps-content {
        padding: 8px 12px 10px;
    }

    #cw-gps-status {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 8px 0 4px;
        font-size: 11px;
        color: #6b7280;
    }

    #cw-gps-status.loading {
        color: #2563eb;
    }

    #cw-gps-status.success {
        color: #16a34a;
    }

    #cw-gps-status.error {
        color: #dc2626;
    }

    .cw-gps-rows {
        display: flex;
        flex-direction: column;
        gap: 5px;
        margin-top: 3px;
    }

    .cw-gps-row {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        font-size: 11px;
        color: #1e40af;
        line-height: 1.4;
    }

    .cw-gps-row-icon {
        font-size: 11px;
        color: #2563eb;
        flex-shrink: 0;
        margin-top: 1px;
        width: 14px;
        text-align: center;
    }

    .cw-gps-row-label {
        font-size: 10px;
        font-weight: 700;
        color: #3b82f6;
        text-transform: uppercase;
        letter-spacing: .4px;
        min-width: 52px;
        flex-shrink: 0;
    }

    .cw-gps-row-value {
        color: #1e3a8a;
        font-weight: 600;
        flex: 1;
        min-width: 0;
        word-break: break-word;
    }

    .cw-gps-coord-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .cw-gps-coord-chip {
        background: rgba(37, 99, 235, .1);
        border: 1px solid rgba(37, 99, 235, .2);
        border-radius: 6px;
        padding: 3px 8px;
        font-size: 10px;
        font-weight: 700;
        color: #1d4ed8;
        font-family: monospace;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    #cw-gps-detect-btn {
        margin-top: 8px;
        width: 100%;
        padding: 7px;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all .15s;
        box-shadow: 0 2px 8px rgba(37, 99, 235, .3);
    }

    #cw-gps-detect-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, .4);
    }

    #cw-gps-detect-btn:disabled {
        opacity: .6;
        cursor: default;
        transform: none;
    }

    #cw-gps-accuracy {
        font-size: 9px;
        color: #6b7280;
        text-align: center;
        margin-top: 3px;
    }

    /* ===== Call History Modal ===== */
    #cw-callhist-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .58);
        z-index: 200200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
        backdrop-filter: blur(5px);
    }

    #cw-callhist-overlay.active {
        display: flex;
    }

    #cw-callhist-modal {
        background: white;
        border-radius: 20px;
        width: 360px;
        max-width: calc(100vw - 32px);
        max-height: min(540px, calc(100vh - 60px));
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 28px 70px rgba(0, 0, 0, .32);
        animation: cwSeenModalIn .22s cubic-bezier(.34, 1.56, .64, 1) forwards;
    }

    #cw-callhist-header {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        padding: 16px 18px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    #cw-callhist-header h4 {
        font-size: 15px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 9px;
    }

    #cw-callhist-header-icon {
        width: 30px;
        height: 30px;
        border-radius: 9px;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        color: #93c5fd;
    }

    #cw-callhist-close {
        background: rgba(255, 255, 255, .15);
        border: none;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: background .15s;
    }

    #cw-callhist-close:hover {
        background: rgba(255, 255, 255, .28);
    }

    #cw-callhist-body {
        flex: 1;
        overflow-y: auto;
        padding: 6px 0;
    }

    #cw-callhist-body::-webkit-scrollbar {
        width: 4px;
    }

    #cw-callhist-body::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 2px;
    }

    #cw-callhist-loading {
        padding: 36px 18px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    #cw-callhist-empty {
        padding: 40px 18px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .cw-clog-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        transition: background .12s;
        cursor: pointer;
        border-bottom: 1px solid #f9fafb;
    }

    .cw-clog-row:hover {
        background: #f8fafc;
    }

    .cw-clog-row:last-child {
        border-bottom: none;
    }

    .cw-clog-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 15px;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        position: relative;
    }

    .cw-clog-type-badge {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 7px;
    }

    .cw-clog-type-badge.audio {
        background: #f97316;
    }

    .cw-clog-type-badge.video {
        background: #2563eb;
    }

    .cw-clog-info {
        flex: 1;
        min-width: 0;
    }

    .cw-clog-name {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cw-clog-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 2px;
    }

    .cw-clog-dir-icon {
        font-size: 10px;
    }

    .cw-clog-dir-icon.incoming {
        color: #16a34a;
    }

    .cw-clog-dir-icon.missed {
        color: #dc2626;
    }

    .cw-clog-dir-icon.outgoing {
        color: #6b7280;
    }

    .cw-clog-dir-icon.cancelled {
        color: #9ca3af;
    }

    .cw-clog-status {
        font-size: 11px;
        font-weight: 600;
    }

    .cw-clog-status.answered {
        color: #16a34a;
    }

    .cw-clog-status.missed {
        color: #dc2626;
    }

    .cw-clog-status.rejected {
        color: #ef4444;
    }

    .cw-clog-status.cancelled {
        color: #9ca3af;
    }

    .cw-clog-status.initiated {
        color: #6b7280;
    }

    .cw-clog-dur {
        font-size: 10px;
        color: #6b7280;
        margin-left: 2px;
    }

    .cw-clog-time {
        font-size: 10px;
        color: #9ca3af;
        flex-shrink: 0;
        text-align: right;
    }

    .cw-clog-actions {
        display: flex;
        gap: 5px;
        flex-shrink: 0;
    }

    .cw-clog-call-btn {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1.5px solid;
        background: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        transition: all .15s;
    }

    .cw-clog-call-btn.audio {
        border-color: rgba(249, 115, 22, .4);
        color: #f97316;
    }

    .cw-clog-call-btn.audio:hover {
        background: #f97316;
        color: white;
        border-color: #f97316;
    }

    .cw-clog-call-btn.video {
        border-color: rgba(37, 99, 235, .4);
        color: #2563eb;
    }

    .cw-clog-call-btn.video:hover {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    #cw-callhist-footer {
        padding: 10px 16px;
        border-top: 1px solid #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    #cw-callhist-load-more {
        background: none;
        border: 1.5px solid #e5e7eb;
        border-radius: 10px;
        padding: 6px 18px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
        transition: all .15s;
    }

    #cw-callhist-load-more:hover {
        border-color: #f97316;
        color: #f97316;
    }

    #cw-callhist-load-more:disabled {
        opacity: .45;
        cursor: default;
    }

    /* History button in Online tab */
    #cw-callhist-open-btn {
        background: rgba(255, 255, 255, .18);
        border: none;
        color: white;
        border-radius: 8px;
        padding: 4px 9px;
        font-size: 10px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: background .15s;
        flex-shrink: 0;
    }

    #cw-callhist-open-btn:hover {
        background: rgba(255, 255, 255, .3);
    }
</style>

<!-- Bubble -->
<button id="cw-bubble" onclick="cwToggle()" aria-label="Obrolan">
    <div id="cw-bubble-pulse"></div>
    <i class="fas fa-comment-dots" id="cw-bubble-icon" style="font-size:22px"></i>
    <span id="cw-badge"></span>
</button>

<!-- DM Panel (floating, opens when clicking a user) -->
<div id="cw-dm-panel">
    <div id="cw-dm-header">
        <button id="cw-dm-back-btn" onclick="cwCloseDM(true)"><i class="fas fa-arrow-left"></i></button>
        <div id="cw-dm-header-avatar">?</div>
        <div id="cw-dm-header-info">
            <div id="cw-dm-header-name">-</div>
            <div id="cw-dm-header-sub">Pesan Langsung</div>
        </div>
        <button id="cw-dm-call-btn" onclick="cwStartCallFromDM()" title="Telepon" style="display:none"><i
                class="fas fa-phone"></i></button>
        <button id="cw-dm-video-btn" onclick="cwStartVideoCallFromDM()" title="Video Call" style="display:none"><i
                class="fas fa-video"></i></button>
        <button id="cw-dm-close-btn" onclick="cwCloseDM(false)"><i class="fas fa-times"></i></button>
    </div>
    <div id="cw-dm-messages">
        <div id="cw-dm-empty">
            <i class="fas fa-comments" style="font-size:28px;color:#e5e7eb"></i>
            <span>Mulai percakapan!</span>
        </div>
    </div>
    <div id="cw-dm-footer">
        <div id="cw-dm-reply-bar">
            <div class="dm-rp-name" id="cw-dm-reply-name"></div>
            <div class="dm-rp-text" id="cw-dm-reply-text"></div>
            <button id="cw-dm-reply-close" onclick="cwDmCancelReply()"><i class="fas fa-times"></i></button>
        </div>
        <div id="cw-dm-attach-bar">
            <div id="cw-dm-attach-preview"></div>
        </div>
        <div id="cw-dm-input-row">
            <button id="cw-dm-attach-btn" onclick="document.getElementById('cw-dm-file-input').click()"
                title="Lampirkan foto/dokumen">
                <i class="fas fa-paperclip"></i>
            </button>
            <textarea id="cw-dm-input" placeholder="Ketik pesan..." rows="1" maxlength="1000"></textarea>
            <button id="cw-dm-send" onclick="cwSendDM()" disabled><i class="fas fa-paper-plane"></i></button>
        </div>
        <input type="file" id="cw-dm-file-input" style="display:none" multiple
            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
    </div>
</div>

<!-- Lightbox -->
<div id="cw-lightbox" onclick="cwCloseLightbox(event)">
    <button id="cw-lightbox-close" onclick="document.getElementById('cw-lightbox').style.display='none'" title="Tutup">
        <i class="fas fa-times"></i>
    </button>
    <img id="cw-lightbox-img" src="" alt="Preview" onclick="event.stopPropagation()">
</div>

<!-- ===== Call History Modal ===== -->
<div id="cw-callhist-overlay" onclick="cwCallHistClose(event)">
    <div id="cw-callhist-modal" onclick="event.stopPropagation()">
        <div id="cw-callhist-header">
            <h4>
                <div id="cw-callhist-header-icon"><i class="fas fa-phone-alt"></i></div>
                Riwayat Panggilan
            </h4>
            <button id="cw-callhist-close" onclick="cwCallHistForceClose()" title="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="cw-callhist-body">
            <div id="cw-callhist-loading">
                <i class="fas fa-spinner fa-spin" style="font-size:26px;color:#e5e7eb"></i>
                <span>Memuat riwayat...</span>
            </div>
        </div>
        <div id="cw-callhist-footer" style="display:none">
            <button id="cw-callhist-load-more" onclick="cwCallHistLoadMore()">Muat lebih banyak</button>
        </div>
    </div>
</div>

<!-- ===== Seen By Modal (WhatsApp-style) ===== -->
<div id="cw-seen-modal-overlay" onclick="cwSeenModalClose(event)">
    <div id="cw-seen-modal" onclick="event.stopPropagation()">
        <div id="cw-seen-modal-header">
            <h4>
                <i class="fas fa-eye"></i>
                Dilihat oleh
                <span id="cw-seen-counter">0</span>
            </h4>
            <button id="cw-seen-modal-close" onclick="cwSeenModalForceClose()" title="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="cw-seen-msg-preview"></div>
        <div id="cw-seen-modal-body"></div>
    </div>
</div>

<!-- ===== Call Overlays ===== -->
<!-- Incoming Call -->
<div class="cw-call-overlay" id="cw-call-incoming">
    <div class="cw-call-card">
        <div class="cw-call-avatar-wrap">
            <span class="cw-call-ring1"></span><span class="cw-call-ring2"></span>
            <div class="cw-call-avatar" id="cw-ci-avatar">?</div>
        </div>
        <div class="cw-call-name" id="cw-ci-name">-</div>
        <div class="cw-call-status">Panggilan masuk...</div>
        <div class="cw-call-actions">
            <button class="cw-call-btn decline" onclick="cwCallReject()" title="Tolak"><i
                    class="fas fa-phone-slash"></i></button>
            <button class="cw-call-btn accept" onclick="cwCallAccept('audio')" title="Angkat Audio"><i
                    class="fas fa-phone"></i></button>
            <button class="cw-call-btn accept-video" onclick="cwCallAccept('video')" title="Angkat Video"><i
                    class="fas fa-video"></i></button>
        </div>
    </div>
</div>
<!-- Outgoing Call -->
<div class="cw-call-overlay" id="cw-call-outgoing">
    <div class="cw-call-card">
        <div class="cw-call-avatar-wrap">
            <span class="cw-call-ring1"></span><span class="cw-call-ring2"></span>
            <div class="cw-call-avatar" id="cw-co-avatar">?</div>
        </div>
        <div class="cw-call-name" id="cw-co-name">-</div>
        <div class="cw-call-status" id="cw-co-status">Menghubungi...</div>
        <div class="cw-call-actions">
            <button class="cw-call-btn decline" onclick="cwCallCancel()" title="Batalkan"><i
                    class="fas fa-phone-slash"></i></button>
        </div>
    </div>
</div>
<!-- Active Call -->
<div class="cw-call-overlay" id="cw-call-active">
    <div class="cw-call-card">
        <div id="cw-call-video-wrap">
            <video id="cw-call-remote-video" autoplay playsinline muted></video>
            <audio id="cw-call-remote-audio" autoplay style="display:none"></audio>
            <div id="cw-call-no-video">🎙️</div>
            <div id="cw-call-local-wrap"><video id="cw-call-local-video" autoplay playsinline muted></video></div>
        </div>
        <div class="cw-call-active-body">
            <div class="cw-call-name" id="cw-ca-name">-</div>
            <div class="cw-call-duration" id="cw-ca-dur">00:00</div>
            <div class="cw-call-actions">
                <button class="cw-call-btn ctrl" id="cw-ca-mute" onclick="cwCallToggleMute()" title="Mute"><i
                        class="fas fa-microphone"></i></button>
                <button class="cw-call-btn ctrl" id="cw-ca-cam" onclick="cwCallToggleCam()" title="Kamera"><i
                        class="fas fa-video"></i></button>
                <button class="cw-call-btn hangup" onclick="cwCallHangup()" title="Tutup"><i
                        class="fas fa-phone-slash"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Chat Panel -->
<div id="cw-panel">
    <!-- Header -->
    <div id="cw-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <h3 id="cw-header-title"><i class="fas fa-comments" style="font-size:13px;margin-right:5px"></i>Obrolan</h3>
            <button onclick="cwToggle()"
                style="background:rgba(255,255,255,.2);border:none;color:white;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-times" style="font-size:12px"></i>
            </button>
        </div>
        <!-- Tabs -->
        <div id="cw-tabs">
            <button id="cw-tab-chat" class="cw-tab active" onclick="cwSwitchTab('chat')">
                <i class="fas fa-comments"></i> Umum
            </button>
            <button id="cw-tab-online" class="cw-tab" onclick="cwSwitchTab('online')">
                <i class="fas fa-users"></i> Online
                <span id="cw-online-count"
                    style="background:rgba(255,255,255,.25);border-radius:999px;padding:0 5px;font-size:10px;margin-left:2px">1</span>
                <span id="cw-dm-total-badge"></span>
            </button>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
            <p style="font-size:10px;margin:0;opacity:.8"><span id="cw-online-dot"></span><span
                    id="cw-online-count-label">1</span> pengguna online</p>
            <button id="cw-callhist-open-btn" onclick="cwOpenCallHistory()" title="Riwayat Panggilan">
                <i class="fas fa-history"></i> Riwayat
            </button>
        </div>
    </div>

    <!-- Messages (Chat View) -->
    <div id="cw-messages">
        <div id="cw-empty">
            <i class="fas fa-comments" style="font-size:32px;color:#e5e7eb"></i>
            <span>Belum ada pesan. Mulai obrolan!</span>
        </div>
    </div>

    <!-- Online Users View -->
    <div id="cw-online-view">
        <!-- GPS Location Panel -->
        <div id="cw-gps-panel">
            <div id="cw-gps-header" onclick="cwGpsToggle()">
                <div id="cw-gps-header-left">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Lokasi Saya</span>
                </div>
                <div id="cw-gps-header-right">
                    <button id="cw-gps-refresh-btn" onclick="event.stopPropagation();cwDetectLocation(true)"
                        title="Refresh Lokasi">
                        <i class="fas fa-sync-alt" id="cw-gps-refresh-icon"></i>
                    </button>
                    <i class="fas fa-chevron-up" id="cw-gps-toggle-icon"></i>
                </div>
            </div>
            <div id="cw-gps-body">
                <div id="cw-gps-content">
                    <div id="cw-gps-status">
                        <i class="fas fa-location-arrow"></i>
                        <span id="cw-gps-status-text">Klik untuk deteksi lokasi Anda</span>
                    </div>
                    <div class="cw-gps-rows" id="cw-gps-rows" style="display:none">
                        <div class="cw-gps-row">
                            <i class="fas fa-home cw-gps-row-icon"></i>
                            <span class="cw-gps-row-label">Alamat</span>
                            <span class="cw-gps-row-value" id="cw-gps-address">-</span>
                        </div>
                        <div class="cw-gps-row">
                            <i class="fas fa-city cw-gps-row-icon"></i>
                            <span class="cw-gps-row-label">Kota</span>
                            <span class="cw-gps-row-value" id="cw-gps-city">-</span>
                        </div>
                        <div class="cw-gps-row">
                            <i class="fas fa-flag cw-gps-row-icon"></i>
                            <span class="cw-gps-row-label">Negara</span>
                            <span class="cw-gps-row-value" id="cw-gps-country">-</span>
                        </div>
                        <div class="cw-gps-coord-row">
                            <div class="cw-gps-coord-chip">
                                <i class="fas fa-arrows-alt-v" style="font-size:9px"></i>
                                Lat: <span id="cw-gps-lat">-</span>
                            </div>
                            <div class="cw-gps-coord-chip">
                                <i class="fas fa-arrows-alt-h" style="font-size:9px"></i>
                                Lng: <span id="cw-gps-lng">-</span>
                            </div>
                        </div>
                        <div id="cw-gps-accuracy"></div>
                    </div>
                    <button id="cw-gps-detect-btn" onclick="cwDetectLocation(false)">
                        <i class="fas fa-crosshairs"></i>
                        Deteksi Lokasi Saya
                    </button>
                </div>
            </div>
        </div>
        <div id="cw-user-search-wrap" style="padding-top:8px">
            <input type="text" id="cw-user-search" placeholder="Cari pengguna..." oninput="cwFilterUsers(this.value)">
        </div>
        <div id="cw-online-list">
            <div id="cw-online-empty" style="padding:24px 12px;text-align:center;color:#9ca3af;font-size:12px">
                <i class="fas fa-spinner fa-spin"
                    style="font-size:28px;color:#e5e7eb;display:block;margin-bottom:8px"></i>
                Memuat pengguna...
            </div>
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
        <div id="cw-attach-preview"></div>
    </div>

    <!-- Footer -->
    <div id="cw-footer">
        <div id="cw-input-row">
            <button class="cw-foot-btn" onclick="document.getElementById('cw-file-input').click()"
                title="Lampirkan file/foto">
                <i class="fas fa-paperclip"></i>
            </button>
            <textarea id="cw-input" placeholder="Ketik pesan..." rows="1" maxlength="1000"></textarea>
            <button id="cw-send" onclick="cwSend()" disabled>
                <i class="fas fa-paper-plane" style="font-size:13px"></i>
            </button>
        </div>
        <div id="cw-char">0 / 1000</div>
        <input type="file" id="cw-file-input" style="display:none" multiple
            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
    </div>
</div>

<script>
    (function () {
        const ENDPOINT = <?php echo json_encode($_cw_endpoint); ?>;
        const WEB_BASE = <?php echo json_encode($_cw_web_base); ?>;
        const MY_ID = <?php echo $_cw_user_id; ?>;
        const MY_NAMA = <?php echo json_encode($_cw_nama); ?>;
        const IS_ADMIN = <?php echo $_cw_is_admin ? 'true' : 'false'; ?>;

        let panelOpen = false;
        let lastId = 0;
        let unread = 0;
        let pollTimer = null;
        let lastDateStr = '';
        let replyToId = 0;
        let editMsgId = 0;
        let pendingFiles = []; // array of { blob, name, type ('image'|'file'), dataUrl }
        let openDropdown = null;
        let _sessionExpired = false; // flag: stop all polls jika session sudah habis

        // ---- Offline token (di-embed dari PHP, valid tanpa session aktif) ----
        const CW_OFFLINE_TOKEN = <?php echo json_encode($_cw_offline_token); ?>;
        const CW_UID = <?php echo $_cw_user_id; ?>;

        // ---- Fungsi go_offline — kirim dengan token, tidak butuh session ----
        function cwSendOffline() {
            const fd = new FormData();
            fd.append('action', 'go_offline');
            fd.append('cw_uid', CW_UID);
            fd.append('cw_token', CW_OFFLINE_TOKEN);
            // Gunakan keepalive fetch agar tetap terkirim walau halaman sudah berganti
            fetch(ENDPOINT, { method: 'POST', body: fd, keepalive: true }).catch(() => { });
        }

        // ---- Intercept semua link logout di halaman ini ----
        // Dipanggil SEBELUM navigasi ke logout.php, sehingga session masih valid
        function cwInterceptLogout() {
            document.querySelectorAll('a[href*="logout"]').forEach(function (link) {
                if (link._cwLogoutIntercepted) return; // cegah double-bind
                link._cwLogoutIntercepted = true;
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const dest = link.href;
                    cwSendOffline();
                    // Navigasi setelah minimal 150ms agar request sempat terkirim
                    setTimeout(function () { window.location.href = dest; }, 150);
                });
            });
        }

        // Intercept saat halaman load dan update jika DOM berubah
        document.addEventListener('DOMContentLoaded', cwInterceptLogout);
        cwInterceptLogout(); // juga jalankan sekarang (jika DOM sudah siap)

        // Fallback: sendBeacon saat tab ditutup (bukan saat logout — untuk kasus user tutup browser)
        window.addEventListener('pagehide', function () {
            if (navigator.sendBeacon) {
                const fd = new FormData();
                fd.append('action', 'go_offline');
                fd.append('cw_uid', CW_UID);
                fd.append('cw_token', CW_OFFLINE_TOKEN);
                navigator.sendBeacon(ENDPOINT, fd);
            }
        });

        const panel = document.getElementById('cw-panel');
        const msgs = document.getElementById('cw-messages');
        const empty = document.getElementById('cw-empty');
        const badge = document.getElementById('cw-badge');
        const input = document.getElementById('cw-input');
        const sendBtn = document.getElementById('cw-send');
        const charEl = document.getElementById('cw-char');
        const onlineEl = document.getElementById('cw-online-count');
        const bubIcon = document.getElementById('cw-bubble-icon');
        const replyBar = document.getElementById('cw-reply-bar');
        const editBar = document.getElementById('cw-edit-bar');
        const attachBar = document.getElementById('cw-attach-bar');
        const fileInput = document.getElementById('cw-file-input');
        const lbImg = document.getElementById('cw-lightbox-img');
        const lightbox = document.getElementById('cw-lightbox');

        // ---- Avatar color ----
        function avatarColor(uid) {
            const c = ['#f97316', '#8b5cf6', '#06b6d4', '#10b981', '#f43f5e', '#3b82f6', '#ec4899', '#84cc16'];
            return c[Math.abs(uid) % c.length];
        }

        function formatDate(d) {
            const today = new Date();
            const tStr = `${today.getDate()} ${['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][today.getMonth()]} ${today.getFullYear()}`;
            return d === tStr ? 'Hari ini' : d;
        }

        function fileIcon(name) {
            const ext = (name || '').split('.').pop().toLowerCase();
            if (['pdf'].includes(ext)) return '<i class="fas fa-file-pdf" style="color:#ef4444"></i>';
            if (['doc', 'docx'].includes(ext)) return '<i class="fas fa-file-word" style="color:#3b82f6"></i>';
            if (['xls', 'xlsx'].includes(ext)) return '<i class="fas fa-file-excel" style="color:#16a34a"></i>';
            if (['ppt', 'pptx'].includes(ext)) return '<i class="fas fa-file-powerpoint" style="color:#ea580c"></i>';
            if (['zip', 'rar'].includes(ext)) return '<i class="fas fa-file-archive" style="color:#8b5cf6"></i>';
            return '<i class="fas fa-file" style="color:#6b7280"></i>';
        }
        function fileSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        // ---- Build message HTML ----
        function buildMsgEl(m) {
            const isOwn = parseInt(m.user_id) === MY_ID;
            const isAdmin = m.is_admin;
            const color = avatarColor(parseInt(m.user_id));
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
                const rText = rs.has_file ? `<i class="fas fa-paperclip"></i> ${rs.file}` : rs.text;
                replyHtml = `<div class="cw-reply-snippet"><strong>${rs.name}</strong>${rText}</div>`;
            }

            // Attachment
            let attachHtml = '';
            if (m.attachment_path && m.attachment_type === 'image') {
                const url = WEB_BASE + '/' + m.attachment_path;
                attachHtml = `<img class="cw-img-attach" src="${url}" alt="${m.attachment_name || ''}" onclick="cwOpenLightbox('${url}')">`;
            } else if (m.attachment_path && m.attachment_type === 'file') {
                const url = WEB_BASE + '/' + m.attachment_path;
                const ico = fileIcon(m.attachment_name);
                const sz = fileSize(parseInt(m.attachment_size || 0));
                attachHtml = `<a class="cw-file-attach" href="${url}" target="_blank" download>
                <span class="cw-file-icon">${ico}</span>
                <div>
                    <div style="font-weight:600">${m.attachment_name || 'File'}</div>
                    <div style="font-size:10px;opacity:.7">${sz}</div>
                </div>
                <i class="fas fa-download" style="margin-left:auto;font-size:12px;opacity:.6"></i>
            </a>`;
            }

            // Message text (empty if only attachment) - convert newlines to <br> for paragraph support
            const safeMsg = m.message ? m.message.replace(/\n/g, '<br>') : '';
            const msgText = m.message ? `<div class="cw-text">${replyHtml}${safeMsg}${attachHtml}</div>` : `<div class="cw-text">${replyHtml}${attachHtml}</div>`;

            // Edit tag
            const editedTag = m.is_edited ? '<span class="cw-edited-tag"><i class="fas fa-pencil-alt" style="font-size:8px"></i> diedit</span>' : '';

            // Read receipt (only for own messages)
            let readHtml = '';
            if (isOwn) {
                const cnt = parseInt(m.read_count || 0);
                const readers = m.read_by || [];
                const isSeen = cnt > 0;
                const msgPreview = m.message ? m.message.substring(0, 80) + (m.message.length > 80 ? '…' : '') : (m.attachment_name || '📎 Lampiran');
                readHtml = `<span class="cw-read-receipt" data-rid="${m.id}" data-seen="${isSeen ? 1 : 0}" data-preview="${msgPreview.replace(/"/g, '&quot;').replace(/\n/g, ' ')}" onclick="cwSeenModalOpen(${m.id},event)" title="${isSeen ? 'Klik untuk lihat siapa yang membaca' : 'Belum dilihat'}">
                <span class="cw-check ${isSeen ? 'seen' : 'sent'}">&#10003;</span>
                <span class="cw-check ${isSeen ? 'seen' : 'sent'}">&#10003;</span>
            </span>`;
            }

            // Dropdown
            const canEdit = isOwn;
            const canDelete = isOwn || IS_ADMIN;
            let ddHtml = '';
            if (canEdit || canDelete) {
                const editBtn = canEdit ? `<button onclick="cwEditMsg(${m.id},this)"><i class="fas fa-pencil-alt"></i> Edit</button>` : '';
                const deleteBtn = canDelete ? `<button class="danger" onclick="cwDeleteMsg(${m.id},this)"><i class="fas fa-trash"></i> Hapus</button>` : '';
                ddHtml = `<div class="cw-dropdown" id="cwd-${m.id}">
                <button onclick="cwReplyTo(${m.id},'${(m.nama_display || '').replace(/'/g, '\\\'')}',${'`' + m.message.replace(/`/g, '\\`') + '`'})"><i class="fas fa-reply"></i> Balas</button>
                ${editBtn}${deleteBtn}
            </div>`;
            } else {
                ddHtml = `<div class="cw-dropdown" id="cwd-${m.id}">
                <button onclick="cwReplyTo(${m.id},'${(m.nama_display || '').replace(/'/g, '\\\'')}',${'`' + m.message.replace(/`/g, '\\`') + '`'})"><i class="fas fa-reply"></i> Balas</button>
            </div>`;
            }

            const actionBtn = `<button class="cw-action-btn" onclick="cwToggleDropdown(${m.id},event)"><i class="fas fa-ellipsis-h"></i></button>${ddHtml}`;

            const sender = isOwn ? 'Kamu' : m.nama_display;

            // IT badge for admin/super_admin
            const itBadge = isAdmin ? '<span class="cw-it-badge">IT</span>' : '';

            // Jabatan badge with smart color
            let jabatanBadge = '';
            if (m.jabatan) {
                const jab = m.jabatan.toUpperCase();
                let bg, fg;
                if (jab.includes('IT') || jab.includes('TEKNOLOGI') || jab.includes('SYSTEM') || jab.includes('NETWORK')) {
                    bg = '#fff7ed'; fg = '#ea580c'; // orange tint
                } else if (jab.includes('HRD') || jab.includes('HR ') || jab.includes('SDM') || jab.includes('HUMAN')) {
                    bg = '#f0fdf4'; fg = '#16a34a'; // green
                } else if (jab.includes('GA') || jab.includes('GENERAL') || jab.includes('ASSET')) {
                    bg = '#eff6ff'; fg = '#2563eb'; // blue
                } else if (jab.includes('FINANCE') || jab.includes('AKUNTANSI') || jab.includes('KEUANGAN')) {
                    bg = '#fdf4ff'; fg = '#9333ea'; // purple
                } else if (jab.includes('MARKETING') || jab.includes('SALES')) {
                    bg = '#fff1f2'; fg = '#e11d48'; // rose
                } else if (jab.includes('MANAGER') || jab.includes('KEPALA') || jab.includes('SUPERVISOR')) {
                    bg = '#fefce8'; fg = '#ca8a04'; // amber
                } else if (jab.includes('SECURITY') || jab.includes('SATPAM')) {
                    bg = '#f1f5f9'; fg = '#475569'; // slate
                } else {
                    bg = '#f3f4f6'; fg = '#4b5563'; // gray
                }
                jabatanBadge = `<span class="cw-jabatan-badge" style="background:${bg};color:${fg};border:1px solid ${fg}22">${m.jabatan}</span>`;
            }

            const html = `
            ${divHtml}
            <div class="cw-msg${isOwn ? ' own' : ''}" data-mid="${m.id}">
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
        window.cwToggle = function () {
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
                .then(r => r.json())
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
            if (_sessionExpired) return;
            fetch(`${ENDPOINT}?action=get&after_id=${lastId}`)
                .then(r => r.json())
                .then(data => {
                    // Deteksi session expired
                    if (data.error === 'Unauthorized') {
                        _sessionExpired = true;
                        stopPoll();
                        return;
                    }
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
                                // Someone else's message was fetched - means WE just read it.
                                // Also update existing own messages' receipt counters via a separate reads check.
                            }
                        });
                    }
                    // Refresh receipts on own visible messages
                    cwRefreshReceipts();
                }).catch(() => { });
        }

        function startPoll() { stopPoll(); pollTimer = setInterval(poll, 3000); }
        function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

        // ---- Notification Sound (Web Audio API - no external file needed) ----
        let _audioCtx = null;

        // Suara Tab Umum: dua nada naik (ping ringan)
        function cwPlayNotif() {
            try {
                if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const ctx = _audioCtx;
                const now = ctx.currentTime;
                // Note 1: 880 Hz (A5) - short ping
                const o1 = ctx.createOscillator();
                const g1 = ctx.createGain();
                o1.type = 'sine'; o1.frequency.setValueAtTime(880, now);
                g1.gain.setValueAtTime(0.0, now);
                g1.gain.linearRampToValueAtTime(0.28, now + 0.01);
                g1.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);
                o1.connect(g1); g1.connect(ctx.destination);
                o1.start(now); o1.stop(now + 0.22);
                // Note 2: 1100 Hz - higher follow-up
                const o2 = ctx.createOscillator();
                const g2 = ctx.createGain();
                o2.type = 'sine'; o2.frequency.setValueAtTime(1100, now + 0.12);
                g2.gain.setValueAtTime(0.0, now + 0.12);
                g2.gain.linearRampToValueAtTime(0.22, now + 0.13);
                g2.gain.exponentialRampToValueAtTime(0.0001, now + 0.38);
                o2.connect(g2); g2.connect(ctx.destination);
                o2.start(now + 0.12); o2.stop(now + 0.38);
            } catch (e) { }
        }

        // Suara Tab Online/DM: tiga nada lembut turun-naik (lebih personal & intim)
        function cwPlayNotifDM() {
            try {
                if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const ctx = _audioCtx;
                const now = ctx.currentTime;
                // Note 1: 660 Hz (E5) - nada dasar lembut
                const o1 = ctx.createOscillator();
                const g1 = ctx.createGain();
                o1.type = 'triangle'; o1.frequency.setValueAtTime(660, now);
                g1.gain.setValueAtTime(0.0, now);
                g1.gain.linearRampToValueAtTime(0.20, now + 0.015);
                g1.gain.exponentialRampToValueAtTime(0.0001, now + 0.18);
                o1.connect(g1); g1.connect(ctx.destination);
                o1.start(now); o1.stop(now + 0.18);
                // Note 2: 528 Hz (turun) - nada tengah
                const o2 = ctx.createOscillator();
                const g2 = ctx.createGain();
                o2.type = 'triangle'; o2.frequency.setValueAtTime(528, now + 0.10);
                g2.gain.setValueAtTime(0.0, now + 0.10);
                g2.gain.linearRampToValueAtTime(0.18, now + 0.115);
                g2.gain.exponentialRampToValueAtTime(0.0001, now + 0.28);
                o2.connect(g2); g2.connect(ctx.destination);
                o2.start(now + 0.10); o2.stop(now + 0.28);
                // Note 3: 784 Hz (naik lagi) - penutup manis
                const o3 = ctx.createOscillator();
                const g3 = ctx.createGain();
                o3.type = 'triangle'; o3.frequency.setValueAtTime(784, now + 0.22);
                g3.gain.setValueAtTime(0.0, now + 0.22);
                g3.gain.linearRampToValueAtTime(0.16, now + 0.235);
                g3.gain.exponentialRampToValueAtTime(0.0001, now + 0.42);
                o3.connect(g3); g3.connect(ctx.destination);
                o3.start(now + 0.22); o3.stop(now + 0.42);
            } catch (e) { }
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
                        el.setAttribute('data-seen', isSeen ? 1 : 0);
                        el.title = isSeen ? 'Klik untuk lihat siapa yang membaca' : 'Belum dilihat';
                        const checks = el.querySelectorAll('.cw-check');
                        checks.forEach(c => {
                            c.className = 'cw-check ' + (isSeen ? 'seen' : 'sent');
                        });
                    });
                }).catch(() => { });
        }

        // Refresh receipts every 5 seconds when panel is open
        setInterval(() => { if (panelOpen) cwRefreshReceipts(); }, 5000);

        // ===== SEEN BY MODAL (WhatsApp-style) =====
        let _seenModalMsgId = 0;

        window.cwSeenModalOpen = function (msgId, e) {
            if (e) e.stopPropagation();
            _seenModalMsgId = msgId;

            const overlay = document.getElementById('cw-seen-modal-overlay');
            const body = document.getElementById('cw-seen-modal-body');
            const counter = document.getElementById('cw-seen-counter');
            const previewEl = document.getElementById('cw-seen-msg-preview');

            // Show message preview
            const receiptEl = msgs.querySelector(`.cw-read-receipt[data-rid="${msgId}"]`);
            const msgEl = receiptEl ? receiptEl.closest('[data-mid]') : null;
            const rawPreview = receiptEl ? receiptEl.getAttribute('data-preview') : '';
            if (rawPreview) {
                previewEl.innerHTML = `<div class="preview-bubble">${rawPreview.replace(/</g, '&lt;')}</div>`;
                previewEl.style.display = 'block';
            } else {
                previewEl.style.display = 'none';
            }

            // Show loading state
            body.innerHTML = `<div id="cw-seen-modal-empty"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:#e5e7eb"></i><span>Memuat...</span></div>`;
            counter.textContent = '...';
            overlay.classList.add('active');

            // Fetch readers
            fetch(`${ENDPOINT}?action=get_reads&ids=${msgId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.reads || !data.reads[msgId]) {
                        body.innerHTML = `<div id="cw-seen-modal-empty"><i class="fas fa-eye-slash" style="font-size:28px;color:#e5e7eb"></i><span>Belum ada yang melihat pesan ini</span></div>`;
                        counter.textContent = '0';
                        return;
                    }
                    const info = data.reads[msgId];
                    const readers = info.readers || [];
                    counter.textContent = readers.length;

                    if (readers.length === 0) {
                        body.innerHTML = `<div id="cw-seen-modal-empty"><i class="fas fa-eye-slash" style="font-size:28px;color:#e5e7eb"></i><span>Belum ada yang melihat pesan ini</span></div>`;
                        return;
                    }

                    const avatarColors = ['#f97316', '#8b5cf6', '#06b6d4', '#10b981', '#f43f5e', '#3b82f6', '#ec4899', '#84cc16'];
                    function getColor(uid) { return avatarColors[Math.abs(uid) % avatarColors.length]; }

                    let html = `<div class="cw-seen-section-title"><i class="fas fa-eye" style="margin-right:5px;color:#f97316"></i>Dibaca oleh ${readers.length} orang</div>`;
                    readers.forEach(r => {
                        const initial = (r.name || '?').charAt(0).toUpperCase();
                        const color = getColor(r.user_id || 0);
                        const timeLabel = r.read_at ? `<div class="cw-seen-time"><i class="fas fa-clock" style="margin-right:3px;font-size:9px"></i>${r.read_at}</div>` : '';
                        html += `<div class="cw-seen-row">
                            <div class="cw-seen-avatar" style="background:${color}">${initial}</div>
                            <div class="cw-seen-info">
                                <div class="cw-seen-name">${r.name || 'Pengguna'}</div>
                                ${timeLabel}
                            </div>
                            <i class="fas fa-check-double cw-seen-check-icon"></i>
                        </div>`;
                    });
                    body.innerHTML = html;
                }).catch(() => {
                    body.innerHTML = `<div id="cw-seen-modal-empty"><i class="fas fa-exclamation-circle" style="font-size:24px;color:#f97316"></i><span>Gagal memuat data</span></div>`;
                    counter.textContent = '?';
                });
        };

        window.cwSeenModalClose = function (e) {
            // Called from X button (no arg) OR from overlay onclick (has arg)
            // When called from overlay onclick, only close if clicking the overlay itself (not the modal card)
            if (e && e.target !== document.getElementById('cw-seen-modal-overlay')) return;
            const overlay = document.getElementById('cw-seen-modal-overlay');
            overlay.classList.remove('active');
            _seenModalMsgId = 0;
        };

        // Dedicated close for the X button (always closes)
        window.cwSeenModalForceClose = function () {
            document.getElementById('cw-seen-modal-overlay').classList.remove('active');
            _seenModalMsgId = 0;
        };

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('cw-seen-modal-overlay').classList.contains('active')) {
                document.getElementById('cw-seen-modal-overlay').classList.remove('active');
            }
        });

        // Background poll for unread badge
        setInterval(() => { if (!panelOpen && !_sessionExpired) poll(); }, 8000);
        // Presence ping every 30s (well within 90s server window, even on high-latency hosting)
        // Jika server balas Unauthorized → session expired → hentikan ping
        setInterval(() => {
            if (_sessionExpired) return;
            fetch(`${ENDPOINT}?action=online`)
                .then(r => r.json())
                .then(data => {
                    if (data.error === 'Unauthorized') {
                        // Session habis — hentikan semua poll dan ping
                        _sessionExpired = true;
                        stopPoll();
                    }
                })
                .catch(() => { });
        }, 30000);

        // ---- Dropdown ----
        window.cwToggleDropdown = function (mid, e) {
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
        document.addEventListener('click', () => { if (openDropdown) { openDropdown.style.display = 'none'; openDropdown = null; } });

        // ---- Reply ----
        window.cwReplyTo = function (id, name, text) {
            if (openDropdown) { openDropdown.style.display = 'none'; openDropdown = null; }
            replyToId = id;
            editMsgId = 0;
            editBar.style.display = 'none';
            document.getElementById('cw-reply-name').textContent = 'Membalas ' + name;
            document.getElementById('cw-reply-text').innerHTML = text ? text.replace(/</g, '&lt;') : '<i class="fas fa-paperclip"></i> Lampiran';
            replyBar.style.display = 'block';
            input.focus();
        };
        document.getElementById('cw-reply-close').onclick = function () {
            replyToId = 0; replyBar.style.display = 'none';
        };

        // ---- Edit ----
        window.cwEditMsg = function (id, btn) {
            if (openDropdown) { openDropdown.style.display = 'none'; openDropdown = null; }
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
        document.getElementById('cw-edit-close').onclick = function () {
            editMsgId = 0; editBar.style.display = 'none'; input.value = ''; charEl.textContent = '0 / 1000'; sendBtn.disabled = true; autoResize();
        };

        // ---- Delete ----
        window.cwDeleteMsg = function (id, btn) {
            if (openDropdown) { openDropdown.style.display = 'none'; openDropdown = null; }
            if (!confirm('Hapus pesan ini?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_msg'); fd.append('id', id);
            fetch(ENDPOINT, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const el = document.querySelector(`[data-mid="${id}"]`);
                        if (el) el.remove();
                    }
                }).catch(() => { });
        };

        // ---- Lightbox ----
        window.cwOpenLightbox = function (url) {
            lbImg.src = url;
            lightbox.style.display = 'flex';
        };

        // ---- File Attachment ----
        fileInput.addEventListener('change', function () {
            const files = Array.from(this.files);
            if (!files.length) return;
            let pending = files.length;
            files.forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                if (isImg) {
                    cwCompressImage(file, (blob, dataUrl) => {
                        pendingFiles.push({ blob, name: file.name, type: 'image', dataUrl });
                        if (--pending === 0) showAttachPreview();
                    });
                } else {
                    pendingFiles.push({ blob: file, name: file.name, type: 'file', dataUrl: null });
                    if (--pending === 0) showAttachPreview();
                }
            });
            this.value = '';
        });

        function showAttachPreview() {
            if (!pendingFiles.length) { attachBar.style.display = 'none'; return; }
            attachBar.style.display = 'block';
            const preview = document.getElementById('cw-attach-preview');
            preview.innerHTML = pendingFiles.map((f, i) => {
                const thumbHtml = f.type === 'image' && f.dataUrl
                    ? `<img src="${f.dataUrl}" class="cw-attach-chip-thumb">`
                    : `<i class="fas fa-file" style="color:#f97316;font-size:18px;flex-shrink:0"></i>`;
                return `<div class="cw-attach-chip">${thumbHtml}<span class="cw-attach-chip-name">${f.name}</span><button onclick="cwRemoveAttach(${i})" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:10px;padding:0;flex-shrink:0"><i class="fas fa-times"></i></button></div>`;
            }).join('') + `<button class="cw-attach-clear-all" onclick="cwClearAttach()" title="Hapus semua"><i class="fas fa-times-circle"></i></button>`;
            updateSendBtn();
        }

        window.cwRemoveAttach = function (i) {
            pendingFiles.splice(i, 1);
            showAttachPreview();
        };

        window.cwClearAttach = function () {
            pendingFiles = [];
            attachBar.style.display = 'none';
            document.getElementById('cw-attach-preview').innerHTML = '';
            updateSendBtn();
        };

        // Client-side image compression
        function cwCompressImage(file, callback) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    const MAX = 1280;
                    let w = img.width, h = img.height;
                    if (w > MAX || h > MAX) {
                        if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                        else { w = Math.round(w * MAX / h); h = MAX; }
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
        window.cwSend = function () {
            const msg = input.value.trim();
            if (!msg && pendingFiles.length === 0) return;
            if (msg.length > 1000) return;
            sendBtn.disabled = true;

            if (editMsgId > 0) {
                // Edit mode
                const fd = new FormData();
                fd.append('action', 'edit'); fd.append('id', editMsgId); fd.append('message', msg);
                fetch(ENDPOINT, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const el = document.querySelector(`[data-mid="${editMsgId}"] .cw-text`);
                            if (el) {
                                el.childNodes.forEach(n => { if (n.nodeType === 3) n.textContent = msg; });
                                const et = document.querySelector(`[data-mid="${editMsgId}"] .cw-edited-tag`);
                                if (et) et.innerHTML = '<i class="fas fa-pencil-alt" style="font-size:8px"></i> diedit';
                                else {
                                    const t = document.querySelector(`[data-mid="${editMsgId}"] .cw-time`);
                                    if (t) t.insertAdjacentHTML('beforeend', '<span class="cw-edited-tag"><i class="fas fa-pencil-alt" style="font-size:8px"></i> diedit</span>');
                                }
                            }
                            document.getElementById('cw-edit-close').click();
                        }
                        updateSendBtn();
                    }).catch(() => { sendBtn.disabled = false; });
                return;
            }

            // Capture state then clear UI
            const capturedMsg = msg;
            const capturedReplyId = replyToId;
            const capturedFiles = [...pendingFiles];
            input.value = ''; charEl.textContent = '0 / 1000'; autoResize();
            if (replyToId > 0) { replyToId = 0; replyBar.style.display = 'none'; }
            cwClearAttach();

            // Build queue: text+first file together, rest as file-only messages
            const queue = [];
            if (capturedFiles.length === 0) {
                queue.push({ message: capturedMsg, file: null });
            } else {
                capturedFiles.forEach((f, i) => queue.push({ message: i === 0 ? capturedMsg : '', file: f }));
            }

            function sendNext(idx) {
                if (idx >= queue.length) { sendBtn.disabled = false; poll(); return; }
                const item = queue[idx];
                const fd = new FormData();
                fd.append('action', 'send');
                fd.append('message', item.message || '');
                if (idx === 0 && capturedReplyId > 0) fd.append('reply_to_id', capturedReplyId);
                if (item.file) fd.append('attachment', item.file.blob, item.file.name);
                fetch(ENDPOINT, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(() => sendNext(idx + 1))
                    .catch(() => sendNext(idx + 1));
            }
            sendNext(0);
        };

        function updateSendBtn() {
            sendBtn.disabled = input.value.trim().length === 0 && pendingFiles.length === 0;
        }

        function autoResize() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 100) + 'px';
        }

        input.addEventListener('input', function () {
            charEl.textContent = `${input.value.length} / 1000`;
            updateSendBtn();
            autoResize();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.shiftKey) {
                // Shift+Enter = kirim pesan
                e.preventDefault();
                if (!sendBtn.disabled) cwSend();
            }
            // Enter biasa = baris baru (biarkan default textarea)
        });

        // ===== ONLINE USERS PANEL + DIRECT MESSAGE (DM) SYSTEM =====
        // ============================================================

        const onlineView = document.getElementById('cw-online-view');
        const onlineList = document.getElementById('cw-online-list');
        const onlineCountEl = document.getElementById('cw-online-count');
        const onlineCountLbl = document.getElementById('cw-online-count-label');
        const dmTotalBadge = document.getElementById('cw-dm-total-badge');
        const dmPanel = document.getElementById('cw-dm-panel');
        const dmMsgs = document.getElementById('cw-dm-messages');
        const dmInput = document.getElementById('cw-dm-input');
        const dmSendBtn = document.getElementById('cw-dm-send');
        const dmHeaderAvatar = document.getElementById('cw-dm-header-avatar');
        const dmHeaderName = document.getElementById('cw-dm-header-name');
        const tabChat = document.getElementById('cw-tab-chat');
        const tabOnline = document.getElementById('cw-tab-online');

        let currentTab = 'chat';
        let dmWithUserId = 0;
        let dmWithName = '';
        let dmLastId = 0;
        let dmPollTimer = null;
        let onlinePollTimer = null;
        let dmLastDateStr = '';

        // ---- Avatar color ----
        function cwAvatarColor2(uid) {
            const c = ['#f97316', '#8b5cf6', '#06b6d4', '#10b981', '#f43f5e', '#3b82f6', '#ec4899', '#84cc16'];
            return c[Math.abs(uid) % c.length];
        }

        // ---- Switch tab ----
        window.cwSwitchTab = function (tab) {
            currentTab = tab;
            if (tab === 'chat') {
                msgs.style.display = 'flex';
                onlineView.classList.remove('active');
                tabChat.classList.add('active');
                tabOnline.classList.remove('active');
                // show footer bars back
                document.getElementById('cw-footer').style.display = 'block';
            } else {
                msgs.style.display = 'none';
                onlineView.classList.add('active');
                tabChat.classList.remove('active');
                tabOnline.classList.add('active');
                // hide footer in online view
                document.getElementById('cw-footer').style.display = 'none';
                document.getElementById('cw-edit-bar').style.display = 'none';
                document.getElementById('cw-reply-bar').style.display = 'none';
                document.getElementById('cw-attach-bar').style.display = 'none';
                cwLoadOnlineUsers();
            }
        };

        // ---- All users store ----
        let allUsersCache = [];

        // ---- Load all registered users (Facebook-style) ----
        function cwLoadOnlineUsers() {
            fetch(`${ENDPOINT}?action=get_all_users`)
                .then(r => r.json())
                .then(data => {
                    if (!data.users) return;
                    allUsersCache = data.users;
                    renderOnlineList(data.users);
                    updateDmTotalBadge(data.users);
                    const cnt = data.online_count || 0;
                    if (onlineCountEl) onlineCountEl.textContent = cnt;
                    if (onlineCountLbl) onlineCountLbl.textContent = cnt;
                }).catch(() => { });
        }

        // ---- Filter users by search ----
        window.cwFilterUsers = function (q) {
            const filtered = q.trim() === ''
                ? allUsersCache
                : allUsersCache.filter(u => u.nama.toLowerCase().includes(q.toLowerCase()) || (u.jabatan || '').toLowerCase().includes(q.toLowerCase()));
            renderOnlineList(filtered, true);
        };

        function renderOnlineList(users, isFiltered) {
            if (users.length === 0) {
                onlineList.innerHTML = '<div style="padding:24px 12px;text-align:center;color:#9ca3af;font-size:12px"><i class="fas fa-user-slash" style="font-size:28px;color:#e5e7eb;display:block;margin-bottom:8px"></i>Tidak ada pengguna ditemukan</div>';
                return;
            }

            // Split online / offline
            const online = users.filter(u => u.is_online);
            const offline = users.filter(u => !u.is_online);

            function buildItem(u) {
                const color = cwAvatarColor2(u.user_id);
                const initial = (u.nama || '?').charAt(0).toUpperCase();
                const itBadge = u.is_admin ? '<span style="background:#f97316;color:white;font-size:8px;font-weight:700;padding:1px 5px;border-radius:999px;margin-left:3px">IT</span>' : '';
                const unread = u.unread_dm > 0 ? `<span class="cw-online-unread">${u.unread_dm}</span>` : '';
                const meLabel = u.is_me ? ' <span style="color:#bbb;font-weight:400;font-size:10px">(Anda)</span>' : '';
                const dotCls = u.is_online ? '' : ' offline';
                const itemCls = u.is_me ? ' is-me' : (u.is_online ? '' : ' offline-user');
                const click = (u.is_me) ? '' : `onclick="cwOpenDM(${u.user_id},'${u.nama.replace(/'/g, "\\'")}','${(u.jabatan || '').replace(/'/g, "\\'")}',${u.is_admin},${u.is_online})"`;
                const title = u.is_me ? '(Anda)' : 'Klik untuk kirim pesan';

                // Last seen / online now label
                let seenHtml;
                if (u.is_me) {
                    seenHtml = `<div class="cw-online-lastseen online-now">Online sekarang</div>`;
                } else if (u.is_online) {
                    seenHtml = `<div class="cw-online-lastseen online-now"><i class="fas fa-circle" style="font-size:6px;margin-right:3px"></i>Online sekarang</div>`;
                } else {
                    seenHtml = `<div class="cw-online-lastseen">${u.last_seen_label || 'Belum pernah online'}</div>`;
                }

                const callBtn = (!u.is_me && u.is_online)
                    ? `<div style="display:flex;gap:3px;flex-shrink:0">
                        <button class="cw-online-call-btn" onclick="event.stopPropagation();cwStartCall(${u.user_id},'${u.nama.replace(/'/g, "\\'")}\'audio')" title="Telepon ${u.nama}"><i class="fas fa-phone"></i></button>
                        <button class="cw-online-call-btn video" onclick="event.stopPropagation();cwStartCall(${u.user_id},'${u.nama.replace(/'/g, "\\'")}\'video')" title="Video Call ${u.nama}"><i class="fas fa-video"></i></button>
                      </div>`
                    : '';
                return `<div class="cw-online-item${itemCls}" ${click} title="${title}">
                    <div class="cw-online-avatar" style="background:${color}">${initial}<span class="cw-online-dot-indicator${dotCls}"></span></div>
                    <div class="cw-online-info">
                        <div class="cw-online-name">${u.nama}${itBadge}${meLabel}</div>
                        <div class="cw-online-jab">${u.jabatan || 'Pengguna'}</div>
                        ${seenHtml}
                    </div>
                    ${unread}
                    ${callBtn}
                </div>`;
            }

            let html = '';

            if (online.length > 0) {
                html += `<div class="cw-online-section-hdr"><i class="fas fa-circle" style="color:#16a34a;font-size:7px;margin-right:4px"></i>Online — ${online.length}</div>`;
                online.forEach(u => { html += buildItem(u); });
            }

            if (offline.length > 0) {
                html += `<div class="cw-online-section-hdr" style="margin-top:${online.length > 0 ? '6px' : '0'}"><i class="fas fa-circle" style="color:#d1d5db;font-size:7px;margin-right:4px"></i>Semua Pengguna — ${offline.length}</div>`;
                offline.forEach(u => { html += buildItem(u); });
            }

            onlineList.innerHTML = html;
        }

        function updateDmTotalBadge(users) {
            const total = users.reduce((sum, u) => sum + (u.unread_dm || 0), 0);
            if (total > 0) {
                dmTotalBadge.textContent = total > 99 ? '99+' : total;
                dmTotalBadge.style.display = 'flex';
            } else {
                dmTotalBadge.style.display = 'none';
            }
        }

        // ---- Open DM ----
        window.cwOpenDM = function (userId, nama, jabatan, isAdmin, isOnline) {
            dmWithUserId = userId;
            dmWithName = nama;
            dmLastId = 0;
            dmLastDateStr = '';
            dmReplyToId = 0;
            document.getElementById('cw-dm-reply-bar').style.display = 'none';
            cwDmClosePicker();

            const color = cwAvatarColor2(userId);
            dmHeaderAvatar.textContent = nama.charAt(0).toUpperCase();
            dmHeaderAvatar.style.background = color;
            dmHeaderName.textContent = nama;
            document.getElementById('cw-dm-header-sub').textContent = jabatan || 'Pesan Langsung';

            dmMsgs.innerHTML = '<div id="cw-dm-empty" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:28px 12px;color:#9ca3af;font-size:12px;gap:8px"><i class="fas fa-comments" style="font-size:28px;color:#e5e7eb"></i><span>Mulai percakapan!</span></div>';
            dmInput.value = '';
            dmSendBtn.disabled = true;
            cwDmAutoResize();

            // Reset DM attachment state when opening new DM
            dmPendingFiles = [];
            const dmAttBar = document.getElementById('cw-dm-attach-bar');
            if (dmAttBar) dmAttBar.style.display = 'none';
            const dmAttPreview = document.getElementById('cw-dm-attach-preview');
            if (dmAttPreview) dmAttPreview.innerHTML = '';

            // Show/hide call & video buttons based on online status
            const dmCallBtn = document.getElementById('cw-dm-call-btn');
            const dmVideoBtn = document.getElementById('cw-dm-video-btn');
            if (dmCallBtn) dmCallBtn.style.display = isOnline ? 'flex' : 'none';
            if (dmVideoBtn) dmVideoBtn.style.display = isOnline ? 'flex' : 'none';

            dmPanel.style.display = 'flex';
            cwLoadDM(false);
            cwStartDMPoll();
        };

        // ---- Close DM ----
        window.cwCloseDM = function (backToOnline) {
            cwStopDMPoll();
            dmPanel.style.display = 'none';
            dmWithUserId = 0;
            dmReplyToId = 0;
            document.getElementById('cw-dm-reply-bar').style.display = 'none';
            cwDmClosePicker();
            if (backToOnline) cwSwitchTab('online');
        };

        // Tracker unread DM total terakhir (untuk deteksi pesan baru di background)
        let _lastDmTotalUnread = 0;
        let dmReplyToId = 0;
        let _dmActivePicker = null;

        // ---- Format DM text (paragraph support) ----
        function formatDMText(text) {
            if (!text) return '';
            const paras = text.split(/\n\n+/);
            if (paras.length > 1) {
                return paras.map(p => `<div class="dm-para">${p.replace(/\n/g, '<br>')}</div>`).join('');
            }
            return text.replace(/\n/g, '<br>');
        }

        // ---- Load DM messages ----
        function cwLoadDM(isIncremental) {
            const afterParam = isIncremental ? `&after_id=${dmLastId}` : '';
            fetch(`${ENDPOINT}?action=get_dm&with_user_id=${dmWithUserId}${afterParam}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.messages) return;
                    if (data.messages.length > 0) {
                        const wasBottom = dmMsgs.scrollHeight - dmMsgs.scrollTop - dmMsgs.clientHeight < 60;
                        // Hitung pesan baru dari orang lain (bukan pesan sendiri)
                        const newFromOthers = isIncremental
                            ? data.messages.filter(m => !m.is_own).length
                            : 0;
                        if (newFromOthers > 0) {
                            // Mainkan suara notifikasi DM (berbeda dari tab Umum)
                            cwPlayNotifDM();
                        }
                        data.messages.forEach(m => appendDMMsg(m));
                        dmLastId = data.last_id;
                        if (!isIncremental || wasBottom || data.messages.some(m => m.is_own))
                            dmMsgs.scrollTop = dmMsgs.scrollHeight;
                    }
                    cwLoadOnlineBackground();
                }).catch(() => { });
        }

        function appendDMMsg(m) {
            const emp = document.getElementById('cw-dm-empty');
            if (emp) emp.remove();

            if (m.date_display && m.date_display !== dmLastDateStr) {
                dmLastDateStr = m.date_display;
                const div = document.createElement('div');
                div.className = 'cw-dm-date-div';
                const today = new Date();
                const tStr = `${today.getDate()} ${['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][today.getMonth()]} ${today.getFullYear()}`;
                div.textContent = m.date_display === tStr ? 'Hari ini' : m.date_display;
                dmMsgs.appendChild(div);
            }

            const row = document.createElement('div');
            row.className = 'cw-dm-msg' + (m.is_own ? ' own' : '');
            row.dataset.dmid = m.id;

            // Reply snippet
            let replyHtml = '';
            if (m.reply_snippet) {
                const rs = m.reply_snippet;
                const rName = rs.is_mine ? 'Kamu' : rs.name;
                const rText = rs.has_file ? `<i class="fas fa-paperclip"></i> ${rs.file}` : rs.text;
                replyHtml = `<div class="cw-dm-reply-snippet"><strong>${rName}</strong>${rText}</div>`;
            }

            // Attachment
            let attachHtml = '';
            if (m.attachment_path && m.attachment_type === 'image') {
                const url = WEB_BASE + '/' + m.attachment_path;
                attachHtml = `<img src="${url}" onclick="cwOpenLightbox('${url}')" style="max-width:100%;max-height:160px;border-radius:8px;display:block;margin-top:4px;cursor:pointer">`;
            } else if (m.attachment_path) {
                const url = WEB_BASE + '/' + m.attachment_path;
                attachHtml = `<a href="${url}" target="_blank" download style="display:flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;color:inherit;opacity:.8"><i class='fas fa-paperclip'></i> ${m.attachment_name}</a>`;
            }

            const safeMsg = formatDMText(m.message);

            // Read receipt (own messages only)
            let receiptHtml = '';
            if (m.is_own) {
                const isRead = m.is_read;
                const readTime = m.read_at_display || '';
                const tipText = isRead ? `Dibaca${readTime ? ' pukul ' + readTime : ''}` : 'Terkirim';
                receiptHtml = `<span class="cw-dm-receipt" data-drid="${m.id}">
                    <div class="cw-dm-receipt-tip">${tipText}</div>
                    <span class="cw-dm-check ${isRead ? 'read' : 'sent'}">&#10003;</span>
                    <span class="cw-dm-check ${isRead ? 'read' : 'sent'}">&#10003;</span>
                </span>`;
            }

            // Reactions
            let reactionsHtml = '';
            if (m.reactions && m.reactions.length > 0) {
                reactionsHtml = '<div class="cw-dm-reactions">' +
                    m.reactions.map(r =>
                        `<button class="cw-dm-react-pill${r.mine ? ' mine' : ''}" onclick="cwDmReact(${m.id},'${r.emoji}')">${r.emoji}<span>${r.count}</span></button>`
                    ).join('') + '</div>';
            }

            // Emoji picker
            const emojis = ['👍', '❤️', '😂', '😮', '😢', '👏', '🔥', '✅'];
            const pickerHtml = `<div class="cw-dm-react-picker" id="cwdmrp-${m.id}" style="display:none">${emojis.map(e => `<button class="cw-dm-pick-emoji" onclick="cwDmReact(${m.id},'${e}');cwDmClosePicker()">${e}</button>`).join('')
                }</div>`;

            // Action buttons
            const safeNameForJs = m.is_own
                ? 'Kamu'
                : (m.from_nama || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            const safeMsgForJs = (m.message || '').replace(/'/g, "\\'").replace(/\n/g, ' ').substring(0, 60);
            const actionBtns = `<div class="cw-dm-actions">
                <button class="cw-dm-action-btn" title="Balas" onclick="cwDmReplyTo(${m.id},'${safeNameForJs}','${safeMsgForJs}')">
                    <i class="fas fa-reply"></i>
                </button>
                <button class="cw-dm-action-btn" title="Reaksi" onclick="cwDmTogglePicker(${m.id},event)">
                    <i class="far fa-smile"></i>
                </button>
            </div>`;

            row.innerHTML = m.is_own
                ? `<div class="cw-dm-msg-wrap">${pickerHtml}
                    <div class="cw-dm-bubble">${replyHtml}${safeMsg}${attachHtml}</div>
                    ${reactionsHtml}
                    <div class="cw-dm-time">${m.time_display}${receiptHtml}</div>
                  </div>${actionBtns}`
                : `<div class="cw-dm-msg-wrap">${pickerHtml}
                    <div class="cw-dm-bubble">${replyHtml}${safeMsg}${attachHtml}</div>
                    ${reactionsHtml}
                    <div class="cw-dm-time">${m.time_display}</div>
                  </div>${actionBtns}`;

            dmMsgs.appendChild(row);
        }

        // ---- DM File Attachment ----
        let dmPendingFiles = []; // array of { blob, name, type ('image'|'file'), dataUrl }

        const dmFileInput = document.getElementById('cw-dm-file-input');
        dmFileInput.addEventListener('change', function () {
            const files = Array.from(this.files);
            if (!files.length) return;
            let pending = files.length;
            files.forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                const isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
                if (isImg) {
                    cwCompressImage(file, (blob, dataUrl) => {
                        dmPendingFiles.push({ blob, name: file.name, type: 'image', dataUrl });
                        if (--pending === 0) cwDmShowAttachPreview();
                    });
                } else {
                    dmPendingFiles.push({ blob: file, name: file.name, type: 'file', dataUrl: null });
                    if (--pending === 0) cwDmShowAttachPreview();
                }
            });
            this.value = '';
        });

        function cwDmShowAttachPreview() {
            if (!dmPendingFiles.length) { document.getElementById('cw-dm-attach-bar').style.display = 'none'; return; }
            document.getElementById('cw-dm-attach-bar').style.display = 'block';
            const preview = document.getElementById('cw-dm-attach-preview');
            preview.innerHTML = dmPendingFiles.map((f, i) => {
                const thumbHtml = f.type === 'image' && f.dataUrl
                    ? `<img src="${f.dataUrl}" class="cw-attach-chip-thumb">`
                    : `<i class="fas fa-file" style="color:#f97316;font-size:18px;flex-shrink:0"></i>`;
                return `<div class="cw-attach-chip">${thumbHtml}<span class="cw-attach-chip-name">${f.name}</span><button onclick="cwDmRemoveAttach(${i})" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:10px;padding:0;flex-shrink:0"><i class="fas fa-times"></i></button></div>`;
            }).join('') + `<button class="cw-attach-clear-all" onclick="cwDmClearAttach()" title="Hapus semua"><i class="fas fa-times-circle"></i></button>`;
            cwDmUpdateSendBtn();
        }

        window.cwDmRemoveAttach = function (i) {
            dmPendingFiles.splice(i, 1);
            cwDmShowAttachPreview();
        };

        window.cwDmClearAttach = function () {
            dmPendingFiles = [];
            document.getElementById('cw-dm-attach-bar').style.display = 'none';
            document.getElementById('cw-dm-attach-preview').innerHTML = '';
            cwDmUpdateSendBtn();
        };

        function cwDmUpdateSendBtn() {
            dmSendBtn.disabled = dmInput.value.trim().length === 0 && dmPendingFiles.length === 0;
        }

        // ---- Send DM ----
        window.cwSendDM = function () {
            const msg = dmInput.value.trim();
            if (!msg && dmPendingFiles.length === 0) return;
            if (dmWithUserId === 0) return;
            dmSendBtn.disabled = true;

            // Capture state then clear UI
            const capturedMsg = msg;
            const capturedReplyId = dmReplyToId;
            const capturedFiles = [...dmPendingFiles];
            dmInput.value = ''; cwDmAutoResize();
            if (dmReplyToId > 0) cwDmCancelReply();
            cwDmClearAttach();

            const queue = [];
            if (capturedFiles.length === 0) {
                queue.push({ message: capturedMsg, file: null });
            } else {
                capturedFiles.forEach((f, i) => queue.push({ message: i === 0 ? capturedMsg : '', file: f }));
            }

            function sendNextDM(idx) {
                if (idx >= queue.length) { dmSendBtn.disabled = false; cwLoadDM(true); return; }
                const item = queue[idx];
                const fd = new FormData();
                fd.append('action', 'send_dm');
                fd.append('to_user_id', dmWithUserId);
                fd.append('message', item.message || '');
                if (idx === 0 && capturedReplyId > 0) fd.append('reply_to_id', capturedReplyId);
                if (item.file) fd.append('attachment', item.file.blob, item.file.name);
                fetch(ENDPOINT, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(() => sendNextDM(idx + 1))
                    .catch(() => sendNextDM(idx + 1));
            }
            sendNextDM(0);
        };

        // ---- DM Reply ----
        window.cwDmReplyTo = function (id, name, text) {
            dmReplyToId = id;
            document.getElementById('cw-dm-reply-name').textContent = 'Membalas ' + name;
            document.getElementById('cw-dm-reply-text').textContent = text || '📎 Lampiran';
            document.getElementById('cw-dm-reply-bar').style.display = 'block';
            cwDmClosePicker();
            dmInput.focus();
        };
        window.cwDmCancelReply = function () {
            dmReplyToId = 0;
            document.getElementById('cw-dm-reply-bar').style.display = 'none';
        };

        // ---- Emoji Reactions ----
        window.cwDmTogglePicker = function (id, e) {
            e.stopPropagation();
            const picker = document.getElementById('cwdmrp-' + id);
            if (!picker) return;
            if (_dmActivePicker && _dmActivePicker !== picker) { _dmActivePicker.style.display = 'none'; }
            const isOpen = picker.style.display === 'flex';
            picker.style.display = isOpen ? 'none' : 'flex';
            _dmActivePicker = isOpen ? null : picker;
        };
        window.cwDmClosePicker = function () {
            if (_dmActivePicker) { _dmActivePicker.style.display = 'none'; _dmActivePicker = null; }
        };
        document.addEventListener('click', () => cwDmClosePicker());

        window.cwDmReact = function (dmId, emoji) {
            const fd = new FormData();
            fd.append('action', 'react_dm');
            fd.append('dm_id', dmId);
            fd.append('emoji', emoji);
            fetch(ENDPOINT, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(() => {
                    // Re-load current DM to refresh reactions
                    const orig = dmLastId;
                    dmLastId = 0;
                    dmLastDateStr = '';
                    dmMsgs.innerHTML = '';
                    cwLoadDM(false);
                    setTimeout(() => { dmMsgs.scrollTop = dmMsgs.scrollHeight; }, 300);
                }).catch(() => { });
        };

        // ---- Refresh DM Read Receipts ----
        function cwRefreshDMReceipts() {
            const receipts = dmMsgs.querySelectorAll('.cw-dm-receipt[data-drid]');
            if (!receipts.length) return;
            // Only query un-read ones (class 'sent')
            const unreadIds = Array.from(receipts)
                .filter(el => el.querySelector('.cw-dm-check.sent'))
                .map(el => el.getAttribute('data-drid')).filter(Boolean);
            if (!unreadIds.length) return;
            fetch(`${ENDPOINT}?action=get_dm_receipts&ids=${unreadIds.join(',')}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.receipts) return;
                    receipts.forEach(el => {
                        const mid = el.getAttribute('data-drid');
                        const info = data.receipts[mid];
                        if (!info || !info.is_read) return;
                        const checks = el.querySelectorAll('.cw-dm-check');
                        checks.forEach(c => c.className = 'cw-dm-check read');
                        const tip = el.querySelector('.cw-dm-receipt-tip');
                        if (tip) tip.textContent = `Dibaca${info.read_at_display ? ' pukul ' + info.read_at_display : ''}`;
                    });
                }).catch(() => { });
        }

        function cwStartDMPoll() {
            cwStopDMPoll();
            dmPollTimer = setInterval(() => {
                if (dmWithUserId > 0) { cwLoadDM(true); cwRefreshDMReceipts(); }
            }, 3000);
        }
        function cwStopDMPoll() {
            if (dmPollTimer) { clearInterval(dmPollTimer); dmPollTimer = null; }
        }

        function cwLoadOnlineBackground() {
            fetch(`${ENDPOINT}?action=get_all_users`)
                .then(r => r.json())
                .then(data => {
                    if (!data.users) return;
                    allUsersCache = data.users;

                    // Hitung total unread DM baru
                    const newTotal = data.users.reduce((sum, u) => sum + (u.unread_dm || 0), 0);
                    // Jika DM panel sedang tidak terbuka & total unread bertambah = ada pesan baru
                    if (dmWithUserId === 0 && newTotal > _lastDmTotalUnread) {
                        cwPlayNotifDM();
                    }
                    _lastDmTotalUnread = newTotal;

                    updateDmTotalBadge(data.users);
                    const cnt = data.online_count || 0;
                    if (onlineCountEl) onlineCountEl.textContent = cnt;
                    if (onlineCountLbl) onlineCountLbl.textContent = cnt;
                    if (currentTab === 'online') {
                        const q = document.getElementById('cw-user-search');
                        const qVal = q ? q.value.trim() : '';
                        renderOnlineList(qVal ? data.users.filter(u => u.nama.toLowerCase().includes(qVal.toLowerCase()) || (u.jabatan || '').toLowerCase().includes(qVal.toLowerCase())) : data.users);
                    }
                }).catch(() => { });
        }

        // Poll online every 10s when panel open
        onlinePollTimer = setInterval(() => {
            if (!_sessionExpired && panelOpen) cwLoadOnlineBackground();
        }, 10000);

        dmInput.addEventListener('input', function () {
            cwDmUpdateSendBtn();
            cwDmAutoResize();
        });
        dmInput.addEventListener('keydown', function (e) {
            // Shift+Enter = kirim, Enter biasa = baris baru
            if (e.key === 'Enter' && e.shiftKey) { e.preventDefault(); if (!dmSendBtn.disabled) cwSendDM(); }
        });
        function cwDmAutoResize() {
            dmInput.style.height = 'auto';
            dmInput.style.height = Math.min(dmInput.scrollHeight, 80) + 'px';
        }


        // ===== WebRTC CALL SYSTEM =====
        let _callState = 'idle'; // idle | outgoing | incoming | active
        let _callPeer = null;
        let _callLocalStream = null;
        let _callPeerId = 0;
        let _callPeerName = '';
        let _callType = 'audio';
        let _callMuted = false;
        let _callCamOff = false;
        let _callDurationTimer = null;
        let _callDurationSec = 0;
        let _callIncomingData = null;
        let _callQueuedIce = [];
        let _callSignalLastId = 0;
        let _callRingTimer = null;
        let _ringAudioCtx = null;
        let _ringNodes = [];
        let _audioCtxUnlocked = false;

        const ICE_CFG = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun.cloudflare.com:3478' }
            ]
        };

        // ===== PRE-UNLOCK AudioContext pada interaksi user pertama =====
        function cwUnlockAudioCtx() {
            if (_audioCtxUnlocked) return;
            try {
                if (!_ringAudioCtx) _ringAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const ctx = _ringAudioCtx;
                if (ctx.state === 'suspended') {
                    ctx.resume().then(() => { _audioCtxUnlocked = true; }).catch(() => { });
                } else {
                    _audioCtxUnlocked = true;
                }
                // Silent buffer trick untuk unlock
                const buf = ctx.createBuffer(1, 1, 22050);
                const src = ctx.createBufferSource();
                src.buffer = buf;
                src.connect(ctx.destination);
                src.start(0);
            } catch (e) { }
        }
        // Unlock AudioContext pada klik/keydown pertama
        ['click', 'keydown', 'touchstart', 'mousedown'].forEach(ev => {
            document.addEventListener(ev, cwUnlockAudioCtx, { once: false, passive: true });
        });

        // ===== NADA DERING PEMANGGIL — gaya WhatsApp (brr-brr ringan berulang) =====
        function cwPlayRingOutgoing() {
            cwStopRing();
            cwUnlockAudioCtx();
            try {
                if (!_ringAudioCtx) _ringAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const ctx = _ringAudioCtx;
                const doRing = function () {
                    function waDialTone() {
                        const now = ctx.currentTime;
                        // WhatsApp outgoing: dua "brr" pendek (400Hz + 450Hz chord), jeda 3 detik
                        // sedikit vibrato untuk karakter "brr" WA
                        [0, 0.45].forEach(off => {
                            // Osilator utama
                            const o1 = ctx.createOscillator();
                            const o2 = ctx.createOscillator(); // harmonic ringan
                            const g = ctx.createGain();
                            o1.type = 'sine';
                            o2.type = 'sine';
                            o1.frequency.setValueAtTime(397, now + off);
                            o2.frequency.setValueAtTime(450, now + off); // interval minor WA
                            // envelope: cepat naik, sustain, cepat fade
                            g.gain.setValueAtTime(0, now + off);
                            g.gain.linearRampToValueAtTime(0.28, now + off + 0.015);
                            g.gain.setValueAtTime(0.28, now + off + 0.28);
                            g.gain.linearRampToValueAtTime(0, now + off + 0.38);
                            o1.connect(g); o2.connect(g);
                            g.connect(ctx.destination);
                            o1.start(now + off); o1.stop(now + off + 0.40);
                            o2.start(now + off); o2.stop(now + off + 0.40);
                            _ringNodes.push(o1, o2);
                        });
                    }
                    waDialTone();
                    _callRingTimer = setInterval(waDialTone, 3200);
                };
                if (ctx.state === 'suspended') ctx.resume().then(doRing).catch(() => { });
                else doRing();
            } catch (e) { }
        }

        // ===== NADA DERING PENERIMA — gaya WhatsApp (melodi khas "bamboo/xylophone") =====
        function cwPlayRingIncoming() {
            cwStopRing();
            cwUnlockAudioCtx();
            try {
                if (!_ringAudioCtx) _ringAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const ctx = _ringAudioCtx;
                const doRing = function () {
                    // Melodi WA default: sekuens naik-turun dengan karakter "ping" xylophone
                    // Approx: G5-B5-D6-G6 … D6-B5 (ascending + resolusi)
                    const melody = [
                        { freq: 784, off: 0.00, dur: 0.13 }, // G5
                        { freq: 988, off: 0.15, dur: 0.13 }, // B5
                        { freq: 1175, off: 0.30, dur: 0.13 }, // D6
                        { freq: 1568, off: 0.45, dur: 0.22 }, // G6 (puncak)
                        { freq: 1175, off: 0.70, dur: 0.13 }, // D6 (turun)
                        { freq: 988, off: 0.85, dur: 0.18 }, // B5 (resolusi)
                    ];

                    function waRingtone() {
                        const now = ctx.currentTime;
                        melody.forEach(n => {
                            // Layer 1: sine (fundamental)
                            const o1 = ctx.createOscillator();
                            const g1 = ctx.createGain();
                            o1.type = 'sine';
                            o1.frequency.setValueAtTime(n.freq, now + n.off);
                            g1.gain.setValueAtTime(0, now + n.off);
                            g1.gain.linearRampToValueAtTime(0.45, now + n.off + 0.008); // attack cepat (pluck)
                            g1.gain.exponentialRampToValueAtTime(0.001, now + n.off + n.dur + 0.12); // decay alami
                            o1.connect(g1); g1.connect(ctx.destination);
                            o1.start(now + n.off); o1.stop(now + n.off + n.dur + 0.15);

                            // Layer 2: triangle octave atas (memberikan karakter "xylophone/ping")
                            const o2 = ctx.createOscillator();
                            const g2 = ctx.createGain();
                            o2.type = 'triangle';
                            o2.frequency.setValueAtTime(n.freq * 2, now + n.off);
                            g2.gain.setValueAtTime(0, now + n.off);
                            g2.gain.linearRampToValueAtTime(0.12, now + n.off + 0.008);
                            g2.gain.exponentialRampToValueAtTime(0.001, now + n.off + n.dur + 0.06);
                            o2.connect(g2); g2.connect(ctx.destination);
                            o2.start(now + n.off); o2.stop(now + n.off + n.dur + 0.08);

                            _ringNodes.push(o1, o2);
                        });
                    }
                    waRingtone();
                    _callRingTimer = setInterval(waRingtone, 2500);
                };
                if (ctx.state === 'suspended') ctx.resume().then(doRing).catch(() => { });
                else doRing();
            } catch (e) { }
        }

        function cwStopRing() {
            if (_callRingTimer) { clearInterval(_callRingTimer); _callRingTimer = null; }
            _ringNodes.forEach(n => { try { n.stop(); } catch (e) { } });
            _ringNodes = [];
        }

        function cwCleanupCall() {
            cwStopRing();
            if (_callDurationTimer) { clearInterval(_callDurationTimer); _callDurationTimer = null; }
            if (_callLocalStream) { _callLocalStream.getTracks().forEach(t => t.stop()); _callLocalStream = null; }
            if (_callPeer) { _callPeer.close(); _callPeer = null; }
            _callState = 'idle'; _callPeerId = 0; _callPeerName = '';
            _callMuted = false; _callCamOff = false; _callDurationSec = 0;
            _callIncomingData = null; _callQueuedIce = [];
            document.getElementById('cw-call-incoming').classList.remove('active');
            document.getElementById('cw-call-outgoing').classList.remove('active');
            document.getElementById('cw-call-active').classList.remove('active');
            const rv = document.getElementById('cw-call-remote-video');
            const lv = document.getElementById('cw-call-local-video');
            const lw = document.getElementById('cw-call-local-wrap');
            const nv = document.getElementById('cw-call-no-video');
            const muteBtn = document.getElementById('cw-ca-mute');
            const camBtn = document.getElementById('cw-ca-cam');
            if (rv) { try { rv.srcObject = null; } catch (e) { } rv.style.display = 'none'; }
            if (lv) { try { lv.srcObject = null; } catch (e) { } }
            if (lw) lw.style.display = 'none';
            if (nv) nv.style.display = 'block';
            if (muteBtn) { muteBtn.querySelector('i').className = 'fas fa-microphone'; muteBtn.classList.remove('off'); }
            if (camBtn) { camBtn.querySelector('i').className = 'fas fa-video'; camBtn.classList.remove('off'); }
            // Bersihkan elemen audio remote
            const ra = document.getElementById('cw-call-remote-audio');
            if (ra) { ra.srcObject = null; ra.pause(); }
        }

        function cwSendSignal(toId, type, data) {
            const fd = new FormData();
            fd.append('action', 'call_signal');
            fd.append('to_user_id', toId);
            fd.append('type', type);
            fd.append('data', JSON.stringify(data || {}));
            return fetch(ENDPOINT, { method: 'POST', body: fd }).then(r => r.json()).catch(() => { });
        }

        function cwCreatePeer() {
            const peer = new RTCPeerConnection(ICE_CFG);
            peer.onicecandidate = e => {
                if (e.candidate && _callPeerId > 0)
                    cwSendSignal(_callPeerId, 'ice', { candidate: e.candidate });
            };
            peer.ontrack = e => {
                // Ambil stream: dari e.streams[0] atau buat dari e.track
                const stream = (e.streams && e.streams[0]) ? e.streams[0] : (() => {
                    const s = new MediaStream(); s.addTrack(e.track); return s;
                })();

                const rv = document.getElementById('cw-call-remote-video');
                const ra = document.getElementById('cw-call-remote-audio');
                const nv = document.getElementById('cw-call-no-video');
                const hasVid = stream.getVideoTracks().length > 0;

                // *** PENTING: Audio SELALU diputar via elemen <audio> terpisah ***
                // Ini menghindari masalah autoplay-block pada elemen <video> yang disembunyikan
                if (ra) {
                    // Gabungkan track audio ke dalam audio element
                    if (!ra.srcObject) {
                        ra.srcObject = stream;
                    } else {
                        // Tambahkan track audio ke stream yang sudah ada
                        stream.getAudioTracks().forEach(t => {
                            try {
                                const existing = ra.srcObject;
                                if (existing && !existing.getAudioTracks().find(x => x.id === t.id)) {
                                    existing.addTrack(t);
                                }
                            } catch (err) { ra.srcObject = stream; }
                        });
                    }
                    ra.volume = 1.0;
                    ra.muted = false;
                    const playAudio = () => ra.play().catch(() => {
                        // Retry sekali jika gagal
                        setTimeout(() => ra.play().catch(() => { }), 300);
                    });
                    if (ra.readyState >= 2) { playAudio(); }
                    else { ra.oncanplay = playAudio; }
                }

                // Video hanya ditampilkan untuk video call
                if (hasVid && rv) {
                    rv.srcObject = stream;
                    rv.style.display = 'block';
                    rv.play().catch(() => { });
                    if (nv) nv.style.display = 'none';
                } else if (!hasVid) {
                    if (rv) rv.style.display = 'none';
                    if (nv) nv.style.display = 'block';
                }
            };
            peer.onconnectionstatechange = () => {
                if (['disconnected', 'failed', 'closed'].includes(peer.connectionState) && _callState === 'active')
                    cwCallHangup();
            };
            return peer;
        }

        function cwCallShowActive() {
            const el = document.getElementById('cw-ca-name');
            const dur = document.getElementById('cw-ca-dur');
            if (el) el.textContent = _callPeerName;
            if (dur) dur.textContent = '00:00';
            document.getElementById('cw-call-active').classList.add('active');
            if (_callDurationTimer) clearInterval(_callDurationTimer);
            _callDurationSec = 0;
            _callDurationTimer = setInterval(() => {
                _callDurationSec++;
                const m = Math.floor(_callDurationSec / 60).toString().padStart(2, '0');
                const s = (_callDurationSec % 60).toString().padStart(2, '0');
                const durEl = document.getElementById('cw-ca-dur');
                if (durEl) durEl.textContent = `${m}:${s}`;
            }, 1000);
        }

        window.cwStartCall = function (userId, nama, callType) {
            if (_callState !== 'idle') { alert('Sedang ada panggilan aktif.'); return; }
            if (!navigator.mediaDevices || !window.RTCPeerConnection) {
                alert('Browser Anda tidak mendukung fitur panggilan suara/video. Gunakan Chrome atau Firefox terbaru.');
                return;
            }
            callType = (callType || 'audio').trim();
            _callState = 'outgoing'; _callPeerId = userId; _callPeerName = nama; _callType = callType;

            // --- Call logging setup ---
            _callUid = cwMakeCallUid();
            _callStartTs = Date.now();
            _callCalleeId = userId;
            cwLogCall(userId, callType, 'initiated', 0);
            // --------------------------
            const color = cwAvatarColor2(userId);
            const coAv = document.getElementById('cw-co-avatar');
            coAv.textContent = (nama || '?').charAt(0).toUpperCase();
            coAv.style.background = color;
            document.getElementById('cw-co-name').textContent = nama;
            document.getElementById('cw-co-status').textContent = callType === 'video' ? 'Menghubungi (video)...' : 'Menghubungi...';
            document.getElementById('cw-call-outgoing').classList.add('active');
            cwPlayRingOutgoing(); // Nada dial-tone untuk pemanggil
            const audioConstraints = {
                echoCancellation: { ideal: true },
                noiseSuppression: { ideal: true },
                autoGainControl: false,   // Matikan AGC agar dua pihak bisa bicara bersamaan (full-duplex)
                channelCount: 1,
                sampleRate: { ideal: 48000 }
            };
            const constraints = { audio: audioConstraints, video: callType === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' } : false };
            navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    if (_callState !== 'outgoing') { stream.getTracks().forEach(t => t.stop()); return; }
                    _callLocalStream = stream;
                    if (callType === 'video') {
                        const lv = document.getElementById('cw-call-local-video');
                        const lw = document.getElementById('cw-call-local-wrap');
                        if (lv) lv.srcObject = stream;
                        if (lw) lw.style.display = 'block';
                    }
                    _callPeer = cwCreatePeer();
                    stream.getTracks().forEach(t => _callPeer.addTrack(t, stream));
                    return _callPeer.createOffer()
                        .then(offer => _callPeer.setLocalDescription(offer))
                        .then(() => cwSendSignal(userId, 'offer', { sdp: _callPeer.localDescription, callType, callerName: MY_NAMA }));
                })
                .catch(err => {
                    cwCleanupCall();
                    alert(err.name === 'NotAllowedError'
                        ? 'Akses mikrofon/kamera ditolak. Mohon izinkan di pengaturan browser.'
                        : 'Gagal mengakses perangkat: ' + err.message);
                });
            // Auto-cancel if no answer in 30s
            setTimeout(() => {
                if (_callState === 'outgoing' && _callPeerId === userId) {
                    cwSendSignal(userId, 'end', {});
                    cwLogCall(userId, callType, 'missed', 0);
                    cwCleanupCall();
                    cwShowCallToast('Tidak ada jawaban dari ' + nama);
                }
            }, 30000);
        };

        window.cwCallAccept = function (preferredType) {
            if (_callState !== 'incoming') return;
            cwStopRing();
            const inData = _callIncomingData;
            _callType = preferredType === 'video' ? 'video' : 'audio';
            document.getElementById('cw-call-incoming').classList.remove('active');
            _callState = 'active';
            cwCallShowActive();
            // --- Log: callee accepted. We need to log from callee's side ---
            // We don't have _callUid here (that's the caller's), so we create one for the callee
            // The server merges by call_uid but callee doesn't know caller's uid, so we store caller as callee ref
            _callCalleeIdForCallee = _callPeerId; // store caller_id so hangup can log
            _callStartTs = Date.now();
            // We log the callee side as 'answered' using caller_id as callee (direction flipped)
            // Actually the log_call action logs from the current user's perspective (as caller)
            // For the callee side: caller_id=callee (us), callee_id=caller (them), but we don't have a shared uid.
            // Simple approach: log a new record from callee perspective with a fresh uid
            _callUid = cwMakeCallUid();
            _callCalleeId = _callPeerId;
            cwLogCall(_callPeerId, _callType, 'answered', 0);
            const audioConstraints = {
                echoCancellation: { ideal: true },
                noiseSuppression: { ideal: true },
                autoGainControl: false,   // Matikan AGC agar dua pihak bisa bicara bersamaan (full-duplex)
                channelCount: 1,
                sampleRate: { ideal: 48000 }
            };
            const constraints = { audio: audioConstraints, video: _callType === 'video' ? { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' } : false };
            navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    _callLocalStream = stream;
                    if (_callType === 'video') {
                        const lv = document.getElementById('cw-call-local-video');
                        const lw = document.getElementById('cw-call-local-wrap');
                        if (lv) lv.srcObject = stream;
                        if (lw) lw.style.display = 'block';
                    }
                    _callPeer = cwCreatePeer();
                    stream.getTracks().forEach(t => _callPeer.addTrack(t, stream));
                    return _callPeer.setRemoteDescription(new RTCSessionDescription(inData.sdp))
                        .then(() => {
                            _callQueuedIce.forEach(c => _callPeer.addIceCandidate(new RTCIceCandidate(c)).catch(() => { }));
                            _callQueuedIce = [];
                        })
                        .then(() => _callPeer.createAnswer())
                        .then(ans => _callPeer.setLocalDescription(ans))
                        .then(() => cwSendSignal(_callPeerId, 'answer', { sdp: _callPeer.localDescription, callType: _callType }));
                })
                .catch(err => {
                    cwSendSignal(_callPeerId, 'reject', {});
                    cwCleanupCall();
                    alert(err.name === 'NotAllowedError' ? 'Akses mikrofon/kamera ditolak.' : 'Gagal mengakses perangkat: ' + err.message);
                });
        };

        window.cwCallReject = function () {
            if (_callState !== 'incoming') return;
            const pid = _callPeerId;
            const ptype = _callType || 'audio';
            cwCleanupCall();
            cwSendSignal(pid, 'reject', {});
            // Log rejected call from callee's perspective
            _callUid = cwMakeCallUid();
            cwLogCall(pid, ptype, 'rejected', 0);
        };

        window.cwCallCancel = function () {
            if (_callState !== 'outgoing') return;
            const pid = _callPeerId;
            const ptype = _callType || 'audio';
            const uid = _callUid;
            const calleeId = _callCalleeId;
            cwCleanupCall();
            cwSendSignal(pid, 'end', {});
            cwLogCall(calleeId, ptype, 'cancelled', 0, uid);
        };

        window.cwCallHangup = function () {
            if (_callState === 'idle') return;
            const pid = _callPeerId;
            const ptype = _callType || 'audio';
            const uid = _callUid;
            const calleeId = _callCalleeId;
            const dur = _callStartTs > 0 ? Math.round((Date.now() - _callStartTs) / 1000) : 0;
            const wasActive = (_callState === 'active');
            cwCleanupCall();
            if (pid > 0) cwSendSignal(pid, 'end', {});
            if (wasActive) cwLogCall(calleeId, ptype, 'answered', dur, uid);
            else cwLogCall(calleeId, ptype, 'cancelled', 0, uid);
        };

        window.cwCallToggleMute = function () {
            if (!_callLocalStream) return;
            _callMuted = !_callMuted;
            _callLocalStream.getAudioTracks().forEach(t => t.enabled = !_callMuted);
            const btn = document.getElementById('cw-ca-mute');
            if (btn) {
                btn.querySelector('i').className = _callMuted ? 'fas fa-microphone-slash' : 'fas fa-microphone';
                btn.classList.toggle('off', _callMuted);
            }
        };

        window.cwCallToggleCam = function () {
            if (!_callLocalStream) return;
            _callCamOff = !_callCamOff;
            _callLocalStream.getVideoTracks().forEach(t => t.enabled = !_callCamOff);
            const btn = document.getElementById('cw-ca-cam');
            if (btn) {
                btn.querySelector('i').className = _callCamOff ? 'fas fa-video-slash' : 'fas fa-video';
                btn.classList.toggle('off', _callCamOff);
            }
        };

        window.cwStartCallFromDM = function () {
            if (dmWithUserId === 0) return;
            cwStartCall(dmWithUserId, dmWithName, 'audio');
        };

        window.cwStartVideoCallFromDM = function () {
            if (dmWithUserId === 0) return;
            cwStartCall(dmWithUserId, dmWithName, 'video');
        };

        // ===== CALL LOGGING =====
        let _callUid = '';         // unique ID per call session
        let _callStartTs = 0;      // unix ts when call offer was made
        let _callCalleeId = 0;     // callee user_id (from caller's perspective)
        let _callCalleeIdForCallee = 0; // caller user_id (from callee's perspective)

        function cwMakeCallUid() {
            return 'cw_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        }

        function cwLogCall(calleeId, callType, status, durationSec, uid) {
            uid = uid || _callUid;
            if (!uid) return;
            const fd = new FormData();
            fd.append('action', 'log_call');
            fd.append('call_uid', uid);
            fd.append('callee_id', calleeId);
            fd.append('call_type', callType || 'audio');
            fd.append('call_status', status);
            fd.append('duration_sec', durationSec || 0);
            fetch(ENDPOINT, { method: 'POST', body: fd }).catch(() => { });
        }

        // ===== CALL HISTORY MODAL =====
        let _callHistOffset = 0;
        let _callHistTotal = 0;
        let _callHistLoading = false;
        const HIST_LIMIT = 20;

        window.cwOpenCallHistory = function () {
            _callHistOffset = 0;
            _callHistTotal = 0;
            const overlay = document.getElementById('cw-callhist-overlay');
            const body = document.getElementById('cw-callhist-body');
            const footer = document.getElementById('cw-callhist-footer');
            if (!overlay) return;
            body.innerHTML = '<div id="cw-callhist-loading"><i class="fas fa-spinner fa-spin" style="font-size:26px;color:#e5e7eb"></i><span>Memuat riwayat...</span></div>';
            footer.style.display = 'none';
            overlay.classList.add('active');
            cwCallHistLoad(false);
        };

        function cwCallHistLoad(append) {
            if (_callHistLoading) return;
            _callHistLoading = true;
            const lmBtn = document.getElementById('cw-callhist-load-more');
            if (lmBtn) lmBtn.disabled = true;
            fetch(`${ENDPOINT}?action=get_call_history&limit=${HIST_LIMIT}&offset=${_callHistOffset}`)
                .then(r => r.json())
                .then(data => {
                    _callHistTotal = data.total || 0;
                    cwCallHistRender(data.logs || [], append);
                    _callHistOffset += (data.logs || []).length;
                    const footer = document.getElementById('cw-callhist-footer');
                    if (footer) footer.style.display = _callHistOffset < _callHistTotal ? 'flex' : 'none';
                    if (lmBtn) lmBtn.disabled = false;
                })
                .catch(() => {
                    const body = document.getElementById('cw-callhist-body');
                    if (body && !append) body.innerHTML = '<div id="cw-callhist-empty"><i class="fas fa-exclamation-circle" style="font-size:28px;color:#fca5a5"></i><span>Gagal memuat riwayat.</span></div>';
                    if (lmBtn) lmBtn.disabled = false;
                })
                .finally(() => { _callHistLoading = false; });
        }

        function cwCallHistRender(logs, append) {
            const body = document.getElementById('cw-callhist-body');
            if (!body) return;
            if (!append) body.innerHTML = '';
            if (!logs.length && !append) {
                body.innerHTML = '<div id="cw-callhist-empty"><i class="fas fa-phone-slash" style="font-size:30px;color:#e5e7eb"></i><span>Belum ada riwayat panggilan.</span></div>';
                return;
            }
            logs.forEach(log => {
                const isMissed = (log.status === 'missed' || (log.direction === 'outgoing' && log.status === 'missed'));
                const isRejected = log.status === 'rejected';
                const isCancelled = log.status === 'cancelled';
                const isAnswered = log.status === 'answered';
                const isIncoming = log.direction === 'incoming';

                // Direction icon + class
                let dirIcon, dirClass;
                if (isIncoming && isAnswered) { dirIcon = 'fa-phone-volume'; dirClass = 'incoming'; }
                else if (isIncoming && isMissed) { dirIcon = 'fa-phone-missed'; dirClass = 'missed'; }
                else if (isIncoming && isRejected) { dirIcon = 'fa-phone-volume'; dirClass = 'missed'; }
                else if (!isIncoming && isAnswered) { dirIcon = 'fa-phone-alt'; dirClass = 'outgoing'; }
                else if (!isIncoming && isMissed || !isIncoming && isCancelled) { dirIcon = 'fa-phone-alt'; dirClass = 'cancelled'; }
                else { dirIcon = 'fa-phone-alt'; dirClass = 'outgoing'; }

                // Status color class
                let statClass = log.status;
                if (isIncoming && isRejected) statClass = 'missed';

                const color = cwAvatarColor2(log.peer_id);
                const init = (log.peer_name || '?').charAt(0).toUpperCase();
                const typeIcon = log.call_type === 'video' ? 'fa-video' : 'fa-phone';
                const durHtml = log.duration_label ? `<span class="cw-clog-dur">· ${log.duration_label}</span>` : '';

                const row = document.createElement('div');
                row.className = 'cw-clog-row';
                row.innerHTML = `
                    <div class="cw-clog-avatar" style="background:${color}">
                        ${init}
                        <div class="cw-clog-type-badge ${log.call_type}"><i class="fas ${typeIcon}"></i></div>
                    </div>
                    <div class="cw-clog-info">
                        <div class="cw-clog-name">${log.peer_name}</div>
                        <div class="cw-clog-meta">
                            <i class="fas ${dirIcon} cw-clog-dir-icon ${dirClass}"></i>
                            <span class="cw-clog-status ${statClass}">${log.status_label}</span>
                            ${durHtml}
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0">
                        <span class="cw-clog-time">${log.started_label}</span>
                        <div class="cw-clog-actions">
                            <button class="cw-clog-call-btn audio" onclick="event.stopPropagation();cwCallHistDial(${log.peer_id},'${(log.peer_name || '').replace(/'/g, "\\'")}\'audio')" title="Telepon">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button class="cw-clog-call-btn video" onclick="event.stopPropagation();cwCallHistDial(${log.peer_id},'${(log.peer_name || '').replace(/'/g, "\\'")}\'video')" title="Video Call">
                                <i class="fas fa-video"></i>
                            </button>
                        </div>
                    </div>`;
                body.appendChild(row);
            });
        }

        window.cwCallHistLoadMore = function () {
            cwCallHistLoad(true);
        };

        window.cwCallHistDial = function (peerId, peerName, callType) {
            cwCallHistForceClose();
            cwStartCall(peerId, peerName, callType.trim());
        };

        window.cwCallHistClose = function (e) {
            if (e && e.target !== document.getElementById('cw-callhist-overlay')) return;
            cwCallHistForceClose();
        };

        window.cwCallHistForceClose = function () {
            const overlay = document.getElementById('cw-callhist-overlay');
            if (overlay) overlay.classList.remove('active');
        };

        // ===== GPS LOCATION SYSTEM =====
        let _gpsCollapsed = false;
        let _gpsLoaded = false;

        window.cwGpsToggle = function () {
            _gpsCollapsed = !_gpsCollapsed;
            const body = document.getElementById('cw-gps-body');
            const icon = document.getElementById('cw-gps-toggle-icon');
            if (body) body.classList.toggle('collapsed', _gpsCollapsed);
            if (icon) icon.classList.toggle('collapsed', _gpsCollapsed);
        };

        window.cwDetectLocation = function (isRefresh) {
            if (!navigator.geolocation) {
                cwGpsSetStatus('error', '⚠️ Browser tidak mendukung GPS');
                return;
            }
            const btn = document.getElementById('cw-gps-detect-btn');
            const refreshIcon = document.getElementById('cw-gps-refresh-icon');
            cwGpsSetStatus('loading', '🔄 Mendeteksi lokasi...');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendeteksi...'; }
            if (refreshIcon) refreshIcon.classList.add('fa-spin');

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude.toFixed(6);
                    const lng = pos.coords.longitude.toFixed(6);
                    const acc = Math.round(pos.coords.accuracy);

                    // Update koordinat immediately
                    const latEl = document.getElementById('cw-gps-lat');
                    const lngEl = document.getElementById('cw-gps-lng');
                    const accEl = document.getElementById('cw-gps-accuracy');
                    if (latEl) latEl.textContent = lat;
                    if (lngEl) lngEl.textContent = lng;
                    if (accEl) accEl.textContent = `Akurasi: ±${acc} meter`;

                    // Show rows
                    const rows = document.getElementById('cw-gps-rows');
                    if (rows) rows.style.display = 'flex';
                    cwGpsSetStatus('loading', '📡 Memuat nama alamat...');

                    // Reverse geocode via Nominatim (free, no API key needed)
                    const nomUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=id&addressdetails=1`;
                    fetch(nomUrl, { headers: { 'Accept-Language': 'id' } })
                        .then(r => r.json())
                        .then(data => {
                            const addr = data.address || {};
                            // Build display address
                            const road = addr.road || addr.neighbourhood || addr.suburb || '';
                            const num = addr.house_number ? ' No.' + addr.house_number : '';
                            const displayAddr = (road + num) || data.display_name?.split(',')[0] || 'Tidak diketahui';
                            const city = addr.city || addr.town || addr.regency || addr.municipality || addr.county || addr.district || '';
                            const province = addr.state || addr.province || '';
                            const cityFull = [city, province].filter(Boolean).join(', ');
                            const country = addr.country || 'Tidak diketahui';

                            const addrEl = document.getElementById('cw-gps-address');
                            const cityEl = document.getElementById('cw-gps-city');
                            const countryEl = document.getElementById('cw-gps-country');
                            if (addrEl) addrEl.textContent = displayAddr || 'Tidak diketahui';
                            if (cityEl) cityEl.textContent = cityFull || 'Tidak diketahui';
                            if (countryEl) countryEl.textContent = country;

                            cwGpsSetStatus('success', '✅ Lokasi berhasil dideteksi');
                            _gpsLoaded = true;
                        })
                        .catch(() => {
                            const addrEl = document.getElementById('cw-gps-address');
                            const cityEl = document.getElementById('cw-gps-city');
                            const countryEl = document.getElementById('cw-gps-country');
                            if (addrEl) addrEl.textContent = 'Gagal memuat alamat';
                            if (cityEl) cityEl.textContent = '-';
                            if (countryEl) countryEl.textContent = '-';
                            cwGpsSetStatus('success', `✅ Koordinat: ${lat}, ${lng}`);
                            _gpsLoaded = true;
                        })
                        .finally(() => {
                            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-crosshairs"></i> Deteksi Ulang'; }
                            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
                        });
                },
                function (err) {
                    let msg = '⚠️ Gagal deteksi lokasi';
                    if (err.code === 1) msg = '🔒 Izin lokasi ditolak. Izinkan di browser.';
                    else if (err.code === 2) msg = '📡 Lokasi tidak tersedia saat ini.';
                    else if (err.code === 3) msg = '⏱️ Waktu habis. Coba lagi.';
                    cwGpsSetStatus('error', msg);
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-crosshairs"></i> Coba Lagi'; }
                    if (refreshIcon) refreshIcon.classList.remove('fa-spin');
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
            );
        };

        function cwGpsSetStatus(type, text) {
            const el = document.getElementById('cw-gps-status');
            const textEl = document.getElementById('cw-gps-status-text');
            if (!el || !textEl) return;
            el.className = '';
            el.classList.add(type);
            textEl.textContent = text;
        }

        // Auto-detect location when Online tab is first opened
        const _origSwitchTab = window.cwSwitchTab;
        window.cwSwitchTab = function (tab) {
            _origSwitchTab(tab);
            if (tab === 'online' && !_gpsLoaded) {
                setTimeout(() => cwDetectLocation(false), 400);
            }
        };

        function cwShowCallToast(msg) {
            let t = document.getElementById('cw-call-toast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'cw-call-toast';
                t.style.cssText = 'position:fixed;bottom:90px;right:24px;background:#1f2937;color:white;padding:10px 16px;border-radius:10px;font-size:12px;font-weight:500;z-index:100003;box-shadow:0 4px 16px rgba(0,0,0,.35);opacity:0;transition:opacity .3s;pointer-events:none';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.style.opacity = '1';
            clearTimeout(t._tid);
            t._tid = setTimeout(() => { t.style.opacity = '0'; }, 3500);
        }

        function cwPollSignals() {
            if (_sessionExpired) return;
            fetch(`${ENDPOINT}?action=get_call_signals&after_id=${_callSignalLastId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.signals || !data.signals.length) return;
                    data.signals.forEach(sig => {
                        if (sig.id > _callSignalLastId) _callSignalLastId = sig.id;
                        cwHandleSignal(sig);
                    });
                }).catch(() => { });
        }

        function cwHandleSignal(sig) {
            const type = sig.type;
            const fromId = sig.from_user_id;
            const fromName = sig.from_name;
            let payload = {};
            try { payload = JSON.parse(sig.data); } catch (e) { }

            if (type === 'offer') {
                if (_callState !== 'idle') {
                    cwSendSignal(fromId, 'reject', { reason: 'busy' });
                    return;
                }
                _callState = 'incoming'; _callPeerId = fromId; _callPeerName = fromName;
                _callIncomingData = payload; _callQueuedIce = [];
                const ciAv = document.getElementById('cw-ci-avatar');
                ciAv.textContent = (fromName || '?').charAt(0).toUpperCase();
                ciAv.style.background = cwAvatarColor2(fromId);
                document.getElementById('cw-ci-name').textContent = fromName;
                document.getElementById('cw-call-incoming').classList.add('active');
                cwPlayRingIncoming(); // Nada dering HP untuk penerima
                // Auto-reject after 30s
                setTimeout(() => { if (_callState === 'incoming' && _callPeerId === fromId) cwCallReject(); }, 30000);

            } else if (type === 'answer') {
                if (_callState !== 'outgoing' || _callPeerId !== fromId || !_callPeer) return;
                cwStopRing();
                _callPeer.setRemoteDescription(new RTCSessionDescription(payload.sdp))
                    .then(() => {
                        _callQueuedIce.forEach(c => _callPeer.addIceCandidate(new RTCIceCandidate(c)).catch(() => { }));
                        _callQueuedIce = [];
                    }).catch(() => { });
                _callState = 'active';
                _callStartTs = Date.now(); // Reset to actual answer time for duration
                document.getElementById('cw-call-outgoing').classList.remove('active');
                cwCallShowActive();

            } else if (type === 'ice') {
                if (_callPeerId !== fromId) return;
                const cand = payload.candidate;
                if (!cand) return;
                if (_callPeer && _callPeer.remoteDescription && _callPeer.remoteDescription.type) {
                    _callPeer.addIceCandidate(new RTCIceCandidate(cand)).catch(() => { });
                } else {
                    _callQueuedIce.push(cand);
                }

            } else if (type === 'reject') {
                if (_callState !== 'outgoing' || _callPeerId !== fromId) return;
                const name = _callPeerName;
                const ptype = _callType || 'audio';
                const uid = _callUid;
                const calleeId = _callCalleeId;
                cwCleanupCall();
                cwLogCall(calleeId, ptype, 'rejected', 0, uid);
                cwShowCallToast(name + ' menolak panggilan');

            } else if (type === 'end') {
                if (_callPeerId !== fromId) return;
                const wasActive = _callState === 'active';
                const wasIncoming = _callState === 'incoming';
                const dur = wasActive && _callStartTs > 0 ? Math.round((Date.now() - _callStartTs) / 1000) : 0;
                const calleeId = _callCalleeId;
                const uid = _callUid;
                const ptype = _callType || 'audio';
                cwCleanupCall();
                if (wasActive) {
                    cwLogCall(calleeId, ptype, 'answered', dur, uid);
                    cwShowCallToast('Panggilan berakhir');
                } else if (wasIncoming) {
                    // Incoming call cancelled by caller before we answered  → missed for us
                    _callUid = cwMakeCallUid();
                    cwLogCall(fromId, ptype, 'missed', 0);
                    cwShowCallToast(fromName + ' membatalkan panggilan');
                }
            }
        }

        // Poll call signals every 1.5 seconds
        setInterval(cwPollSignals, 1500);

    })();
</script>
<!-- ===== END CHAT WIDGET ===== -->