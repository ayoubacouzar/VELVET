<?php
session_start();
if (!isset($_SESSION["admin_id"])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../db.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$message = "";
$messageType = "";

// ── SUPPRIMER SOUS-CATEGORIE ──
if (isset($_GET['delete_sc'])) {
    $id = (int)$_GET['delete_sc'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM produit WHERE ID_SOUS_CATEGORIE = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        $message = "Impossible de supprimer : des produits utilisent cette sous-catégorie.";
        $messageType = "error";
    } else {
        $pdo->prepare("DELETE FROM sous_categorie WHERE ID_SOUS_CATEGORIE = ?")->execute([$id]);
        $message = "Sous-catégorie supprimée.";
        $messageType = "success";
    }
}

// ── SUPPRIMER CATEGORIE ──
if (isset($_GET['delete_cat'])) {
    $id = (int)$_GET['delete_cat'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM sous_categorie WHERE ID_CATEGORIE = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        $message = "Impossible de supprimer : cette catégorie contient des sous-catégories.";
        $messageType = "error";
    } else {
        $pdo->prepare("DELETE FROM categorie WHERE ID_CATEGORIE = ?")->execute([$id]);
        $message = "Catégorie supprimée.";
        $messageType = "success";
    }
}

// ── AJOUTER CATEGORIE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_cat') {
    $nom = trim($_POST['nom_categorie'] ?? '');
    if (empty($nom)) {
        $message = "Le nom de la catégorie est obligatoire.";
        $messageType = "error";
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM categorie WHERE NOM_CATEGORIE = ?");
        $check->execute([$nom]);
        if ($check->fetchColumn() > 0) {
            $message = "Cette catégorie existe déjà.";
            $messageType = "error";
        } else {
            $pdo->prepare("INSERT INTO categorie (NOM_CATEGORIE) VALUES (?)")->execute([$nom]);
            $message = "Catégorie \"$nom\" ajoutée avec succès.";
            $messageType = "success";
        }
    }
}

// ── MODIFIER CATEGORIE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_cat') {
    $id  = (int)$_POST['id_categorie'];
    $nom = trim($_POST['nom_categorie'] ?? '');
    if (empty($nom)) {
        $message = "Le nom est obligatoire.";
        $messageType = "error";
    } else {
        $pdo->prepare("UPDATE categorie SET NOM_CATEGORIE = ? WHERE ID_CATEGORIE = ?")->execute([$nom, $id]);
        $message = "Catégorie modifiée avec succès.";
        $messageType = "success";
    }
}

// ── AJOUTER SOUS-CATEGORIE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_sc') {
    $nom   = trim($_POST['nom_sc']      ?? '');
    $desc  = trim($_POST['desc_sc']     ?? '');
    $id_cat = (int)($_POST['id_categorie'] ?? 0);
    if (empty($nom) || $id_cat === 0) {
        $message = "Le nom et la catégorie parente sont obligatoires.";
        $messageType = "error";
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM sous_categorie WHERE NOM_SOUS_CATEGORIE = ? AND ID_CATEGORIE = ?");
        $check->execute([$nom, $id_cat]);
        if ($check->fetchColumn() > 0) {
            $message = "Cette sous-catégorie existe déjà dans cette catégorie.";
            $messageType = "error";
        } else {
            $pdo->prepare("INSERT INTO sous_categorie (ID_CATEGORIE, NOM_SOUS_CATEGORIE, DESCRIPTION) VALUES (?,?,?)")
                ->execute([$id_cat, $nom, $desc]);
            $message = "Sous-catégorie \"$nom\" ajoutée avec succès.";
            $messageType = "success";
        }
    }
}

// ── MODIFIER SOUS-CATEGORIE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_sc') {
    $id     = (int)$_POST['id_sc'];
    $nom    = trim($_POST['nom_sc']      ?? '');
    $desc   = trim($_POST['desc_sc']     ?? '');
    $id_cat = (int)$_POST['id_categorie'];
    if (empty($nom)) {
        $message = "Le nom est obligatoire.";
        $messageType = "error";
    } else {
        $pdo->prepare("UPDATE sous_categorie SET NOM_SOUS_CATEGORIE=?, DESCRIPTION=?, ID_CATEGORIE=? WHERE ID_SOUS_CATEGORIE=?")
            ->execute([$nom, $desc, $id_cat, $id]);
        $message = "Sous-catégorie modifiée avec succès.";
        $messageType = "success";
    }
}

