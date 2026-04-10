<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }
require_once __DIR__ . '/../db.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$message = "";
$messageType = "";


if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM avis WHERE ID_AVIS = ?")->execute([$id]);
    header("Location: avis.php?msg=" . urlencode("Avis supprimé.") . "&type=success");
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}


$avis_list = $pdo->query("
    SELECT a.*,
           CONCAT(cl.PRENOM_CLIENT, ' ', cl.NOM_CLIENT) AS NOM_CLIENT,
           p.NOM_PRODUIT, p.IMAGE1
    FROM avis a
    LEFT JOIN client  cl ON a.ID_CLIENT  = cl.ID_CLIENT
    LEFT JOIN produit p  ON a.ID_PRODUIT = p.ID_PRODUIT
    ORDER BY a.DATE_AVIS DESC
")->fetchAll(PDO::FETCH_ASSOC);


$total      = count($avis_list);
$moyenne    = $total > 0 ? round(array_sum(array_column($avis_list, 'NOTE')) / $total, 1) : 0;
$notes_dist = array_fill(1, 5, 0);
foreach ($avis_list as $a) {
    $n = (int)($a['NOTE'] ?? 0);
    if ($n >= 1 && $n <= 5) $notes_dist[$n]++;
}
$positifs  = array_sum(array_filter(array_column($avis_list, 'NOTE'), fn($n) => $n >= 4));
$negatifs  = array_sum(array_filter(array_column($avis_list, 'NOTE'), fn($n) => $n <= 2));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avis | Velvet Admin</title>
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
        .stats-row { display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
        .stat-mini { background:#fff; border-radius:10px; padding:16px 20px; flex:1; min-width:130px; box-shadow:0 1px 6px rgba(0,0,0,0.07); display:flex; align-items:center; gap:14px; }
        .stat-mini .ico { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .stat-mini .ico.yellow { background:#fff8e1; color:#f9a825; }
        .stat-mini .ico.green  { background:#e8f5e9; color:#2e7d32; }
        .stat-mini .ico.red    { background:#fff0f0; color:#e53935; }
        .stat-mini .ico.blue   { background:#e8f0fe; color:#1a56db; }
        .stat-mini .val { font-family:'Anton',sans-serif; font-size:22px; color:#111; }
        .stat-mini .lbl { font-size:11px; color:#999; margin-top:1px; }
        .table-wrap { overflow-x:auto; }
        .admin-table { width:100%; border-collapse:collapse; font-size:13px; }
        .admin-table thead tr { background:#f7f7f7; }
        .admin-table thead th { padding:11px 14px; text-align:left; font-weight:600; color:#444; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; border-bottom:1px solid #eee; white-space:nowrap; }
        .admin-table tbody tr { border-bottom:1px solid #f4f4f4; transition:background .12s; }
        .admin-table tbody tr:hover { background:#fafafa; }
        .admin-table tbody td { padding:11px 14px; vertical-align:middle; }
        .stars { display:inline-flex; gap:2px; }
        .stars i { font-size:13px; }
        .star-full  { color:#f9a825; }
        .star-empty { color:#e0e0e0; }
        .note-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; }
        .n-5, .n-4 { background:#e8f5e9; color:#2e7d32; }
        .n-3        { background:#fff8e1; color:#f57f17; }
        .n-2, .n-1  { background:#fff0f0; color:#c62828; }
        .commentaire { font-size:13px; color:#444; max-width:280px; }
        .commentaire.vide { color:#ccc; font-style:italic; }
        .prod-img    { width:36px; height:36px; border-radius:6px; object-fit:cover; }
        .prod-no-img { width:36px; height:36px; border-radius:6px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:13px; }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:none; cursor:pointer; font-size:13px; font-family:'Inter',sans-serif; font-weight:500; transition:.2s; text-decoration:none; }
        .btn-del { background:#fff0f0; color:#c62828; } .btn-del:hover { background:#ffd5d5; }
        .btn-sm  { padding:5px 10px; font-size:12px; border-radius:6px; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; gap:12px; flex-wrap:wrap; border-bottom:1px solid #f0f0f0; }
        .search-box { position:relative; flex:1; max-width:280px; }
        .search-box input { width:100%; padding:8px 12px 8px 34px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:13px; outline:none; background:#fafafa; }
        .search-box input:focus { border-color:#000; background:#fff; }
        .search-box i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#aaa; font-size:13px; }
        .dist-card { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.07); padding:20px 24px; margin-bottom:20px; }
        .dist-card h4 { font-family:'Anton',sans-serif; font-size:14px; color:#111; margin:0 0 16px; letter-spacing:0.3px; }
        .dist-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
        .dist-label { font-size:12px; font-weight:600; color:#555; width:20px; text-align:right; flex-shrink:0; }
        .dist-bar-wrap { flex:1; background:#f0f0f0; border-radius:20px; height:8px; overflow:hidden; }
        .dist-bar-fill { height:100%; border-radius:20px; background:#f9a825; transition:width .4s; }
        .dist-count { font-size:11px; color:#aaa; width:28px; text-align:right; flex-shrink:0; }
        .empty-state { text-align:center; padding:60px 20px; }
        .empty-state i { font-size:2.2rem; color:#ccc; display:block; margin-bottom:12px; }
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
        <div class="top-bar"><h1 class="page-title"><i class="fas fa-star"></i> Avis clients</h1></div>

        <?php if ($message): ?>
        <div id="phpToast" data-msg="<?= htmlspecialchars($message) ?>" data-type="<?= $messageType ?>"></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-mini"><div class="ico blue"><i class="fas fa-comments"></i></div><div><div class="val"><?= $total ?></div><div class="lbl">Total avis</div></div></div>
            <div class="stat-mini"><div class="ico yellow"><i class="fas fa-star"></i></div><div><div class="val"><?= $moyenne ?>/5</div><div class="lbl">Note moyenne</div></div></div>
        </div>

        <?php if ($total > 0): ?>
        <div class="dist-card"><h4><i class="fas fa-chart-bar"></i> Distribution des notes</h4>
            <?php for ($n = 5; $n >= 1; $n--): $pct = $total > 0 ? round($notes_dist[$n] / $total * 100) : 0; ?>
            <div class="dist-row"><div class="dist-label"><?= $n ?>★</div><div class="dist-bar-wrap"><div class="dist-bar-fill" style="width:<?= $pct ?>%;"></div></div><div class="dist-count"><?= $notes_dist[$n] ?></div></div>
            <?php endfor; ?>
            <?php $cnt0 = 0; foreach ($avis_list as $a) if ((int)($a['NOTE'] ?? -1) === 0) $cnt0++; $pct0 = $total > 0 ? round($cnt0 / $total * 100) : 0; ?>
            <div class="dist-row"><div class="dist-label" style="color:#999;">0★</div><div class="dist-bar-wrap"><div class="dist-bar-fill" style="width:<?= $pct0 ?>%; background:#bbb;"></div></div><div class="dist-count"><?= $cnt0 ?></div></div>
        </div>
        <?php endif; ?>

        <div class="section-card">
            <div class="card-header"><h3><i class="fas fa-star"></i> Liste des avis</h3><span class="count-badge"><?= $total ?></span></div>
            <div class="toolbar"><div class="search-box"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Rechercher client, produit…"></div></div>
            <?php if (empty($avis_list)): ?><div class="empty-state"><i class="fas fa-star"></i><p>Aucun avis pour le moment.</p></div>
            <?php else: ?>
            <div class="table-wrap"><table class="admin-table" id="avisTable"><thead><tr><th>#</th><th>Client</th><th>Produit</th><th>Note</th><th>Commentaire</th><th>Date</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($avis_list as $a): $note = (int)($a['NOTE'] ?? 0); $cls = 'n-' . min(max($note, 1), 5); ?>
            <tr><td style="color:#888;">#<?= $a['ID_AVIS'] ?></td><td><strong><?= htmlspecialchars($a['NOM_CLIENT'] ?? '—') ?></strong></td>
            <td><div style="display:flex;align-items:center;gap:8px;"><?php if (!empty($a['IMAGE1'])): ?><img src="../<?= htmlspecialchars($a['IMAGE1']) ?>" class="prod-img"><?php else: ?><div class="prod-no-img"><i class="fas fa-image"></i></div><?php endif; ?><span><?= htmlspecialchars($a['NOM_PRODUIT'] ?? '—') ?></span></div></td>
            <td><span class="note-badge <?= $cls ?>"><?= $note ?>★</span><div class="stars"><?php for ($i=1;$i<=5;$i++): ?><i class="fas fa-star <?= $i <= $note ? 'star-full' : 'star-empty' ?>"></i><?php endfor; ?></div></td>
            <td><?php if (!empty($a['COMMENTAIRE'])): ?><div class="commentaire"><?= htmlspecialchars($a['COMMENTAIRE']) ?></div><?php else: ?><span class="commentaire vide">Aucun commentaire</span><?php endif; ?></td>
            <td><?= $a['DATE_AVIS'] ? date('d/m/Y', strtotime($a['DATE_AVIS'])) : '—' ?></td>
            <td><a href="?delete=<?= $a['ID_AVIS'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer cet avis ?')"><i class="fas fa-trash"></i></a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <div id="paginationControls" style="display:flex;justify-content:center;gap:8px;margin:20px 0;flex-wrap:wrap;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showToast(msg, type) {
    let toast = document.getElementById('globalToast');
    if (!toast) { toast = document.createElement('div'); toast.id = 'globalToast'; toast.className = 'global-toast'; document.body.appendChild(toast); }
    toast.textContent = msg;
    toast.className = 'global-toast ' + (type === 'success' ? 'success' : 'error') + ' show';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), 5000);
}
const ROWS_PER_PAGE = 15;
let currentPage = 1;

function getFilteredRows() {
    const q = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const all = [...document.querySelectorAll('#avisTable tbody tr')];
    if (!q) return all;
    return all.filter(tr => tr.textContent.toLowerCase().includes(q));
}

function renderPage() {
    const allRows = [...document.querySelectorAll('#avisTable tbody tr')];
    const filtered = getFilteredRows();
    const total = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;

    allRows.forEach(r => r.style.display = 'none');
    filtered.forEach((r, i) => {
        r.style.display = (i >= (currentPage-1)*ROWS_PER_PAGE && i < currentPage*ROWS_PER_PAGE) ? '' : 'none';
    });

    const pc = document.getElementById('paginationControls');
    if (!pc) return;
    pc.innerHTML = '';
    if (totalPages <= 1) return;
    const makeBtn = (label, page, active, disabled) => {
        const b = document.createElement('button');
        b.innerHTML = label;
        b.style.cssText = `padding:6px 12px;border-radius:6px;border:1px solid ${active?'#000':'#ddd'};background:${active?'#000':'#fff'};color:${active?'#fff':'#333'};cursor:${disabled?'not-allowed':'pointer'};margin:0 2px;opacity:${disabled?'0.4':'1'};`;
        if (!disabled) b.onclick = () => { currentPage = page; renderPage(); };
        return b;
    };
    pc.appendChild(makeBtn('<i class="fas fa-chevron-left"></i>', currentPage-1, false, currentPage <= 1));
    for(let i=1;i<=totalPages;i++) pc.appendChild(makeBtn(i, i, i===currentPage, false));
    pc.appendChild(makeBtn('<i class="fas fa-chevron-right"></i>', currentPage+1, false, currentPage >= totalPages));
}

window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('phpToast');
    if (el) showToast(el.dataset.msg, el.dataset.type);
    renderPage();
});
document.getElementById('searchInput')?.addEventListener('input', function() {
    currentPage = 1;
    renderPage();
});
</script>
</body>
</html>