<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../db.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$message = "";
$messageType = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_livreur') {
    $nom     = trim($_POST['nom_livreur']     ?? '');
    $prenom  = trim($_POST['prenom_livreur']  ?? '');
    $email   = trim($_POST['email_livreur']   ?? '');
    $tel     = trim($_POST['tel_livreur']     ?? '');
    $zone    = trim($_POST['zone_livreur']    ?? '');
    $pwd     =      $_POST['pwd_livreur']     ?? '';
    $confirm =      $_POST['confirm_livreur'] ?? '';

    if (empty($nom)||empty($prenom)||empty($email)||empty($tel)||empty($zone)||empty($pwd)) {
        $message = "Tous les champs sont obligatoires."; $messageType = "error";
    } elseif (!preg_match('/^(05|06|07|08)\d{8}$/', $tel)) {
        $message = "Le numéro doit commencer par 05, 06, 07 ou 08 et contenir exactement 10 chiffres."; $messageType = "error";
    } elseif ($pwd !== $confirm) {
        $message = "Les mots de passe ne correspondent pas."; $messageType = "error";
    } elseif (strlen($pwd) < 8) {
        $message = "Le mot de passe doit comporter au moins 8 caractères."; $messageType = "error";
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM livreur WHERE EMAIL_LIVREUR = ?");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            $message = "Cet e-mail est déjà utilisé."; $messageType = "error";
        } else {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO livreur (NOM_LIVREUR,PRENOM_LIVREUR,EMAIL_LIVREUR,MOT_DE_PASSE_LIVREUR,TEL_LIVREUR,ZONE_LIVRAISON,STATUT_LIVREUR,DATE_EMBAUCHE)
                           VALUES (?,?,?,?,?,?,'disponible',CURDATE())")
                ->execute([$nom,$prenom,$email,$hash,$tel,$zone]);
            $message = "Livreur $prenom $nom créé avec succès."; $messageType = "success";
        }
    }
}


if (isset($_GET['del_livreur'])) {
    $pdo->prepare("DELETE FROM livreur WHERE ID_LIVREUR = ?")->execute([(int)$_GET['del_livreur']]);
    $message = "Livreur supprimé."; $messageType = "success";
}


if (isset($_GET['del_client'])) {
    $pdo->prepare("DELETE FROM client WHERE ID_CLIENT = ?")->execute([(int)$_GET['del_client']]);
    $message = "Client supprimé."; $messageType = "success";
}


