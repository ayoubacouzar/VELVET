<?php
session_start();
if (!isset($_SESSION["admin_id"])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../db.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$message = "";
$messageType = "";

// ── UPLOAD IMAGE ──────────────────────────────────────────
function uploadImage($file, $fieldName) {
    if (!isset($file[$fieldName]) || $file[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;

    $ext = strtolower(pathinfo($file[$fieldName]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','gif','bmp','avif'];
    if (!in_array($ext, $allowed)) return null;

    $parentDir = dirname(__DIR__);
    $dir = $parentDir . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $filename = uniqid('img_') . '.' . $ext;
    $destination = $dir . $filename;

    if (!move_uploaded_file($file[$fieldName]['tmp_name'], $destination)) {
        error_log("Upload echoue: dest=" . $destination . " erreur=" . print_r(error_get_last(), true));
        return null;
    }
    return "images/" . $filename;
}

// ── SUPPRIMER ─────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM modele_produit WHERE ID_PRODUIT = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM produit WHERE ID_PRODUIT = ?")->execute([$id]);
    $message = "Produit supprimé avec succès.";
    $messageType = "success";
    header("Location: produits.php?msg=" . urlencode($message) . "&type=" . $messageType);
    exit;
}

// ── AJOUTER ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nom         = trim($_POST['nom']);
    $desc        = trim($_POST['description']);
    $prix        = (float)$_POST['prix'];
    $id_sc       = (int)$_POST['id_sous_categorie'];
    $en_promo    = isset($_POST['en_promo']) ? 1 : 0;
    $prix_promo  = $en_promo ? (float)$_POST['prix_promo'] : null;
    $date        = date('Y-m-d');

    // Validation des prix (pas de 0 ou négatif)
    if ($prix <= 0) {
        $message = "Le prix doit être supérieur à 0.";
        $messageType = "error";
    } elseif ($en_promo && $prix_promo <= 0) {
        $message = "Le prix promotionnel doit être supérieur à 0.";
        $messageType = "error";
    } else {
        $img1 = uploadImage($_FILES, 'image1');
        if (!$img1) {
            $message = "L'image principale est obligatoire.";
            $messageType = "error";
        } else {
            $img2 = uploadImage($_FILES, 'image2');
            $img3 = uploadImage($_FILES, 'image3');

            $stmt = $pdo->prepare("INSERT INTO produit (ID_SOUS_CATEGORIE, NOM_PRODUIT, IMAGE1, IMAGE2, IMAGE3, DESCRIPTION, PRIX, EN_PROMO, PRIX_PROMO, DATE_PRODUIT)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_sc, $nom, $img1, $img2, $img3, $desc, $prix, $en_promo, $prix_promo, $date]);
            $id_produit = $pdo->lastInsertId();

            // Modèles (tailles/couleurs)
            if (!empty($_POST['taille']) && is_array($_POST['taille'])) {
                foreach ($_POST['taille'] as $i => $taille) {
                    $couleur  = $_POST['couleur'][$i]   ?? '';
                    $quantite = (int)($_POST['quantite'][$i] ?? 0);
                    if (($taille || $couleur) && $quantite > 0) {  // quantite > 0 obligatoire
                        $pdo->prepare("INSERT INTO modele_produit (ID_PRODUIT, TAILLE, COULEUR, QUANTITE) VALUES (?,?,?,?)")
                            ->execute([$id_produit, $taille, $couleur, $quantite]);
                    }
                }
            }
            $message = "Produit ajouté avec succès.";
            $messageType = "success";
        }
    }
    if ($message) {
        header("Location: produits.php?msg=" . urlencode($message) . "&type=" . $messageType);
        exit;
    }
}

// ── MODIFIER ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id          = (int)$_POST['id_produit'];
    $nom         = trim($_POST['nom']);
    $desc        = trim($_POST['description']);
    $prix        = (float)$_POST['prix'];
    $id_sc       = (int)$_POST['id_sous_categorie'];
    $en_promo    = isset($_POST['en_promo']) ? 1 : 0;
    $prix_promo  = $en_promo ? (float)$_POST['prix_promo'] : null;

    if ($prix <= 0) {
        $message = "Le prix doit être supérieur à 0.";
        $messageType = "error";
    } elseif ($en_promo && $prix_promo <= 0) {
        $message = "Le prix promotionnel doit être supérieur à 0.";
        $messageType = "error";
    } else {
        // Keep old images unless new ones uploaded
        $current = $pdo->prepare("SELECT IMAGE1,IMAGE2,IMAGE3 FROM produit WHERE ID_PRODUIT=?");
        $current->execute([$id]);
        $old = $current->fetch(PDO::FETCH_ASSOC);

        $img1 = uploadImage($_FILES, 'image1') ?? $old['IMAGE1'];
        // Si l'image principale est supprimée (checkbox), on la supprime vraiment
        if (isset($_POST['del_image1']) && $_POST['del_image1'] == '1') {
            $img1 = null;
        }
        $img2 = uploadImage($_FILES, 'image2') ?? $old['IMAGE2'];
        if (isset($_POST['del_image2']) && $_POST['del_image2'] == '1') {
            $img2 = null;
        }
        $img3 = uploadImage($_FILES, 'image3') ?? $old['IMAGE3'];
        if (isset($_POST['del_image3']) && $_POST['del_image3'] == '1') {
            $img3 = null;
        }

        $pdo->prepare("UPDATE produit SET ID_SOUS_CATEGORIE=?, NOM_PRODUIT=?, IMAGE1=?, IMAGE2=?, IMAGE3=?, DESCRIPTION=?, PRIX=?, EN_PROMO=?, PRIX_PROMO=? WHERE ID_PRODUIT=?")
            ->execute([$id_sc, $nom, $img1, $img2, $img3, $desc, $prix, $en_promo, $prix_promo, $id]);

        // Update modèles
        $pdo->prepare("DELETE FROM modele_produit WHERE ID_PRODUIT=?")->execute([$id]);
        if (!empty($_POST['taille']) && is_array($_POST['taille'])) {
            foreach ($_POST['taille'] as $i => $taille) {
                $couleur  = $_POST['couleur'][$i]   ?? '';
                $quantite = (int)($_POST['quantite'][$i] ?? 0);
                if (($taille || $couleur) && $quantite > 0) {
                    $pdo->prepare("INSERT INTO modele_produit (ID_PRODUIT, TAILLE, COULEUR, QUANTITE) VALUES (?,?,?,?)")
                        ->execute([$id, $taille, $couleur, $quantite]);
                }
            }
        }

        $message = "Produit modifié avec succès.";
        $messageType = "success";
    }
    if ($message) {
        header("Location: produits.php?msg=" . urlencode($message) . "&type=" . $messageType);
        exit;
    }
}

// ── CHARGER DONNÉES ───────────────────────────────────────
$produits = $pdo->query("
    SELECT p.*, COALESCE(sc.NOM_SOUS_CATEGORIE,'—') as NOM_SOUS_CATEGORIE, COALESCE(c.NOM_CATEGORIE,'—') as NOM_CATEGORIE
    FROM produit p
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    ORDER BY p.ID_PRODUIT DESC
")->fetchAll(PDO::FETCH_ASSOC);

$sous_categories = $pdo->query("
    SELECT sc.*, COALESCE(c.NOM_CATEGORIE, '(Sans catégorie)') as NOM_CATEGORIE
    FROM sous_categorie sc
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    ORDER BY NOM_CATEGORIE, sc.NOM_SOUS_CATEGORIE
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("
    SELECT ID_CATEGORIE, NOM_CATEGORIE FROM categorie ORDER BY NOM_CATEGORIE
")->fetchAll(PDO::FETCH_ASSOC);

// Charger modèles pour édition
$editProduit = null;
$editModeles = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $s = $pdo->prepare("SELECT p.*, sc.NOM_SOUS_CATEGORIE FROM produit p LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE=sc.ID_SOUS_CATEGORIE WHERE p.ID_PRODUIT=?");
    $s->execute([$editId]);
    $editProduit = $s->fetch(PDO::FETCH_ASSOC);
    $s2 = $pdo->prepare("SELECT * FROM modele_produit WHERE ID_PRODUIT=?");
    $s2->execute([$editId]);
    $editModeles = $s2->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits | Velvet Admin</title>
    <link rel="icon" type="image/png" href="../images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style_admin.css">
    <style>
        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead tr { background: #000; color: #fff; }
        thead th { padding: 12px 14px; text-align: left; font-weight: 500; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #eee; transition: background 0.15s; }
        tbody tr:hover { background: #fafafa; }
        tbody td { padding: 11px 14px; vertical-align: middle; }

        .prod-img { width: 52px; height: 52px; object-fit: cover; border-radius: 8px; background: #eee; }
        .no-img { width: 52px; height: 52px; border-radius: 8px; background: #eee; display:flex; align-items:center; justify-content:center; color:#aaa; font-size:20px; }

        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-promo { background:#fff3cd; color:#856404; }
        .badge-normal { background:#e8f5e9; color:#2e7d32; }

        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:none; cursor:pointer; font-size:13px; font-family:'Inter',sans-serif; font-weight:500; transition:0.2s; text-decoration:none; }
        .btn-black { background:#000; color:#fff; }
        .btn-black:hover { background:#333; }
        .btn-edit { background:#f0f0f0; color:#000; }
        .btn-edit:hover { background:#ddd; }
        .btn-del { background:#fff0f0; color:#c0392b; }
        .btn-del:hover { background:#ffd5d5; }
        .btn-sm { padding:5px 10px; font-size:12px; }

        /* ── FORM ── */
        .form-card { background:#fff; border-radius:12px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:24px; }
        .form-card h3 { font-family:'Anton',sans-serif; font-size:18px; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-grid.three { grid-template-columns:1fr 1fr 1fr; }
        .form-full { grid-column: 1 / -1; }
        label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.4px; }
        input[type=text], input[type=number], input[type=file], textarea, select {
            width:100%; padding:9px 12px; border:1.5px solid #e0e0e0; border-radius:8px;
            font-family:'Inter',sans-serif; font-size:13.5px; outline:none; transition:border 0.2s;
            background:#fafafa;
        }
        input:focus, textarea:focus, select:focus { border-color:#000; background:#fff; }
        textarea { resize:vertical; min-height:80px; }
        .checkbox-row { display:flex; align-items:center; gap:10px; }
        .checkbox-row input[type=checkbox] { width:18px; height:18px; cursor:pointer; }

        /* ── MODÈLES ── */
        .modele-row { display:grid; grid-template-columns:1fr 1fr 80px 36px; gap:10px; align-items:center; margin-bottom:10px; }
        .btn-remove-modele { background:#fff0f0; border:none; color:#c0392b; border-radius:6px; padding:6px 10px; cursor:pointer; font-size:14px; }

        /* ── ALERT (kept for compatibility, hidden via PHP now) ── */
        .alert { display:none; }

        /* ── CATEGORY HEADER ROW ── */
        .cat-header-row td { background:#f5f5f5; }

        /* ── SEARCH BAR ── */
        .toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; flex-wrap:wrap; }
        .search-box { position:relative; flex:1; max-width:300px; }
        .search-box input { padding-left:36px; }
        .search-box i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#aaa; }

        /* ── MODAL OVERLAY ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:#fff; border-radius:14px; padding:32px; width:90%; max-width:700px; max-height:90vh; overflow-y:auto; position:relative; }
        .modal-close { position:absolute; top:14px; right:18px; background:none; border:none; font-size:22px; cursor:pointer; color:#888; }

        /* ── Image wrapper with remove button ── */
        .image-input-wrapper { position: relative; display: inline-block; width: 100%; }
        .image-input-wrapper input { width: calc(100% - 32px); }
        .image-remove-btn {
            position: absolute; right: 0; top: 0;
            background: none; border: none; color: #c0392b; cursor: pointer;
            font-size: 18px; padding: 8px; border-radius: 50%;
            transition: background 0.2s;
        }
        .image-remove-btn:hover { background: #fff0f0; }

        @media(max-width:640px) {
            .form-grid, .form-grid.three { grid-template-columns:1fr; }
            .modele-row { grid-template-columns:1fr 1fr 70px 34px; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="logo-section"><img src="../images/logo2.png" alt="Logo"></div>
        <ul class="menu">
            <li><a href="index.php"          class="<?= $currentPage=='index.php'          ?'active':''?>"><i class="fas fa-chart-line"></i><span> Tableau de bord</span></a></li>
            <li><a href="produits.php"        class="<?= $currentPage=='produits.php'        ?'active':''?>"><i class="fas fa-box"></i><span> Produits</span></a></li>
            <li><a href="categories.php"      class="<?= $currentPage=='categories.php'      ?'active':''?>"><i class="fas fa-tags"></i><span> Catégories</span></a></li>
            <li><a href="comptes.php"         class="<?= $currentPage=='comptes.php'         ?'active':''?>"><i class="fas fa-users"></i><span> Comptes</span></a></li>
            <li><a href="commandes.php"       class="<?= $currentPage=='commandes.php'       ?'active':''?>"><i class="fas fa-shopping-cart"></i><span> Commandes</span></a></li>
            <li><a href="avis.php"            class="<?= $currentPage=='avis.php'            ?'active':''?>"><i class="fas fa-star"></i><span> Avis</span></a></li>
            <li><a href="messages.php"        class="<?= $currentPage=='messages.php'        ?'active':''?>"><i class="fas fa-envelope"></i><span> Messages</span></a></li>
            <li><a href="modifier_profil.php" class="<?= $currentPage=='modifier_profil.php' ?'active':''?>"><i class="fas fa-user-cog"></i><span> Modifier mon profil</span></a></li>
            <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i><span> Déconnexion</span></a></li>
        </ul>
    </div>

    <!-- MAIN -->
    <div class="main-content">
        <h2>Produits</h2>

        <?php if(isset($_GET['msg'])): ?>
        <div id="phpToast" data-msg="<?= htmlspecialchars($_GET['msg']) ?>" data-type="<?= htmlspecialchars($_GET['type'] ?? 'success') ?>"></div>
        <?php endif; ?>

        <!-- ── TOOLBAR ── -->
        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Rechercher un produit…">
            </div>
            <button class="btn btn-black" onclick="openModal('modalAdd')">
                <i class="fas fa-plus"></i> Ajouter un produit
            </button>
        </div>

        <!-- ── TABLE ── -->
        <div class="form-card" style="padding:0; overflow:hidden;">
            <div class="table-wrap">
                <table id="prodTable">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix (DH)</th>
                            <th>Stock (unités)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="prodTbody">
                    <?php if(empty($produits)): ?>
                         <td colspan="6" style="text-align:center;color:#aaa;padding:40px;">Aucun produit pour l'instant.</td>
                    <?php else: ?>
                    <?php
                    // Group products by category
                    $grouped = [];
                    foreach($produits as $p) {
                        $cat = $p['NOM_CATEGORIE'] ?? '—';
                        $grouped[$cat][] = $p;
                    }
                    // Sort: Femmes first, then Hommes, then others
                    uksort($grouped, function($a, $b) {
                        $order = ['Femmes' => 0, 'Hommes' => 1];
                        $oa = $order[$a] ?? 99;
                        $ob = $order[$b] ?? 99;
                        return $oa - $ob;
                    });
                    foreach($grouped as $catName => $catProduits):
                    ?>
                        <tr class="cat-header-row" data-cat="<?= htmlspecialchars($catName) ?>">
                            <td colspan="6" style="background:#f5f5f5;padding:10px 14px;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.8px;color:#444;border-left:4px solid #000;">
                                <i class="fas fa-layer-group" style="margin-right:7px;"></i><?= htmlspecialchars($catName) ?>
                                <span style="font-weight:400;font-size:12px;color:#888;margin-left:8px;">(<?= count($catProduits) ?> produit<?= count($catProduits)>1?'s':'' ?>)</span>
                            </td>
                        </tr>
                    <?php foreach($catProduits as $p):
                        $stockStmt = $pdo->prepare("SELECT SUM(QUANTITE) as total FROM modele_produit WHERE ID_PRODUIT=?");
                        $stockStmt->execute([$p['ID_PRODUIT']]);
                        $stock = (int)($stockStmt->fetchColumn() ?? 0);
                    ?>
                        <tr class="prod-row" data-cat="<?= htmlspecialchars($catName) ?>">
                            <td>
                                <?php if($p['IMAGE1']): ?>
                                    <img src="../<?= htmlspecialchars($p['IMAGE1'] ?? '') ?>" class="prod-img" alt="" onerror="this.style.display='none';">
                                <?php else: ?>
                                    <div class="no-img"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($p['NOM_PRODUIT'] ?? '') ?></strong></td>
                            <td>
                                <span style="color:#888;font-size:12px;"><?= htmlspecialchars($p['NOM_SOUS_CATEGORIE'] ?? '—') ?></span>
                            </td>
                            <td>
                                <?php if($p['EN_PROMO'] && $p['PRIX_PROMO']): ?>
                                    <span style="text-decoration:line-through;color:#aaa;font-size:12px;"><?= number_format((float)$p['PRIX'],2,',',' ') ?></span>
                                    <strong style="color:#c0392b;margin-left:6px;"><?= number_format((float)$p['PRIX_PROMO'],2,',',' ') ?></strong>
                                <?php else: ?>
                                    <strong><?= number_format((float)($p['PRIX'] ?? 0),2,',',' ') ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($stock > 0): ?>
                                    <span style="color:#2e7d32;font-weight:600;"><?= $stock ?></span>
                                <?php else: ?>
                                    <span style="color:#c0392b;font-weight:600;">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="?edit=<?= $p['ID_PRODUIT'] ?>" class="btn btn-edit btn-sm" title="Modifier"><i class="fas fa-pen"></i></a>
                                <a href="?delete=<?= $p['ID_PRODUIT'] ?>" class="btn btn-del btn-sm" onclick="return confirm('Supprimer ce produit ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div id="paginationControls" style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:18px;flex-wrap:wrap;"></div>

    </div><!-- /main-content -->
</div><!-- /container -->

<!-- ══════════════════════════════════════════════════════ -->
<!-- MODAL : AJOUTER -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalAdd">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('modalAdd')"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Anton',sans-serif;font-size:20px;margin-bottom:22px;"><i class="fas fa-plus-circle"></i> Ajouter un produit</h3>

        <form method="POST" enctype="multipart/form-data" id="formAdd" novalidate>
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div>
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" required placeholder="Ex: Veste en cuir">
                </div>
                <div>
                    <label>Catégorie *</label>
                    <select id="cat_add" onchange="filterSousCats('cat_add','sc_add')" required>
                        <option value="">— Choisir —</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?= $c['ID_CATEGORIE'] ?>"><?= htmlspecialchars($c['NOM_CATEGORIE']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Sous-catégorie *</label>
                    <select name="id_sous_categorie" id="sc_add" required>
                        <option value="">— Choisir d'abord une catégorie —</option>
                        <?php foreach($sous_categories as $sc): ?>
                        <option value="<?= $sc['ID_SOUS_CATEGORIE'] ?>" data-cat="<?= $sc['ID_CATEGORIE'] ?>" style="display:none;">
                            <?= htmlspecialchars($sc['NOM_SOUS_CATEGORIE'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Prix (DH) *</label>
                    <input type="number" name="prix" step="0.01" min="0.01" required placeholder="Ex: 299.00">
                </div>
                <div>
                    <label>Description *</label>
                    <textarea name="description" required placeholder="Décrivez le produit…"></textarea>
                </div>
                <div>
                    <label>Image principale *</label>
                    <div class="image-input-wrapper">
                        <input type="file" name="image1" accept="image/*" required>
                    </div>
                </div>
                <!-- Images 2 & 3 hidden by default -->
                <div id="img2_wrap" style="display:none;">
                    <label>Image 2</label>
                    <div class="image-input-wrapper">
                        <input type="file" name="image2" accept="image/*">
                        <button type="button" class="image-remove-btn" onclick="removeImageInput('img2_wrap')"><i class="fas fa-times-circle"></i></button>
                    </div>
                </div>
                <div id="img3_wrap" class="form-full" style="display:none;">
                    <label>Image 3</label>
                    <div class="image-input-wrapper">
                        <input type="file" name="image3" accept="image/*">
                        <button type="button" class="image-remove-btn" onclick="removeImageInput('img3_wrap')"><i class="fas fa-times-circle"></i></button>
                    </div>
                </div>
                <div class="form-full" id="addImgBtnWrap">
                    <button type="button" class="btn btn-edit btn-sm" onclick="addNextImage()" id="addImgBtn">
                        <i class="fas fa-image"></i> Ajouter une image
                    </button>
                </div>
                <div class="form-full">
                    <div class="checkbox-row">
                        <input type="checkbox" name="en_promo" id="promo_add" onchange="togglePromo(this,'promo_prix_add')">
                        <label for="promo_add" style="margin:0;text-transform:none;font-size:14px;">Ce produit est en promotion</label>
                    </div>
                </div>
                <div id="promo_prix_add" style="display:none;">
                    <label>Prix promo (DH)</label>
                    <input type="number" name="prix_promo" step="0.01" min="0.01">
                </div>
            </div>

            <!-- Modèles -->
            <div style="margin-top:22px;">
                <label style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Stock — Tailles & Couleurs</label>
                <div id="modeles_add" style="margin-top:10px;">
                    <div class="modele-row">
                        <select name="taille[]" required>
                            <option value="">Choisir taille</option>
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="XXXL">XXXL</option>
                        </select>
                        <input type="text" name="couleur[]" placeholder="Couleur" required>
                        <input type="number" name="quantite[]" placeholder="Qté" min="1" value="1" required>
                    </div>
                </div>
                <button type="button" class="btn btn-edit btn-sm" style="margin-top:8px;" onclick="addModeleRow('modeles_add')">
                    <i class="fas fa-plus"></i> Ajouter taille/couleur
                </button>
            </div>

            <div style="margin-top:24px;text-align:right;">
                <button type="button" class="btn btn-edit" onclick="closeModal('modalAdd')" style="margin-right:8px;">Annuler</button>
                <button type="submit" class="btn btn-black"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- MODAL : MODIFIER (s'ouvre automatiquement si ?edit=X) -->
<!-- ══════════════════════════════════════════════════════ -->
<?php if($editProduit): ?>
<div class="modal-overlay open" id="modalEdit">
    <div class="modal">
        <button class="modal-close" onclick="window.location='produits.php'"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Anton',sans-serif;font-size:20px;margin-bottom:22px;"><i class="fas fa-pen"></i> Modifier le produit</h3>

        <form method="POST" enctype="multipart/form-data" id="formEdit">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_produit" value="<?= $editProduit['ID_PRODUIT'] ?>">
            <div class="form-grid">
                <div>
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($editProduit['NOM_PRODUIT'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Sous-catégorie *</label>
                    <select name="id_sous_categorie" required>
                        <?php foreach($sous_categories as $sc): ?>
                        <option value="<?= $sc['ID_SOUS_CATEGORIE'] ?>" <?= $sc['ID_SOUS_CATEGORIE']==$editProduit['ID_SOUS_CATEGORIE']?'selected':'' ?>>
                            <?= htmlspecialchars($sc['NOM_CATEGORIE'] ?? '') ?> › <?= htmlspecialchars($sc['NOM_SOUS_CATEGORIE'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Prix (DH) *</label>
                    <input type="number" name="prix" step="0.01" min="0.01" value="<?= $editProduit['PRIX'] ?>" required>
                </div>
                <div>
                    <label>Description</label>
                    <textarea name="description"><?= htmlspecialchars($editProduit['DESCRIPTION'] ?? '') ?></textarea>
                </div>
                <div>
                    <label>Image principale <?= $editProduit['IMAGE1']?'(laisser vide = garder)':'' ?></label>
                    <?php if($editProduit['IMAGE1']): ?>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="../<?= htmlspecialchars($editProduit['IMAGE1'] ?? '') ?>" style="height:50px;border-radius:6px;">
                            <label><input type="checkbox" name="del_image1" value="1"> Supprimer cette image</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image1" accept="image/*">
                </div>
                <div>
                    <label>Image 2</label>
                    <?php if($editProduit['IMAGE2']): ?>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="../<?= htmlspecialchars($editProduit['IMAGE2'] ?? '') ?>" style="height:50px;border-radius:6px;">
                            <label><input type="checkbox" name="del_image2" value="1"> Supprimer cette image</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image2" accept="image/*">
                </div>
                <div class="form-full">
                    <label>Image 3</label>
                    <?php if($editProduit['IMAGE3']): ?>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="../<?= htmlspecialchars($editProduit['IMAGE3'] ?? '') ?>" style="height:50px;border-radius:6px;">
                            <label><input type="checkbox" name="del_image3" value="1"> Supprimer cette image</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image3" accept="image/*">
                </div>
                <div class="form-full">
                    <div class="checkbox-row">
                        <input type="checkbox" name="en_promo" id="promo_edit" <?= $editProduit['EN_PROMO']?'checked':'' ?> onchange="togglePromo(this,'promo_prix_edit')">
                        <label for="promo_edit" style="margin:0;text-transform:none;font-size:14px;">Ce produit est en promotion</label>
                    </div>
                </div>
                <div id="promo_prix_edit" style="display:<?= $editProduit['EN_PROMO']?'block':'none' ?>;">
                    <label>Prix promo (DH)</label>
                    <input type="number" name="prix_promo" step="0.01" min="0.01" value="<?= $editProduit['PRIX_PROMO'] ?>">
                </div>
            </div>

            <!-- Modèles existants -->
            <div style="margin-top:22px;">
                <label style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Stock — Tailles & Couleurs</label>
                <div id="modeles_edit" style="margin-top:10px;">
                    <?php if(empty($editModeles)): ?>
                    <div class="modele-row">
                        <select name="taille[]">
                            <option value="">Choisir taille</option>
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="XXXL">XXXL</option>
                        </select>
                        <input type="text" name="couleur[]" placeholder="Couleur">
                        <input type="number" name="quantite[]" placeholder="Qté" min="0" value="0">
                        <button type="button" class="btn-remove-modele" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    </div>
                    <?php else: ?>
                    <?php foreach($editModeles as $m): ?>
                    <div class="modele-row">
                        <select name="taille[]">
                            <option value="">Choisir taille</option>
                            <option value="XS" <?= $m['TAILLE']=='XS'?'selected':'' ?>>XS</option>
                            <option value="S"  <?= $m['TAILLE']=='S'?'selected':'' ?>>S</option>
                            <option value="M"  <?= $m['TAILLE']=='M'?'selected':'' ?>>M</option>
                            <option value="L"  <?= $m['TAILLE']=='L'?'selected':'' ?>>L</option>
                            <option value="XL" <?= $m['TAILLE']=='XL'?'selected':'' ?>>XL</option>
                            <option value="XXL" <?= $m['TAILLE']=='XXL'?'selected':'' ?>>XXL</option>
                            <option value="XXXL" <?= $m['TAILLE']=='XXXL'?'selected':'' ?>>XXXL</option>
                        </select>
                        <input type="text" name="couleur[]" value="<?= htmlspecialchars($m['COULEUR'] ?? '') ?>">
                        <input type="number" name="quantite[]" min="0" value="<?= (int)$m['QUANTITE'] ?>">
                        <button type="button" class="btn-remove-modele" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-edit btn-sm" style="margin-top:8px;" onclick="addModeleRow('modeles_edit')">
                    <i class="fas fa-plus"></i> Ajouter taille/couleur
                </button>
            </div>

            <div style="margin-top:24px;text-align:right;">
                <a href="produits.php" class="btn btn-edit" style="margin-right:8px;">Annuler</a>
                <button type="submit" class="btn btn-black"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ── TOAST NOTIFICATION (en haut, 5 secondes) ─────────────
function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.cssText = `
        position:fixed; top: 20px; left:50%; transform:translateX(-50%) translateY(-20px);
        background:${type==='success'?'#2e7d32':'#c62828'}; color:#fff;
        padding:13px 26px; border-radius:50px; font-size:14px; font-weight:500;
        box-shadow:0 4px 18px rgba(0,0,0,0.18); z-index:9999;
        opacity:0; transition:opacity 0.3s, transform 0.3s;
        font-family:'Inter',sans-serif; letter-spacing:0.2px;
        display:flex; align-items:center; gap:9px;
    `;
    const icon = document.createElement('i');
    icon.className = type==='success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    toast.prepend(icon);
    document.body.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
    });
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(-20px)';
        setTimeout(() => toast.remove(), 350);
    }, 5000); // 5 secondes
}

// Auto-show PHP toast
window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('phpToast');
    if (el) showToast(el.dataset.msg, el.dataset.type);
});

// ── MODAL ─────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) {
            if (overlay.id === 'modalEdit') window.location = 'produits.php';
            else overlay.classList.remove('open');
        }
    });
});

// ── PROMO TOGGLE ──────────────────────────────────────────
function togglePromo(checkbox, targetId) {
    document.getElementById(targetId).style.display = checkbox.checked ? 'block' : 'none';
}

// ── ADD EXTRA IMAGES (sequential) avec bouton supprimer ──
let imagesAdded = 0;
function addNextImage() {
    if (imagesAdded === 0) {
        document.getElementById('img2_wrap').style.display = 'block';
        imagesAdded = 1;
    } else if (imagesAdded === 1) {
        document.getElementById('img3_wrap').style.display = 'block';
        document.getElementById('addImgBtn').style.display = 'none';
        imagesAdded = 2;
    }
}
function removeImageInput(wrapId) {
    const wrap = document.getElementById(wrapId);
    if (wrap) {
        wrap.style.display = 'none';
        const input = wrap.querySelector('input[type="file"]');
        if (input) input.value = '';
        // Re-allow adding image again if needed (optional)
        if (wrapId === 'img3_wrap') {
            imagesAdded = 1;
            document.getElementById('addImgBtn').style.display = 'inline-flex';
        } else if (wrapId === 'img2_wrap') {
            imagesAdded = 0;
            document.getElementById('addImgBtn').style.display = 'inline-flex';
        }
    }
}

// ── FILTER SOUS-CATEGORIES ────────────────────────────────
function filterSousCats(catSelectId, scSelectId) {
    const catId = document.getElementById(catSelectId).value;
    const sc = document.getElementById(scSelectId);
    sc.value = '';
    sc.options[0].text = catId ? '— Choisir —' : '— Choisir d\'abord une catégorie —';
    [...sc.options].forEach((opt, i) => {
        if (i === 0) return;
        const show = opt.dataset.cat === catId;
        opt.style.display = show ? '' : 'none';
        if (!show && opt.selected) opt.selected = false;
    });
}

// ── ADD MODÈLE ROW (avec select pour taille) ──────────────
function addModeleRow(containerId) {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'modele-row';
    div.innerHTML = `
        <select name="taille[]" required>
            <option value="">Choisir taille</option>
            <option value="XS">XS</option>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
            <option value="XXL">XXL</option>
            <option value="XXXL">XXXL</option>
        </select>
        <input type="text" name="couleur[]" placeholder="Couleur" required>
        <input type="number" name="quantite[]" placeholder="Qté" min="1" value="1" required>
        <button type="button" class="btn-remove-modele" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

function removeRow(btn) {
    btn.closest('.modele-row').remove();
}

// ── FORM VALIDATION (custom toast errors) ─────────────────
document.getElementById('formAdd')?.addEventListener('submit', function(e) {
    const fields = [
        { el: this.querySelector('[name="nom"]'), label: 'Nom du produit' },
        { el: this.querySelector('#cat_add'), label: 'Catégorie' },
        { el: this.querySelector('[name="id_sous_categorie"]'), label: 'Sous-catégorie' },
        { el: this.querySelector('[name="prix"]'), label: 'Prix' },
        { el: this.querySelector('[name="description"]'), label: 'Description' },
        { el: this.querySelector('[name="image1"]'), label: 'Image principale' },
    ];
    for (const f of fields) {
        if (!f.el) continue;
        if (!f.el.value || (f.el.tagName==='SELECT' && f.el.value==='')) {
            e.preventDefault();
            f.el.focus();
            showToast(`Champ obligatoire : ${f.label}`, 'error');
            return;
        }
    }
    // Check prix > 0
    const prix = parseFloat(this.querySelector('[name="prix"]').value);
    if (prix <= 0) {
        e.preventDefault();
        showToast('Le prix doit être supérieur à 0.', 'error');
        return;
    }
    const promoCheck = this.querySelector('[name="en_promo"]');
    if (promoCheck && promoCheck.checked) {
        const prixPromo = parseFloat(this.querySelector('[name="prix_promo"]').value);
        if (isNaN(prixPromo) || prixPromo <= 0) {
            e.preventDefault();
            showToast('Le prix promotionnel doit être supérieur à 0.', 'error');
            return;
        }
    }
    // Check taille/couleur rows
    const tailles = this.querySelectorAll('[name="taille[]"]');
    const couleurs = this.querySelectorAll('[name="couleur[]"]');
    const qtys = this.querySelectorAll('[name="quantite[]"]');
    for (let i = 0; i < tailles.length; i++) {
        if (!tailles[i].value) { e.preventDefault(); tailles[i].focus(); showToast('Veuillez choisir une taille pour chaque ligne de stock', 'error'); return; }
        if (!couleurs[i].value.trim()) { e.preventDefault(); couleurs[i].focus(); showToast('Couleur obligatoire pour chaque ligne de stock', 'error'); return; }
        if (!qtys[i].value || parseInt(qtys[i].value) < 1) { e.preventDefault(); qtys[i].focus(); showToast('La quantité doit être au moins 1', 'error'); return; }
    }
});

// Validation pour le formulaire d'édition
document.getElementById('formEdit')?.addEventListener('submit', function(e) {
    const prix = parseFloat(this.querySelector('[name="prix"]').value);
    if (prix <= 0) {
        e.preventDefault();
        showToast('Le prix doit être supérieur à 0.', 'error');
        return;
    }
    const promoCheck = this.querySelector('[name="en_promo"]');
    if (promoCheck && promoCheck.checked) {
        const prixPromo = parseFloat(this.querySelector('[name="prix_promo"]').value);
        if (isNaN(prixPromo) || prixPromo <= 0) {
            e.preventDefault();
            showToast('Le prix promotionnel doit être supérieur à 0.', 'error');
            return;
        }
    }
    const tailles = this.querySelectorAll('[name="taille[]"]');
    const couleurs = this.querySelectorAll('[name="couleur[]"]');
    const qtys = this.querySelectorAll('[name="quantite[]"]');
    for (let i = 0; i < tailles.length; i++) {
        if (!tailles[i].value) { e.preventDefault(); tailles[i].focus(); showToast('Veuillez choisir une taille pour chaque ligne de stock', 'error'); return; }
        if (!couleurs[i].value.trim()) { e.preventDefault(); couleurs[i].focus(); showToast('Couleur obligatoire pour chaque ligne de stock', 'error'); return; }
        if (!qtys[i].value || parseInt(qtys[i].value) < 1) { e.preventDefault(); qtys[i].focus(); showToast('La quantité doit être au moins 1', 'error'); return; }
    }
});

// ── PAGINATION (10 produits par page) ─────────────────────
const ROWS_PER_PAGE = 10;
let currentPage = 1;

function getAllVisibleRows() {
    return [...document.querySelectorAll('#prodTbody .prod-row, #prodTbody .cat-header-row')];
}

function getProdRows() {
    return [...document.querySelectorAll('#prodTbody .prod-row')];
}

function applySearch(q) {
    currentPage = 1;
    const allProd = [...document.querySelectorAll('#prodTbody .prod-row')];
    allProd.forEach(tr => {
        const match = !q || tr.textContent.toLowerCase().includes(q);
        tr.dataset.searchHide = match ? '' : '1';
    });
    renderPage();
}

function renderPage() {
    const prodRows = [...document.querySelectorAll('#prodTbody .prod-row')].filter(r => !r.dataset.searchHide);
    const catHeaders = [...document.querySelectorAll('#prodTbody .cat-header-row')];

    // Hide all first
    document.querySelectorAll('#prodTbody .prod-row, #prodTbody .cat-header-row').forEach(r => r.style.display='none');

    const total = prodRows.length;
    const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * ROWS_PER_PAGE;
    const pageRows = prodRows.slice(start, start + ROWS_PER_PAGE);

    // Show page rows
    pageRows.forEach(r => r.style.display = '');

    // Show category headers if they have visible rows on this page
    const catsOnPage = new Set(pageRows.map(r => r.dataset.cat));
    catHeaders.forEach(h => {
        if (catsOnPage.has(h.dataset.cat)) h.style.display = '';
    });

    // Build pagination UI
    const pc = document.getElementById('paginationControls');
    pc.innerHTML = '';
    if (totalPages <= 1) return;

    const makeBtn = (label, page, active, disabled) => {
        const b = document.createElement('button');
        b.innerHTML = label;
        b.style.cssText = `padding:6px 13px;border-radius:7px;border:1.5px solid ${active?'#000':'#ddd'};
            background:${active?'#000':'#fff'};color:${active?'#fff':'#333'};cursor:${disabled?'not-allowed':'pointer'};
            font-size:13px;font-family:'Inter',sans-serif;opacity:${disabled?'0.4':'1'};`;
        if (!disabled) b.onclick = () => { currentPage = page; renderPage(); };
        return b;
    };

    pc.appendChild(makeBtn('<i class="fas fa-chevron-left"></i>', currentPage-1, false, currentPage===1));
    for (let i=1;i<=totalPages;i++) {
        pc.appendChild(makeBtn(i, i, i===currentPage, false));
    }
    pc.appendChild(makeBtn('<i class="fas fa-chevron-right"></i>', currentPage+1, false, currentPage===totalPages));
}

// ── SEARCH ────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function() {
    applySearch(this.value.toLowerCase().trim());
});

// Init pagination on load
window.addEventListener('DOMContentLoaded', renderPage);
</script>
</body>
</html>