// ── CHARGER DONNÉES (ordre par ID décroissant) ──
$categories = $pdo->query("SELECT * FROM categorie ORDER BY ID_CATEGORIE DESC")->fetchAll(PDO::FETCH_ASSOC);

$sousCategories = $pdo->query("
    SELECT sc.*, c.NOM_CATEGORIE
    FROM sous_categorie sc
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    ORDER BY sc.ID_SOUS_CATEGORIE DESC
")->fetchAll(PDO::FETCH_ASSOC);

$editCat = null;
$editSc  = null;
if (isset($_GET['edit_cat'])) {
    $s = $pdo->prepare("SELECT * FROM categorie WHERE ID_CATEGORIE = ?");
    $s->execute([(int)$_GET['edit_cat']]);
    $editCat = $s->fetch(PDO::FETCH_ASSOC);
}
if (isset($_GET['edit_sc'])) {
    $s = $pdo->prepare("SELECT * FROM sous_categorie WHERE ID_SOUS_CATEGORIE = ?");
    $s->execute([(int)$_GET['edit_sc']]);
    $editSc = $s->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories | Velvet Admin</title>
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
        .page-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
        @media(max-width:860px){ .page-grid { grid-template-columns: 1fr; } }
        .section-card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; }
        .section-card .card-header { background:#000; color:#fff; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; }
        .section-card .card-header h3 { font-family:'Anton',sans-serif; font-size:16px; letter-spacing:1px; display:flex; align-items:center; gap:8px; }
        .count-badge { background:rgba(255,255,255,0.2); color:#fff; font-size:11px; padding:2px 8px; border-radius:20px; font-weight:600; }
        .card-form { padding:20px; border-bottom:1px solid #f0f0f0; background:#fafafa; }
        .card-form .form-row { display:grid; grid-template-columns:1fr auto; gap:10px; align-items:end; }
        .card-form .form-row-full { display:flex; flex-direction:column; gap:10px; }
        .card-form label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#555; margin-bottom:5px; display:block; }
        .card-form input, .card-form select, .card-form textarea { width:100%; padding:9px 12px; border:1.5px solid #e0e0e0; border-radius:8px; font-family:'Inter',sans-serif; font-size:13.5px; outline:none; background:#fff; transition:border 0.2s; }
        .card-form input:focus, .card-form select:focus, .card-form textarea:focus { border-color:#000; }
        .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; border:none; cursor:pointer; font-size:13px; font-family:'Inter',sans-serif; font-weight:500; transition:0.2s; text-decoration:none; white-space:nowrap; }
        .btn-black { background:#000; color:#fff; } .btn-black:hover { background:#333; }
        .btn-edit  { background:#f0f0f0; color:#000; } .btn-edit:hover  { background:#ddd; }
        .btn-del   { background:#fff0f0; color:#c0392b; } .btn-del:hover   { background:#ffd5d5; }
        .btn-sm { padding:5px 10px; font-size:12px; border-radius:6px; }
        .cat-list { padding:0; }
        .cat-item { display:flex; align-items:center; justify-content:space-between; padding:13px 20px; border-bottom:1px solid #f4f4f4; transition:background 0.15s; }
        .cat-item:last-child { border-bottom:none; }
        .cat-item:hover { background:#fafafa; }
        .cat-item-info { display:flex; align-items:center; gap:10px; }
        .cat-item-info .icon { width:32px; height:32px; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#555; flex-shrink:0; }
        .cat-item-info .name { font-weight:600; font-size:14px; }
        .cat-item-info .meta { font-size:11.5px; color:#888; margin-top:2px; }
        .cat-actions { display:flex; gap:6px; }
        .empty-state { text-align:center; padding:32px 20px; color:#aaa; font-size:13px; }
        .empty-state i { font-size:28px; margin-bottom:10px; display:block; }
    </style>
</head>
<body>
<div class="container">

    <div class="sidebar">
        <div class="logo-section"><a href="index.php"><img src="../images/logo2.png" alt="Logo"></a></div>
        <ul class="menu">
            <li><a href="index.php"          class="<?= $currentPage=='index.php'          ?'active':''?>"><i class="fas fa-chart-line"></i><span> Tableau de bord</span></a></li>
            <li><a href="produits.php"        class="<?= $currentPage=='produits.php'        ?'active':''?>"><i class="fas fa-box"></i><span> Produits</span></a></li>
            <li><a href="categories.php"      class="<?= $currentPage=='categories.php'      ?'active':''?>"><i class="fas fa-tags"></i><span> Catégories</span></a></li>
            <li><a href="comptes.php"         class="<?= $currentPage=='comptes.php'         ?'active':''?>"><i class="fas fa-users"></i><span> Comptes</span></a></li>
            <li><a href="commandes.php"       class="<?= $currentPage=='commandes.php'       ?'active':''?>"><i class="fas fa-shopping-cart"></i><span> Commandes</span></a></li>
            <li><a href="avis.php"            class="<?= $currentPage=='avis.php'            ?'active':''?>"><i class="fas fa-star"></i><span> Avis</span></a></li>
            <li><a href="messages.php"        class="<?= $currentPage=='messages.php'        ?'active':''?>"><i class="fas fa-envelope"></i><span> Messages</span></a></li>
            <li><a href="modifier_profil.php" class="<?= $currentPage=='modifier_profil.php' ?'active':''?>"><i class="fas fa-user-cog"></i><span> Mon profil</span></a></li>
            <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i><span> Déconnexion</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <h2>Catégories</h2>

        <?php if ($message): ?>
        <div id="phpToast" data-msg="<?= htmlspecialchars($message) ?>" data-type="<?= $messageType ?>"></div>
        <?php endif; ?>

        <div class="page-grid">
            <div>
                <div class="section-card">
                    <div class="card-header"><h3><i class="fas fa-layer-group"></i> Catégories</h3><span class="count-badge"><?= count($categories) ?></span></div>
                    <div class="card-form">
                        <form method="POST" novalidate>
                            <?php if ($editCat): ?>
                                <input type="hidden" name="action" value="edit_cat"><input type="hidden" name="id_categorie" value="<?= $editCat['ID_CATEGORIE'] ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add_cat">
                            <?php endif; ?>
                            <div class="form-row">
                                <div><label><?= $editCat ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></label><input type="text" name="nom_categorie" value="<?= htmlspecialchars($editCat['NOM_CATEGORIE'] ?? '') ?>" placeholder="Ex : Hommes, Femmes, Enfants…"></div>
                                <div style="padding-top:21px;"><button type="submit" class="btn btn-black"><i class="fas fa-<?= $editCat ? 'save' : 'plus' ?>"></i> <?= $editCat ? 'Modifier' : 'Ajouter' ?></button></div>
                            </div>
                            <?php if ($editCat): ?><div style="margin-top:8px;"><a href="categories.php" class="btn btn-edit btn-sm"><i class="fas fa-times"></i> Annuler</a></div><?php endif; ?>
                        </form>
                    </div>
                    <div class="cat-list">
                        <?php if (empty($categories)): ?><div class="empty-state"><i class="fas fa-tags"></i>Aucune catégorie.</div><?php else: ?>
                        <?php foreach ($categories as $cat): $nbSc = $pdo->prepare("SELECT COUNT(*) FROM sous_categorie WHERE ID_CATEGORIE=?"); $nbSc->execute([$cat['ID_CATEGORIE']]); $nb = $nbSc->fetchColumn(); ?>
                        <div class="cat-item"><div class="cat-item-info"><div class="icon"><i class="fas fa-tag"></i></div><div><div class="name"><?= htmlspecialchars($cat['NOM_CATEGORIE']) ?></div><div class="meta"><?= $nb ?> sous-catégorie<?= $nb > 1 ? 's' : '' ?></div></div></div><div class="cat-actions"><a href="?edit_cat=<?= $cat['ID_CATEGORIE'] ?>" class="btn btn-edit btn-sm"><i class="fas fa-pen"></i></a><a href="?delete_cat=<?= $cat['ID_CATEGORIE'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer cette catégorie ?')"><i class="fas fa-trash"></i></a></div></div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <div class="section-card">
                    <div class="card-header"><h3><i class="fas fa-sitemap"></i> Sous-catégories</h3><span class="count-badge"><?= count($sousCategories) ?></span></div>
                    <div class="card-form">
                        <form method="POST" novalidate>
                            <?php if ($editSc): ?>
                                <input type="hidden" name="action" value="edit_sc"><input type="hidden" name="id_sc" value="<?= $editSc['ID_SOUS_CATEGORIE'] ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add_sc">
                            <?php endif; ?>
                            <div class="form-row-full">
                                <div><label>Catégorie parente *</label><select name="id_categorie"><?php foreach ($categories as $cat): ?><option value="<?= $cat['ID_CATEGORIE'] ?>" <?= (isset($editSc) && $editSc['ID_CATEGORIE'] == $cat['ID_CATEGORIE']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['NOM_CATEGORIE']) ?></option><?php endforeach; ?></select></div>
                                <div><label><?= $editSc ? 'Modifier la sous-catégorie' : 'Nom de la sous-catégorie' ?> *</label><input type="text" name="nom_sc" value="<?= htmlspecialchars($editSc['NOM_SOUS_CATEGORIE'] ?? '') ?>" placeholder="Ex : Chemises, Robes, Vestes…"></div>
                                <div><label>Description <span style="color:#aaa;">(optionnel)</span></label><textarea name="desc_sc"><?= htmlspecialchars($editSc['DESCRIPTION'] ?? '') ?></textarea></div>
                                <div><button type="submit" class="btn btn-black"><i class="fas fa-<?= $editSc ? 'save' : 'plus' ?>"></i> <?= $editSc ? 'Modifier' : 'Ajouter' ?></button> <?php if ($editSc): ?><a href="categories.php" class="btn btn-edit">Annuler</a><?php endif; ?></div>
                            </div>
                        </form>
                    </div>
                    <div class="cat-list">
                        <?php if (empty($sousCategories)): ?><div class="empty-state"><i class="fas fa-sitemap"></i>Aucune sous-catégorie.</div><?php else: ?>
                        <?php $grouped = []; foreach ($sousCategories as $sc) $grouped[$sc['NOM_CATEGORIE'] ?? 'Sans catégorie'][] = $sc; foreach ($grouped as $catName => $items): ?>
                        <div style="padding:10px 20px 4px; background:#f8f8f8;"><span style="font-size:11px; font-weight:700;"><i class="fas fa-tag"></i> <?= htmlspecialchars($catName) ?></span></div>
                        <?php foreach ($items as $sc): ?>
                        <div class="cat-item" style="padding-left:32px;"><div class="cat-item-info"><div class="icon" style="background:#f5f5f5;"><i class="fas fa-minus" style="font-size:10px;"></i></div><div><div class="name"><?= htmlspecialchars($sc['NOM_SOUS_CATEGORIE']) ?></div><?php if (!empty($sc['DESCRIPTION'])): ?><div class="meta"><?= htmlspecialchars($sc['DESCRIPTION']) ?></div><?php endif; ?></div></div><div class="cat-actions"><a href="?edit_sc=<?= $sc['ID_SOUS_CATEGORIE'] ?>" class="btn btn-edit btn-sm"><i class="fas fa-pen"></i></a><a href="?delete_sc=<?= $sc['ID_SOUS_CATEGORIE'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer cette sous-catégorie ?')"><i class="fas fa-trash"></i></a></div></div>
                        <?php endforeach; endforeach; endif; ?>
                    </div>
                </div>
            </div>
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
window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('phpToast');
    if (el) showToast(el.dataset.msg, el.dataset.type);
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[type="text"], select');
            for (const inp of inputs) {
                if (!inp.value.trim()) {
                    e.preventDefault(); inp.focus();
                    const label = inp.closest('div')?.querySelector('label')?.textContent?.replace('*','').trim() || 'Ce champ';
                    showToast(`${label} est obligatoire.`, 'error'); return;
                }
            }
        });
    });
});
</script>
</body>
</html>