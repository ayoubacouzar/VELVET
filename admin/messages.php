<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../db.php';

$currentPage = basename($_SERVER['PHP_SELF']);


if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM message WHERE ID_MESSAGE = ?")->execute([(int)$_GET['delete']]);
    header('Location: messages.php?deleted=1');
    exit;
}


$messages = $pdo->query("SELECT * FROM message ORDER BY DATE_MESSAGE DESC, ID_MESSAGE DESC")->fetchAll();
$total = count($messages);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Velvet Admin</title>
    <link rel="icon" type="image/png" href="../images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style_admin.css">
    <style>
        .global-toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100%); background: #2e7d32; color: #fff; padding: 12px 24px; border-radius: 40px; font-size: 14px; font-weight: 500; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.3s ease, opacity 0.3s ease; opacity: 0; pointer-events: none; font-family: 'Inter', sans-serif; }
        .global-toast.error { background: #c62828; }
        .global-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

        .messaging-layout {
            display: flex;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.07);
            overflow: hidden;
            min-height: 500px;
        }
        .msg-list {
            width: 320px;
            border-right: 1px solid #eee;
            background: #fafafa;
            overflow-y: auto;
        }
        .msg-list-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            font-size: 14px;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .msg-list-item {
            padding: 14px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.15s;
        }
        .msg-list-item:hover { background: #f0f0f0; }
        .msg-list-item.active { background: #e8f0fe; border-left: 3px solid #000; }
        .msg-sender { font-weight: 600; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .msg-sender-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .msg-date { font-size: 11px; color: #999; white-space: nowrap; }
        .msg-subject { font-size: 12px; color: #666; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .msg-preview { font-size: 11px; color: #aaa; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .msg-detail {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
        }
        .msg-detail-header {
            padding: 20px 24px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
        }
        .msg-detail-sender h3 { font-size: 16px; margin-bottom: 6px; }
        .msg-detail-sender .msg-detail-email { font-size: 12px; color: #666; }
        .msg-detail-date { font-size: 12px; color: #999; white-space: nowrap; }
        .msg-detail-body {
            padding: 24px;
            flex: 1;
            line-height: 1.6;
            font-size: 14px;
            color: #333;
            background: #fff;
        }
        .msg-detail-footer {
            padding: 16px 24px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        .btn-delete-msg {
            background: #fff0f0;
            color: #c0392b;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-delete-msg:hover { background: #ffd5d5; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; }
        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 20px; flex-wrap: wrap; }
        .page-btn { padding: 6px 12px; border-radius: 6px; border: 1px solid #ddd; background: #fff; cursor: pointer; }
        .page-btn.active { background: #000; color: #fff; border-color: #000; }
        @media (max-width: 768px) {
            .messaging-layout { flex-direction: column; }
            .msg-list { width: 100%; max-height: 300px; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="sidebar">
        <div class="logo-section"><a href="index.php"><img src="../images/logo2.png" alt="Logo"></a></div>
        <ul class="menu">
            <li><a href="index.php"          class="<?= $currentPage=='index.php'          ? 'active':'' ?>"><i class="fas fa-chart-line"></i><span> Tableau de bord</span></a></li>
            <li><a href="produits.php"        class="<?= $currentPage=='produits.php'        ? 'active':'' ?>"><i class="fas fa-box"></i><span> Produits</span></a></li>
            <li><a href="categories.php"      class="<?= $currentPage=='categories.php'      ? 'active':'' ?>"><i class="fas fa-tags"></i><span> Catégories</span></a></li>
            <li><a href="comptes.php"         class="<?= $currentPage=='comptes.php'         ? 'active':'' ?>"><i class="fas fa-users"></i><span> Comptes</span></a></li>
            <li><a href="commandes.php"       class="<?= $currentPage=='commandes.php'       ? 'active':'' ?>"><i class="fas fa-shopping-cart"></i><span> Commandes</span></a></li>
            <li><a href="avis.php"            class="<?= $currentPage=='avis.php'            ? 'active':'' ?>"><i class="fas fa-star"></i><span> Avis</span></a></li>
            <li><a href="messages.php"        class="<?= $currentPage=='messages.php'        ? 'active':'' ?>"><i class="fas fa-envelope"></i><span> Messages</span></a></li>
            <li><a href="modifier_profil.php" class="<?= $currentPage=='modifier_profil.php' ? 'active':'' ?>"><i class="fas fa-user-cog"></i><span> Mon profil</span></a></li>
            <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i><span> Déconnexion</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar"><h1 class="page-title"><i class="fas fa-envelope"></i> Messages</h1></div>

        <?php if (isset($_GET['deleted'])): ?>
        <div id="phpToast" data-msg="Message supprimé avec succès." data-type="success"></div>
        <?php endif; ?>

        <?php if (empty($messages)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucun message reçu pour le moment.</p></div>
        <?php else: ?>
        <div class="messaging-layout">
            <div class="msg-list" id="msgList"></div>
            <div class="msg-detail" id="msgDetail">
                <div class="empty-state" style="padding:80px 20px;"><i class="fas fa-envelope-open-text"></i><p>Sélectionnez un message</p></div>
            </div>
        </div>
        <div id="paginationControls" class="pagination"></div>
        <?php endif; ?>
    </div>
</div>

<script>
const MESSAGES = <?= json_encode($messages, JSON_UNESCAPED_UNICODE) ?>;
const MSG_PER_PAGE = 20;
let currentPage = 1;
let selectedMsgId = null;

function renderMessageList() {
    const start = (currentPage - 1) * MSG_PER_PAGE;
    const pageMsgs = MESSAGES.slice(start, start + MSG_PER_PAGE);
    const listDiv = document.getElementById('msgList');
    listDiv.innerHTML = `
        <div class="msg-list-header">Tous les messages (${MESSAGES.length})</div>
        ${pageMsgs.map(msg => `
            <div class="msg-list-item ${selectedMsgId === msg.ID_MESSAGE ? 'active' : ''}" data-id="${msg.ID_MESSAGE}">
                <div class="msg-sender">
                    <span class="msg-sender-name">${escapeHtml(msg.NOM_COMPLET)}</span>
                    <span class="msg-date">${formatDate(msg.DATE_MESSAGE)}</span>
                </div>
                <div class="msg-subject">${escapeHtml(msg.OBJET || 'Sans objet')}</div>
                <div class="msg-preview">${escapeHtml(msg.CONTENU).substring(0, 50)}${msg.CONTENU.length > 50 ? '…' : ''}</div>
            </div>
        `).join('')}
    `;
    document.querySelectorAll('.msg-list-item').forEach(item => {
        item.addEventListener('click', () => {
            selectedMsgId = parseInt(item.dataset.id);
            renderMessageList();
            renderMessageDetail(selectedMsgId);
        });
    });
    renderPagination();
}

function renderMessageDetail(msgId) {
    const msg = MESSAGES.find(m => m.ID_MESSAGE === msgId);
    if (!msg) return;
    const detailDiv = document.getElementById('msgDetail');
    detailDiv.innerHTML = `
        <div class="msg-detail-header">
            <div class="msg-detail-sender">
                <h3>${escapeHtml(msg.NOM_COMPLET)}</h3>
                ${msg.EMAIL_CLIENT ? `<div class="msg-detail-email"><i class="fas fa-at"></i> ${escapeHtml(msg.EMAIL_CLIENT)}</div>` : ''}
            </div>
            <div class="msg-detail-date">${formatDate(msg.DATE_MESSAGE)}</div>
        </div>
        <div class="msg-detail-body">
            <div style="margin-bottom:12px;"><strong>Objet :</strong> ${escapeHtml(msg.OBJET || '—')}</div>
            <div style="white-space:pre-wrap;">${escapeHtml(msg.CONTENU)}</div>
        </div>
        <div class="msg-detail-footer">
            <button class="btn-delete-msg" data-id="${msg.ID_MESSAGE}"><i class="fas fa-trash"></i> Supprimer</button>
        </div>
    `;
    detailDiv.querySelector('.btn-delete-msg')?.addEventListener('click', function() {
        if (confirm('Supprimer ce message ?')) {
            window.location.href = `messages.php?delete=${this.dataset.id}`;
        }
    });
}

function renderPagination() {
    const totalPages = Math.ceil(MESSAGES.length / MSG_PER_PAGE);
    const pc = document.getElementById('paginationControls');
    pc.innerHTML = '';
    if (totalPages <= 1) return;
    const makeBtn = (label, page, active) => {
        const btn = document.createElement('button');
        btn.innerHTML = label;
        btn.className = 'page-btn' + (active ? ' active' : '');
        if (!active) btn.onclick = () => { currentPage = page; renderMessageList(); };
        return btn;
    };
    pc.appendChild(makeBtn('<i class="fas fa-chevron-left"></i>', currentPage-1, false));
    for(let i=1;i<=totalPages;i++) pc.appendChild(makeBtn(i, i, i===currentPage));
    pc.appendChild(makeBtn('<i class="fas fa-chevron-right"></i>', currentPage+1, false));
}

function formatDate(d) {
    if (!d) return '—';
    const parts = String(d).split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return d;
}
function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function showToast(msg, type) {
    let toast = document.getElementById('globalToast');
    if (!toast) { toast = document.createElement('div'); toast.id = 'globalToast'; toast.className = 'global-toast'; document.body.appendChild(toast); }
    toast.textContent = msg;
    toast.className = 'global-toast ' + (type === 'success' ? 'success' : 'error') + ' show';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), 5000);
}
window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('phpToast');
    if (el) showToast(el.dataset.msg, el.dataset.type);
    if (MESSAGES.length) {
        selectedMsgId = MESSAGES[0].ID_MESSAGE;
        renderMessageList();
        renderMessageDetail(selectedMsgId);
    }
});
</script>
</body>
</html>