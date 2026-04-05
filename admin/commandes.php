<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../db.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$message = "";
$messageType = "";

// ── ASSIGNER LIVREUR ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_livreur') {
    $id_commande = (int)$_POST['id_commande'];
    $id_livreur  = (int)$_POST['id_livreur'];

    // Vérifier si la commande est déjà livrée
    $chkStatut = $pdo->prepare("SELECT STATUT_COMMANDE FROM commande WHERE ID_COMMANDE = ?");
    $chkStatut->execute([$id_commande]);
    $statut = $chkStatut->fetchColumn();
    if ($statut === 'livré' || $statut === 'livree' || $statut === 'livré') {
        $message = "Cette commande est déjà livrée, vous ne pouvez pas modifier le livreur.";
        $messageType = "error";
    } else {
        $chk = $pdo->prepare("SELECT ID_LIVRAISON FROM livraison WHERE ID_COMMANDE = ?");
        $chk->execute([$id_commande]);
        if ($chk->fetchColumn()) {
            $pdo->prepare("UPDATE livraison SET ID_LIVREUR = ? WHERE ID_COMMANDE = ?")
                ->execute([$id_livreur, $id_commande]);
        } else {
            $pdo->prepare("INSERT INTO livraison (ID_COMMANDE, ID_LIVREUR, STATUT_LIVRAISON) VALUES (?, ?, 'en attente')")
                ->execute([$id_commande, $id_livreur]);
        }
        // Mettre à jour le statut de la commande à "en cours" (et non "en livraison")
        $pdo->prepare("UPDATE commande SET STATUT_COMMANDE = 'en cours' WHERE ID_COMMANDE = ? AND STATUT_COMMANDE NOT IN ('livré','annulé')")
            ->execute([$id_commande]);
        $message = "Livreur assigné avec succès.";
        $messageType = "success";
    }
}

// ── SUPPRIMER COMMANDE ──
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM contient  WHERE ID_COMMANDE = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM livraison WHERE ID_COMMANDE = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM commande  WHERE ID_COMMANDE = ?")->execute([$id]);
    $message = "Commande supprimée.";
    $messageType = "success";
    header("Location: commandes.php?msg=deleted");
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'] === 'deleted' ? "Commande supprimée." : "";
    $messageType = "success";
}