$clientDetail = null;
$clientCommandes = [];
if (isset($_GET['view_client'])) {
    $cid = (int)$_GET['view_client'];
    $s = $pdo->prepare("SELECT * FROM client WHERE ID_CLIENT = ?");
    $s->execute([$cid]);
    $clientDetail = $s->fetch(PDO::FETCH_ASSOC);
    if ($clientDetail) {
        $sc = $pdo->prepare("
            SELECT c.*, COALESCE(SUM(lc.QUANTITE * lc.PRIX_UNITAIRE),0) as TOTAL
            FROM commande c
            LEFT JOIN ligne_commande lc ON c.ID_COMMANDE = lc.ID_COMMANDE
            WHERE c.ID_CLIENT = ?
            GROUP BY c.ID_COMMANDE
            ORDER BY c.DATE_COMMANDE DESC
        ");
        $sc->execute([$cid]);
        $clientCommandes = $sc->fetchAll(PDO::FETCH_ASSOC);
    }
}

$clients  = $pdo->query("SELECT * FROM client ORDER BY ID_CLIENT DESC")->fetchAll();
$livreurs = $pdo->query("SELECT * FROM livreur ORDER BY ID_LIVREUR DESC")->fetchAll();

$quartiersOujda = [
    'Ain Sfa','Al Qods','Annahda','Bab El Oued','Borj Moulay Omar','Centre-ville',
    'Cite Industrielle','Hay Al Farah','Hay Al Inbiath','Hay Al Majd','Hay Al Massira',
    'Hay Al Qaria','Hay Essalam','Hay Ettakadoum','Hay Salam','Lazaret',
    'Lqliaa','Marjane','Mechouar','Oued Nachef','Sidi Maafa','Sidi Yahia'
];
sort($quartiersOujda);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes | Velvet Admin</title>
    <link rel="icon" type="image/png" href="../images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style_admin.css">
    <style>
        .global-toast {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100%);
            background: #2e7d32; color: #fff; padding: 12px 24px; border-radius: 40px; font-size: 14px;
            font-weight: 500; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, opacity 0.3s ease; opacity: 0; pointer-events: none;
            font-family: 'Inter', sans-serif;
        }
        .global-toast.error { background: #c62828; }
        .global-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .tabs-bar { display:flex; gap:8px; margin-bottom:20px; }
        .tab-btn  { padding:8px 20px; border:1.5px solid #e0e0e0; border-radius:8px; background:#fff; font-weight:500; cursor:pointer; font-size:13px; transition:.15s; }
        .tab-btn.active { background:#000; color:#fff; border-color:#000; }
        .tab-content { display:none; } .tab-content.active { display:block; }
        .section-card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.07); overflow:hidden; margin-bottom:20px; }
        .card-header { background:#000; border-bottom:1px solid #f0f0f0; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header h3 { font-family:'Anton',sans-serif; font-size:15px; letter-spacing:0.5px; color:#fff; display:flex; align-items:center; gap:8px; }
        .count-badge { background:rgba(255,255,255,0.2); color:#fff; font-size:11px; padding:2px 8px; border-radius:20px; font-weight:600; }
        .card-form { padding:20px; background:#fafafa; border-bottom:1px solid #f0f0f0; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
        .field-group label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#666; margin-bottom:5px; }
        .field-group input, .field-group select { width:100%; padding:9px 12px; border:1.5px solid #e0e0e0; border-radius:8px; font-family:'Inter',sans-serif; font-size:13.5px; outline:none; background:#fff; }
        .field-group input:focus, .field-group select:focus { border-color:#000; }
        .input-wrap { position:relative; }
        .eye-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#aaa; }
        .eye-toggle:hover { color:#333; }
        .toggle-form-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 18px; background:#000; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:13px; margin-bottom:16px; }
        #formWrapper { display:none; } #formWrapper.open { display:block; }
        .admin-table { width:100%; border-collapse:collapse; font-size:13px; }
        .admin-table thead tr { background:#f7f7f7; }
        .admin-table thead th { padding:11px 14px; text-align:left; font-weight:600; color:#444; font-size:12px; text-transform:uppercase; border-bottom:1px solid #eee; white-space:nowrap; }
        .admin-table tbody tr { border-bottom:1px solid #f4f4f4; transition:background .12s; }
        .admin-table tbody tr:hover { background:#fafafa; }
        .admin-table tbody td { padding:11px 14px; vertical-align:middle; }
        .client-row { cursor:pointer; }
        .badge-dispo  { background:#e8f5e9; color:#2e7d32; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-occupe { background:#fff3e0; color:#e65100; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:none; cursor:pointer; font-size:13px; font-family:'Inter',sans-serif; font-weight:500; transition:.2s; text-decoration:none; }
        .btn-black { background:#000; color:#fff; } .btn-black:hover { background:#333; }
        .btn-del  { background:#fff0f0; color:#c0392b; } .btn-del:hover { background:#ffd5d5; }
        .btn-sm   { padding:5px 10px; font-size:12px; border-radius:6px; }
        .empty-state { text-align:center; padding:36px 20px; color:#bbb; font-size:13px; }
        .empty-state i { font-size:28px; margin-bottom:10px; display:block; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:300; align-items:flex-start; justify-content:center; padding-top:60px; overflow-y:auto; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:14px; width:90%; max-width:680px; position:relative; margin-bottom:40px; overflow:hidden; }
        .modal-head { background:#000; color:#fff; padding:18px 24px; display:flex; align-items:center; justify-content:space-between; }
        .modal-head h3 { font-family:'Anton',sans-serif; font-size:16px; letter-spacing:0.5px; }
        .modal-close-btn { background:none; border:none; color:#fff; font-size:20px; cursor:pointer; opacity:.7; }
        .modal-close-btn:hover { opacity:1; }
        .modal-body { padding:24px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:22px; }
        .info-item label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#999; display:block; margin-bottom:3px; }
        .info-item span  { font-size:14px; font-weight:500; color:#111; }
        .commande-row { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-radius:8px; border:1px solid #f0f0f0; margin-bottom:8px; font-size:13px; }
        .status-en-attente    { background:#fff8e1; color:#f57f17; }
        .status-confirmee     { background:#e3f2fd; color:#1565c0; }
        .status-en-livraison  { background:#fff3e0; color:#e65100; }
        .status-livree        { background:#e8f5e9; color:#2e7d32; }
        .status-annulee       { background:#ffebee; color:#c62828; }
        @media(max-width:600px){ .field-row { grid-template-columns:1fr; } .info-grid { grid-template-columns:1fr; } }
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
        <h2>Comptes</h2>

        <?php if ($message): ?>
        <div id="phpToast" data-msg="<?= htmlspecialchars($message) ?>" data-type="<?= $messageType ?>"></div>
        <?php endif; ?>

        <div class="tabs-bar">
            <button class="tab-btn active" onclick="showTab('tab-livreurs', this)"><i class="fas fa-motorcycle"></i> Livreurs (<?= count($livreurs) ?>)</button>
            <button class="tab-btn" onclick="showTab('tab-clients', this)"><i class="fas fa-user"></i> Clients (<?= count($clients) ?>)</button>
        </div>

        <div id="tab-livreurs" class="tab-content active">
            <button class="toggle-form-btn" onclick="toggleForm()"><i class="fas fa-plus" id="toggleIcon"></i><span id="toggleLabel">Ajouter un livreur</span></button>
            <div id="formWrapper">
                <div class="section-card" style="margin-bottom:20px;">
                    <div class="card-header"><h3><i class="fas fa-user-plus"></i> Nouveau livreur</h3></div>
                    <div class="card-form">
                        <form method="post" id="formLivreur" novalidate>
                            <input type="hidden" name="action" value="add_livreur">
                            <div class="field-row">
                                <div class="field-group"><label>Prénom *</label><input type="text" name="prenom_livreur" required></div>
                                <div class="field-group"><label>Nom *</label><input type="text" name="nom_livreur" required></div>
                            </div>
                            <div class="field-row">
                                <div class="field-group"><label>E-mail *</label><input type="email" name="email_livreur" required></div>
                                <div class="field-group"><label>Téléphone *</label><input type="text" inputmode="numeric" pattern="[0-9]*" name="tel_livreur" placeholder="06XXXXXXXX" maxlength="10" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required></div>
                            </div>
                            <div class="field-group" style="margin-bottom:14px;"><label>Quartier (Oujda) *</label><input type="text" name="zone_livreur" placeholder="Ex : Centre-ville, Hay Essalam…" required></div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label>Mot de passe *</label>
                                    <div class="input-wrap"><input type="password" name="pwd_livreur" id="pwd1" required><button type="button" class="eye-toggle" onclick="toggleEye('pwd1','eye1')"><i class="fas fa-eye" id="eye1"></i></button></div>
                                </div>
                                <div class="field-group">
                                    <label>Confirmer *</label>
                                    <div class="input-wrap"><input type="password" name="confirm_livreur" id="pwd2" required><button type="button" class="eye-toggle" onclick="toggleEye('pwd2','eye2')"><i class="fas fa-eye" id="eye2"></i></button></div>
                                </div>
                            </div>
                            <div style="margin-top:8px;"><button type="submit" class="btn btn-black"><i class="fas fa-user-plus"></i> Ajouter le livreur</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="section-card">
                <div class="card-header"><h3><i class="fas fa-motorcycle"></i> Liste des livreurs</h3><span class="count-badge"><?= count($livreurs) ?></span></div>
                <?php if (empty($livreurs)): ?><div class="empty-state"><i class="fas fa-motorcycle"></i><p>Aucun livreur enregistré.</p></div>
                <?php else: ?>
                <div style="overflow-x:auto;"><table class="admin-table"><thead><tr><th>Nom complet</th><th>E-mail</th><th>Téléphone</th><th>Quartier</th><th>Statut</th><th>Embauché le</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($livreurs as $l): ?>
                <tr><td><strong><?= htmlspecialchars($l['PRENOM_LIVREUR'].' '.$l['NOM_LIVREUR']) ?></strong></td><td><?= htmlspecialchars($l['EMAIL_LIVREUR']) ?></td><td><?= htmlspecialchars($l['TEL_LIVREUR'] ?? '—') ?></td><td><?= htmlspecialchars($l['ZONE_LIVRAISON'] ?? '—') ?></td><td><span class="<?= ($l['STATUT_LIVREUR'] ?? '') === 'disponible' ? 'badge-dispo' : 'badge-occupe' ?>"><?= ucfirst($l['STATUT_LIVREUR'] ?? 'disponible') ?></span></td><td><?= $l['DATE_EMBAUCHE'] ?? '—' ?></td><td><a href="comptes.php?del_livreur=<?= $l['ID_LIVREUR'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer ce livreur ?')"><i class="fas fa-trash"></i></a></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>

        <div id="tab-clients" class="tab-content">
            <div class="section-card">
                <div class="card-header"><h3><i class="fas fa-users"></i> Liste des clients</h3><span class="count-badge"><?= count($clients) ?></span></div>
                <?php if (empty($clients)): ?><div class="empty-state"><i class="fas fa-users"></i><p>Aucun client enregistré.</p></div>
                <?php else: ?>
                <div style="overflow-x:auto;"><table class="admin-table"><thead><tr><th>Nom complet</th><th>E-mail</th><th>Téléphone</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($clients as $c): ?>
                <tr class="client-row" onclick="openClientModal(<?= $c['ID_CLIENT'] ?>)" title="Voir les détails"><td><strong><?= htmlspecialchars($c['PRENOM_CLIENT'].' '.$c['NOM_CLIENT']) ?></strong></td><td><?= htmlspecialchars($c['EMAIL_CLIENT']) ?></td><td><?= htmlspecialchars($c['TEL_CLIENT'] ?? '—') ?></td><td onclick="event.stopPropagation()"><a href="comptes.php?del_client=<?= $c['ID_CLIENT'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer ce client ?')"><i class="fas fa-trash"></i></a></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="clientModal">
    <div class="modal-box"><div class="modal-head"><h3 id="modalClientName"><i class="fas fa-user"></i> Client</h3><button class="modal-close-btn" onclick="closeClientModal()"><i class="fas fa-times"></i></button></div><div class="modal-body" id="modalClientBody"><div style="text-align:center;padding:30px;color:#aaa;"><i class="fas fa-spinner fa-spin fa-2x"></i></div></div></div>
</div>

<script>
const CLIENTS_DATA = <?= json_encode(array_column($clients, null, 'ID_CLIENT'), JSON_UNESCAPED_UNICODE) ?>;
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
    const form = document.getElementById('formLivreur');
    if (form) form.addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input[required], select[required]');
        for (const inp of inputs) {
            if (!inp.value.trim()) { e.preventDefault(); inp.focus(); showToast(`${inp.closest('.field-group')?.querySelector('label')?.textContent?.replace('*','').trim() || 'Ce champ'} est obligatoire.`, 'error'); return; }
        }
        const tel = this.querySelector('[name="tel_livreur"]').value;
        if (!/^(05|06|07|08)\d{8}$/.test(tel)) { e.preventDefault(); showToast('Téléphone invalide : doit commencer par 05,06,07,08 et 10 chiffres.', 'error'); return; }
        const pwd = this.querySelector('[name="pwd_livreur"]').value, confirm = this.querySelector('[name="confirm_livreur"]').value;
        if (pwd !== confirm) { e.preventDefault(); showToast('Les mots de passe ne correspondent pas.', 'error'); return; }
        if (pwd.length < 8) { e.preventDefault(); showToast('Le mot de passe doit contenir au moins 8 caractères.', 'error'); return; }
    });
});
function toggleEye(inputId, iconId) {
    const inp = document.getElementById(inputId), ico = document.getElementById(iconId);
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}
function toggleForm() { const w = document.getElementById('formWrapper'); const icon = document.getElementById('toggleIcon'); w.classList.toggle('open'); icon.className = w.classList.contains('open') ? 'fas fa-times' : 'fas fa-plus'; }
function showTab(id, btn) { document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active')); document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active')); document.getElementById(id).classList.add('active'); btn.classList.add('active'); }
function openClientModal(id) {
    const c = CLIENTS_DATA[id];
    if (!c) return;
    document.getElementById('modalClientName').innerHTML = `<i class="fas fa-user"></i> ${c.PRENOM_CLIENT ?? ''} ${c.NOM_CLIENT ?? ''}`;
    const quartier = c.ADRESSE_CLIENT ?? c.ADRESSE_LIVRAISON ?? c.QUARTIER ?? '—';
    document.getElementById('modalClientBody').innerHTML = `<div class="info-grid"><div class="info-item"><label>Nom complet</label><span>${(c.PRENOM_CLIENT??'')+' '+(c.NOM_CLIENT??'')}</span></div><div class="info-item"><label>E-mail</label><span>${c.EMAIL_CLIENT??'—'}</span></div><div class="info-item"><label>Téléphone</label><span>${c.TEL_CLIENT??'—'}</span></div><div class="info-item"><label>Quartier / Adresse</label><span>${quartier}</span></div></div><div><p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#999;margin-bottom:12px;"><i class="fas fa-shopping-bag"></i>Commandes</p><div id="cmdList"><div style="text-align:center;padding:20px;color:#bbb;"><i class="fas fa-spinner fa-spin"></i> Chargement…</div></div></div>`;
    document.getElementById('clientModal').classList.add('open');
    loadCommandes(id);
}
function loadCommandes(clientId) { fetch(`comptes_ajax.php?client_id=${clientId}`).then(r=>r.json()).then(data=>renderCommandes(data)).catch(()=>renderCommandes(null)); }
function renderCommandes(commandes) {
    const el = document.getElementById('cmdList');
    if (!el) return;
    if (!commandes || commandes.length === 0) { el.innerHTML = '<div style="text-align:center;padding:16px;color:#bbb;">Aucune commande.</div>'; return; }
    const statusClass = { 'en attente':'status-en-attente', 'confirmee':'status-confirmee','confirmée':'status-confirmee', 'en livraison':'status-en-livraison', 'livree':'status-livree','livré':'status-livree','livrée':'status-livree','livre':'status-livree', 'annulee':'status-annulee','annulée':'status-annulee','annule':'status-annulee','annulé':'status-annulee' };
    el.innerHTML = commandes.map(cmd => { const sc = statusClass[cmd.STATUT_COMMANDE?.toLowerCase()] ?? 'status-en-attente'; const icons = {'status-livree':'fa-check-circle','status-en-livraison':'fa-motorcycle','status-confirmee':'fa-thumbs-up','status-annulee':'fa-times-circle','status-en-attente':'fa-clock'}; const ico = icons[sc] ?? 'fa-circle'; return `<div class="commande-row"><span class="cmd-id">#${cmd.ID_COMMANDE}</span><span>${cmd.DATE_COMMANDE??'—'}</span><strong>${parseFloat(cmd.TOTAL??0).toFixed(2)} DH</strong><span class="cmd-status ${sc}"><i class="fas ${ico}"></i>${cmd.STATUT_COMMANDE??'—'}</span></div>`; }).join('');
}
function closeClientModal() { document.getElementById('clientModal').classList.remove('open'); }
document.getElementById('clientModal').addEventListener('click', e => { if (e.target === document.getElementById('clientModal')) closeClientModal(); });
</script>
</body>
</html>