// ── CHARGER COMMANDES (sans filtre pour la pagination) ──
$allCommandes = $pdo->query("
    SELECT c.*,
           CONCAT(cl.PRENOM_CLIENT,' ',cl.NOM_CLIENT)    AS NOM_CLIENT,
           cl.TEL_CLIENT,
           cl.EMAIL_CLIENT,
           cl.ADRESSE_CLIENT,
           CONCAT(lv.PRENOM_LIVREUR,' ',lv.NOM_LIVREUR)  AS NOM_LIVREUR,
           li.ID_LIVREUR                                  AS LIV_ID_LIVREUR,
           li.STATUT_LIVRAISON,
           COALESCE(c.MONTANT_TOTAL, SUM(co.QUANTITE * co.PRIX), 0) AS TOTAL
    FROM commande c
    LEFT JOIN client    cl ON c.ID_CLIENT    = cl.ID_CLIENT
    LEFT JOIN livraison li ON c.ID_COMMANDE  = li.ID_COMMANDE
    LEFT JOIN livreur   lv ON li.ID_LIVREUR  = lv.ID_LIVREUR
    LEFT JOIN contient  co ON c.ID_COMMANDE  = co.ID_COMMANDE
    GROUP BY c.ID_COMMANDE
    ORDER BY c.DATE_COMMANDE DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── LIVREURS DISPONIBLES ──
$livreurs = $pdo->query("SELECT ID_LIVREUR, PRENOM_LIVREUR, NOM_LIVREUR FROM livreur ORDER BY NOM_LIVREUR")->fetchAll(PDO::FETCH_ASSOC);

// ── DETAIL COMMANDE ──
$detail = null;
$lignes = [];
if (isset($_GET['detail'])) {
    $did = (int)$_GET['detail'];
    $s = $pdo->prepare("
        SELECT c.*,
               CONCAT(cl.PRENOM_CLIENT,' ',cl.NOM_CLIENT)    AS NOM_CLIENT,
               cl.TEL_CLIENT, cl.EMAIL_CLIENT, cl.ADRESSE_CLIENT,
               CONCAT(lv.PRENOM_LIVREUR,' ',lv.NOM_LIVREUR)  AS NOM_LIVREUR,
               lv.TEL_LIVREUR, lv.ZONE_LIVRAISON,
               li.ID_LIVREUR AS LIV_ID_LIVREUR,
               li.STATUT_LIVRAISON,
               COALESCE(c.MONTANT_TOTAL, SUM(co.QUANTITE * co.PRIX), 0) AS TOTAL
        FROM commande c
        LEFT JOIN client    cl ON c.ID_CLIENT    = cl.ID_CLIENT
        LEFT JOIN livraison li ON c.ID_COMMANDE  = li.ID_COMMANDE
        LEFT JOIN livreur   lv ON li.ID_LIVREUR  = lv.ID_LIVREUR
        LEFT JOIN contient  co ON c.ID_COMMANDE  = co.ID_COMMANDE
        WHERE c.ID_COMMANDE = ?
        GROUP BY c.ID_COMMANDE
    ");
    $s->execute([$did]);
    $detail = $s->fetch(PDO::FETCH_ASSOC);

    $sl = $pdo->prepare("
        SELECT co.QUANTITE, co.PRIX, co.ID_MODELE,
               p.NOM_PRODUIT, p.IMAGE1,
               mp.TAILLE, mp.COULEUR
        FROM contient co
        LEFT JOIN modele_produit mp ON co.ID_MODELE  = mp.ID_MODELE
        LEFT JOIN produit        p  ON mp.ID_PRODUIT = p.ID_PRODUIT
        WHERE co.ID_COMMANDE = ?
    ");
    $sl->execute([$did]);
    $lignes = $sl->fetchAll(PDO::FETCH_ASSOC);
}

// ── HELPER : badge statut (sans "en livraison") ──
function statutBadge(string $s): array {
    $s = mb_strtolower(trim($s));
    return match(true) {
        str_contains($s, 'livr') => ['s-livree',   'fa-check-circle',   'Livré'],
        str_contains($s, 'en cours') => ['s-confirmee','fa-thumbs-up',       'En cours'],
        str_contains($s, 'confirm') => ['s-confirmee','fa-thumbs-up',       'Confirmée'],
        str_contains($s, 'annul') => ['s-annulee',  'fa-times-circle',    'Annulée'],
        default => ['s-attente',  'fa-clock',           'En attente'],
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes | Velvet Admin</title>
    <link rel="icon" type="image/png" href="../images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style_admin.css">
    <style>
        .global-toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-100%); background: #2e7d32; color: #fff; padding: 12px 24px; border-radius: 40px; font-size: 14px; font-weight: 500; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.3s ease, opacity 0.3s ease; opacity: 0; pointer-events: none; font-family: 'Inter', sans-serif; }
        .global-toast.error { background: #c62828; }
        .global-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .section-card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.07); overflow:hidden; margin-bottom:20px; }
        .card-header  { background:#000; color:#fff; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header h3 { font-family:'Anton',sans-serif; font-size:15px; letter-spacing:0.5px; color:#fff; display:flex; align-items:center; gap:8px; }
        .count-badge  { background:rgba(255,255,255,0.2); color:#fff; font-size:11px; padding:2px 8px; border-radius:20px; font-weight:600; }
        .table-wrap { overflow-x:auto; }
        .admin-table { width:100%; border-collapse:collapse; font-size:13px; }
        .admin-table thead tr { background:#f7f7f7; }
        .admin-table thead th { padding:11px 14px; text-align:left; font-weight:600; color:#444; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; border-bottom:1px solid #eee; white-space:nowrap; }
        .admin-table tbody tr { border-bottom:1px solid #f4f4f4; transition:background .12s; }
        .admin-table tbody tr:hover { background:#fafafa; }
        .admin-table tbody td { padding:11px 14px; vertical-align:middle; }
        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
        .s-attente    { background:#fff8e1; color:#f57f17; }
        .s-confirmee  { background:#e3f2fd; color:#1565c0; }
        .s-livree     { background:#e8f5e9; color:#2e7d32; }
        .s-annulee    { background:#ffebee; color:#c62828; }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:none; cursor:pointer; font-size:13px; font-family:'Inter',sans-serif; font-weight:500; transition:.2s; text-decoration:none; }
        .btn-black { background:#000; color:#fff; } .btn-black:hover { background:#333; }
        .btn-light { background:#f0f0f0; color:#333; } .btn-light:hover { background:#e0e0e0; }
        .btn-del   { background:#fff0f0; color:#c0392b; } .btn-del:hover { background:#ffd5d5; }
        .btn-blue  { background:#e8f0fe; color:#1a56db; } .btn-blue:hover { background:#d2e3fc; }
        .btn-sm    { padding:5px 10px; font-size:12px; border-radius:6px; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; gap:12px; flex-wrap:wrap; border-bottom:1px solid #f0f0f0; }
        .search-box { position:relative; flex:1; max-width:280px; }
        .search-box input { width:100%; padding:8px 12px 8px 34px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:13px; outline:none; background:#fafafa; }
        .search-box input:focus { border-color:#000; background:#fff; }
        .search-box i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#aaa; font-size:13px; }
        .empty-state { text-align:center; padding:50px 20px; color:#ccc; }
        .empty-state i { font-size:2.2rem; margin-bottom:12px; display:block; }
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.38); z-index:300; align-items:flex-start; justify-content:center; padding-top:50px; overflow-y:auto; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:14px; width:92%; max-width:720px; position:relative; margin-bottom:40px; overflow:hidden; }
        .modal-head { background:#000; color:#fff; padding:16px 22px; display:flex; align-items:center; justify-content:space-between; }
        .modal-head h3 { font-family:'Anton',sans-serif; font-size:16px; letter-spacing:0.5px; }
        .modal-close-btn { background:none; border:none; color:#fff; font-size:20px; cursor:pointer; opacity:.7; }
        .modal-close-btn:hover { opacity:1; }
        .modal-body { padding:24px; }
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:22px; }
        .detail-item label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#999; display:block; margin-bottom:3px; }
        .detail-item span  { font-size:14px; font-weight:500; color:#111; }
        .ligne-produit { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid #f5f5f5; }
        .ligne-img    { width:52px; height:52px; border-radius:8px; object-fit:cover; background:#eee; flex-shrink:0; }
        .total-bar { background:#f8f8f8; border-radius:10px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; margin-top:16px; }
        .total-bar .amount { font-size:20px; font-weight:700; color:#111; font-family:'Anton',sans-serif; }
        .livreur-select { padding:6px 10px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:12px; outline:none; background:#fafafa; max-width:200px; }
        .info-note { display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#999; font-style:italic; }
        #paginationControls { display:flex; justify-content:center; gap:8px; margin:20px 0; flex-wrap:wrap; }
        @media(max-width:600px){ .detail-grid { grid-template-columns:1fr; } }
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
        <div class="top-bar"><h1 class="page-title"><i class="fas fa-shopping-cart"></i> Commandes</h1></div>

        <?php if ($message): ?>
        <div id="phpToast" data-msg="<?= htmlspecialchars($message) ?>" data-type="<?= $messageType ?>"></div>
        <?php endif; ?>

        <div class="section-card">
            <div class="card-header"><h3><i class="fas fa-shopping-cart"></i> Liste des commandes</h3><span class="count-badge"><?= count($allCommandes) ?></span></div>
            <div class="toolbar"><div class="search-box"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Rechercher client, commande…"></div></div>
            <?php if (empty($allCommandes)): ?><div class="empty-state"><i class="fas fa-shopping-cart"></i><p>Aucune commande.</p></div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table" id="cmdTable">
                    <thead><tr><th>#</th><th>Client</th><th>Date</th><th>Total (DH)</th><th>Statut commande</th><th>Livreur</th><th>Actions</th></tr></thead>
                    <tbody id="cmdTbody">
                    <?php foreach ($allCommandes as $cmd):
                        [$sc, $icon, $lbl] = statutBadge($cmd['STATUT_COMMANDE'] ?? '');
                    ?>
                        <tr data-statut="<?= htmlspecialchars(mb_strtolower($cmd['STATUT_COMMANDE'] ?? '')) ?>">
                            <td>#<?= $cmd['ID_COMMANDE'] ?></td>
                            <td><strong><?= htmlspecialchars($cmd['NOM_CLIENT'] ?? '—') ?></strong><?php if ($cmd['TEL_CLIENT']): ?><div style="font-size:11px;color:#aaa;"><?= htmlspecialchars($cmd['TEL_CLIENT']) ?></div><?php endif; ?></td>
                            <td><?= htmlspecialchars($cmd['DATE_COMMANDE'] ?? '—') ?></td>
                            <td><strong><?= number_format((float)$cmd['TOTAL'], 2, ',', ' ') ?> </strong></td>
                            <td><span class="badge <?= $sc ?>"><i class="fas <?= $icon ?>"></i> <?= $lbl ?></span></td>
                            <td><?= $cmd['NOM_LIVREUR'] ? htmlspecialchars($cmd['NOM_LIVREUR']) : '<span style="color:#ccc;">Non assigné</span>' ?></td>
                            <td><a href="?detail=<?= $cmd['ID_COMMANDE'] ?>" class="btn btn-blue btn-sm"><i class="fas fa-eye"></i></a> <a href="?delete=<?= $cmd['ID_COMMANDE'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer la commande #<?= $cmd['ID_COMMANDE'] ?> ?')"><i class="fas fa-trash"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationControls"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($detail):
    [$dsc, $dicon, $dlbl] = statutBadge($detail['STATUT_COMMANDE'] ?? '');
?>
<div class="modal-overlay open" id="modalDetail">
    <div class="modal-box">
        <div class="modal-head"><h3><i class="fas fa-receipt"></i> Commande #<?= $detail['ID_COMMANDE'] ?></h3><button class="modal-close-btn" onclick="window.location='commandes.php'"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <p class="section-lbl"><i class="fas fa-user"></i> Informations client</p>
            <div class="detail-grid">
                <div class="detail-item"><label>Client</label><span><?= htmlspecialchars($detail['NOM_CLIENT'] ?? '—') ?></span></div>
                <div class="detail-item"><label>Téléphone</label><span><?= htmlspecialchars($detail['TEL_CLIENT'] ?? '—') ?></span></div>
                <div class="detail-item"><label>E-mail</label><span><?= htmlspecialchars($detail['EMAIL_CLIENT'] ?? '—') ?></span></div>
                <div class="detail-item"><label>Adresse de livraison</label><span><?= htmlspecialchars($detail['ADRESSE_LIVRAISON'] ?? $detail['ADRESSE_CLIENT'] ?? '—') ?></span></div>
                <div class="detail-item"><label>Date de commande</label><span><?= htmlspecialchars($detail['DATE_COMMANDE'] ?? '—') ?></span></div>
            </div>
            <p class="section-lbl"><i class="fas fa-truck"></i> Livraison</p>
            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:22px; align-items:center;">
                <div><span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#999;">Statut :</span> <span class="badge <?= $dsc ?>"><i class="fas <?= $dicon ?>"></i> <?= $dlbl ?></span> <span class="info-note"><i class="fas fa-info-circle"></i> Mis à jour par le livreur</span></div>
                <form method="POST" style="display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="action" value="assign_livreur"><input type="hidden" name="id_commande" value="<?= $detail['ID_COMMANDE'] ?>">
                    <select name="id_livreur" class="livreur-select"><?php foreach($livreurs as $liv): ?><option value="<?= $liv['ID_LIVREUR'] ?>" <?= ($detail['LIV_ID_LIVREUR'] ?? 0) == $liv['ID_LIVREUR'] ? 'selected' : '' ?>><?= htmlspecialchars($liv['PRENOM_LIVREUR'].' '.$liv['NOM_LIVREUR']) ?></option><?php endforeach; ?></select>
                    <button type="submit" class="btn btn-black btn-sm"><i class="fas fa-motorcycle"></i> Assigner</button>
                </form>
            </div>
            <p class="section-lbl"><i class="fas fa-box"></i> Articles (<?= count($lignes) ?>)</p>
            <?php if (empty($lignes)): ?><p style="color:#ccc; text-align:center; padding:16px;">Aucun article.</p><?php else: ?><div><?php foreach($lignes as $lg): ?><div class="ligne-produit"><?php if (!empty($lg['IMAGE1'])): ?><img src="../<?= htmlspecialchars($lg['IMAGE1']) ?>" class="ligne-img"><?php else: ?><div class="ligne-no-img"><i class="fas fa-image"></i></div><?php endif; ?><div class="ligne-info" style="flex:1;"><div class="name"><?= htmlspecialchars($lg['NOM_PRODUIT'] ?? '—') ?></div><div class="meta"><?= implode(' · ', array_filter([$lg['TAILLE']??'', $lg['COULEUR']??''])) ?> × <?= (int)$lg['QUANTITE'] ?></div></div><div class="ligne-prix"><?= number_format((float)($lg['PRIX'] ?? 0), 2, ',', ' ') ?> DH</div></div><?php endforeach; ?></div><?php endif; ?>
            <div class="total-bar"><span class="label">Total commande</span><span class="amount"><?= number_format((float)$detail['TOTAL'], 2, ',', ' ') ?> DH</span></div>
            <div style="margin-top:20px; text-align:right;"><a href="commandes.php" class="btn btn-light"><i class="fas fa-times"></i> Fermer</a></div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
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
    renderPage();
});
// Pagination (20 par page)
const ROWS_PER_PAGE = 20;
let currentPage = 1;
function renderPage() {
    const rows = [...document.querySelectorAll('#cmdTbody tr')];
    const total = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;
    rows.forEach((r,i) => { r.style.display = (i >= (currentPage-1)*ROWS_PER_PAGE && i < currentPage*ROWS_PER_PAGE) ? '' : 'none'; });
    const pc = document.getElementById('paginationControls');
    pc.innerHTML = '';
    if (totalPages <= 1) return;
    const makeBtn = (label, page, active) => {
        const b = document.createElement('button');
        b.innerHTML = label; b.style.cssText = `padding:6px 12px;border-radius:6px;border:1px solid ${active?'#000':'#ddd'};background:${active?'#000':'#fff'};color:${active?'#fff':'#333'};cursor:pointer;margin:0 2px;`;
        b.onclick = () => { currentPage = page; renderPage(); };
        return b;
    };
    pc.appendChild(makeBtn('<i class="fas fa-chevron-left"></i>', currentPage-1, false));
    for(let i=1;i<=totalPages;i++) pc.appendChild(makeBtn(i, i, i===currentPage));
    pc.appendChild(makeBtn('<i class="fas fa-chevron-right"></i>', currentPage+1, false));
}
document.getElementById('searchInput')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#cmdTbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
    renderPage();
});
document.getElementById('modalDetail')?.addEventListener('click', e => { if (e.target === document.getElementById('modalDetail')) window.location = 'commandes.php'; });
</script>
</body>
</html>