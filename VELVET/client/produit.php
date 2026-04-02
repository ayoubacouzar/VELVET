<?php
// ════════════════════════════════════════════════════════════════
//  produit_detail.php — Page détail produit
//  BDD : bd_velvet
//  URL : produit_detail.php?id=44
// ════════════════════════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../db.php';

// ── Récupérer l'ID produit ────────────────────────────────────────
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// ── Produit + sous-catégorie ──────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT p.*, sc.NOM_SOUS_CATEGORIE, c.NOM_CATEGORIE
        FROM produit p
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
        WHERE p.ID_PRODUIT = ?
    ");
    $stmt->execute([$id]);
    $produit = $stmt->fetch();
} catch (PDOException $e) {
    $produit = null;
}

if (!$produit) {
    header('Location: index.php');
    exit;
}

// ── Tailles + couleurs + stock ────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT TAILLE, COULEUR, QUANTITE
        FROM modele_produit
        WHERE ID_PRODUIT = ?
        ORDER BY FIELD(TAILLE,'XS','S','M','L','XL','XXL'), TAILLE
    ");
    $stmt->execute([$id]);
    $modeles = $stmt->fetchAll();
} catch (PDOException $e) {
    $modeles = [];
}

// ── Grouper couleurs uniques ──────────────────────────────────────
$couleurs = array_unique(array_column($modeles, 'COULEUR'));
$couleurs = array_filter($couleurs);

// ── Tailles disponibles ───────────────────────────────────────────
$tailles_dispo = [];
foreach ($modeles as $m) {
    $tailles_dispo[$m['TAILLE']] = [
        'quantite' => $m['QUANTITE'],
        'couleur'  => $m['COULEUR'],
    ];
}

// ── Images disponibles — chemins corrigés pour client/
$images = array_filter([
    $produit['IMAGE1'] ? '../' . $produit['IMAGE1'] : null,
    $produit['IMAGE2'] ? '../' . $produit['IMAGE2'] : null,
    $produit['IMAGE3'] ? '../' . $produit['IMAGE3'] : null,
]);
if (empty($images)) $images = ['../images/VELVET_LOGO_blanc.png'];
$images = array_values($images);

// ── 4 produits similaires (même sous-catégorie) ───────────────────
try {
    $stmt = $pdo->prepare("
        SELECT p.ID_PRODUIT, p.NOM_PRODUIT, p.IMAGE1, p.PRIX, p.EN_PROMO, p.PRIX_PROMO
        FROM produit p
        WHERE p.ID_SOUS_CATEGORIE = ? AND p.ID_PRODUIT != ?
        ORDER BY p.ID_PRODUIT DESC
        LIMIT 4
    ");
    $stmt->execute([$produit['ID_SOUS_CATEGORIE'], $id]);
    $similaires = $stmt->fetchAll();
} catch (PDOException $e) {
    $similaires = [];
}

// ── Prix affiché ──────────────────────────────────────────────────
$prix_final  = (!empty($produit['EN_PROMO']) && !empty($produit['PRIX_PROMO']))
    ? $produit['PRIX_PROMO']
    : $produit['PRIX'];
$en_promo    = !empty($produit['EN_PROMO']) && !empty($produit['PRIX_PROMO']);
$reduction   = $en_promo
    ? round((1 - $produit['PRIX_PROMO'] / $produit['PRIX']) * 100)
    : 0;

// ── Stock total ───────────────────────────────────────────────────
$stock_total = array_sum(array_column($modeles, 'QUANTITE'));

// ── Est-ce que le client a ce produit en favori ? ─────────────────
$isFavori = false;
if (!empty($_SESSION['client_id'])) {
    $stmtFv = $pdo->prepare("SELECT 1 FROM aime WHERE ID_CLIENT = ? AND ID_PRODUIT = ?");
    $stmtFv->execute([$_SESSION['client_id'], $id]);
    $isFavori = (bool)$stmtFv->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produit['NOM_PRODUIT']) ?> — Velvet Fashion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* ════════════════════════════════
           VARIABLES & BASE
        ════════════════════════════════ */
        :root {
            --noir: #0a0a0a;
            --blanc: #fafaf8;
            --gris: #f2f1ef;
            --gris-mid: #e8e6e3;
            --gris-text: #888;
            --accent: #c9a84c;
            --rouge: #e63946;
            --radius: 12px;
            --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--blanc); color: var(--noir); }

        /* ════ BREADCRUMB ════ */
        .breadcrumb-velvet {
            background: var(--gris);
            padding: 14px 0;
            border-bottom: 1px solid var(--gris-mid);
        }
        .breadcrumb-velvet .container { display: flex; align-items: center; gap: 8px; }
        .breadcrumb-velvet a {
            color: var(--gris-text); text-decoration: none;
            font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase;
            transition: color 0.2s;
        }
        .breadcrumb-velvet a:hover { color: var(--noir); }
        .breadcrumb-velvet span {
            color: var(--noir); font-size: 11px;
            letter-spacing: 1.5px; text-transform: uppercase; font-weight: 600;
        }
        .breadcrumb-velvet i { font-size: 7px; color: #ccc; }

        /* ════ SECTION PRINCIPALE ════ */
        .detail-section { padding: 60px 0 80px; }

        /* ════ GALERIE PHOTOS ════ */
        .gallery-wrap { position: sticky; top: 90px; }

        .main-photo {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            background: var(--gris);
            aspect-ratio: 3/4;
            cursor: zoom-in;
        }
        .main-photo img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            display: block;
        }
        .main-photo:hover img { transform: scale(1.04); }

        /* Badge sur la photo */
        .photo-badge {
            position: absolute; top: 20px; left: 20px; z-index: 2;
            display: flex; flex-direction: column; gap: 6px;
        }
        .badge-pill {
            padding: 5px 14px; border-radius: 30px;
            font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; display: inline-block;
        }
        .badge-pill.new    { background: var(--noir); color: #fff; }
        .badge-pill.promo  { background: var(--rouge); color: #fff; }
        .badge-pill.dispo  { background: rgba(255,255,255,0.92); color: var(--noir);
                             backdrop-filter: blur(4px); }

        /* Thumbnails */
        .thumbs-wrap {
            display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap;
        }
        .thumb {
            width: 76px; height: 96px; border-radius: 10px;
            overflow: hidden; cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
            background: var(--gris);
            flex-shrink: 0;
        }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .thumb.active { border-color: var(--noir); }
        .thumb:hover { border-color: var(--gris-mid); transform: translateY(-2px); }
        .thumb.active:hover { border-color: var(--noir); }

        /* ════ INFOS PRODUIT ════ */
        .product-details { padding-left: 30px; }

        .detail-category {
            font-size: 10px; letter-spacing: 3px; text-transform: uppercase;
            color: var(--gris-text); margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .detail-category::before {
            content: ''; display: inline-block;
            width: 24px; height: 1px; background: var(--gris-text);
        }

        .detail-title {
            font-family: 'Anton', sans-serif;
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            line-height: 1.05; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 20px;
            color: var(--noir);
        }

        /* ── Prix ── */
        .detail-price-wrap { display: flex; align-items: baseline; gap: 14px; margin-bottom: 24px; }
        .detail-price {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.6rem; font-weight: 600; color: var(--noir);
            line-height: 1;
        }
        .detail-price.promo { color: var(--rouge); }
        .detail-price-old {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem; color: #bbb;
            text-decoration: line-through; font-weight: 300;
        }
        .badge-reduction {
            background: var(--rouge); color: #fff;
            font-size: 11px; font-weight: 700; letter-spacing: 1px;
            padding: 4px 10px; border-radius: 6px;
        }

        /* ── Divider ── */
        .divider { height: 1px; background: var(--gris-mid); margin: 24px 0; }

        /* ── Description ── */
        .detail-description {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.1rem; line-height: 1.8;
            color: #555; font-style: italic;
            margin-bottom: 28px;
        }

        /* ── Couleurs ── */
        .section-label {
            font-size: 10px; letter-spacing: 2.5px; text-transform: uppercase;
            color: var(--gris-text); margin-bottom: 12px; font-weight: 600;
        }
        .couleurs-wrap { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 28px; }
        .couleur-dot {
            width: 32px; height: 32px; border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer; transition: var(--transition);
            position: relative;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }
        .couleur-dot:hover { transform: scale(1.15); }
        .couleur-dot.active {
            border-color: var(--noir);
            box-shadow: 0 0 0 3px var(--blanc), 0 0 0 5px var(--noir);
        }
        .couleur-dot[title]::after {
            content: attr(title);
            position: absolute; bottom: -22px; left: 50%;
            transform: translateX(-50%);
            font-size: 9px; letter-spacing: 1px; text-transform: uppercase;
            color: var(--gris-text); white-space: nowrap; opacity: 0;
            transition: opacity 0.2s;
        }
        .couleur-dot:hover::after { opacity: 1; }

        /* ── Tailles ── */
        .tailles-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 12px;
        }
        .guide-taille {
            font-size: 10px; letter-spacing: 1px; text-transform: uppercase;
            color: var(--gris-text); text-decoration: underline;
            cursor: pointer; transition: color 0.2s;
        }
        .guide-taille:hover { color: var(--noir); }

        .tailles-wrap { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .taille-btn {
            min-width: 52px; height: 48px;
            border: 1.5px solid var(--gris-mid);
            border-radius: 10px;
            background: #fff; color: var(--noir);
            font-family: 'Inter', sans-serif;
            font-size: 12px; font-weight: 600; letter-spacing: 0.5px;
            cursor: pointer; transition: var(--transition);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 2px; padding: 0 12px;
        }
        .taille-btn:hover:not(.epuise) {
            border-color: var(--noir); background: var(--noir); color: #fff;
        }
        .taille-btn.active {
            border-color: var(--noir); background: var(--noir); color: #fff;
        }
        .taille-btn.epuise {
            color: #ccc; border-color: #eee;
            cursor: not-allowed; background: #fafafa;
            text-decoration: line-through;
        }
        .taille-stock {
            font-size: 8px; font-weight: 400;
            letter-spacing: 0.5px; opacity: 0.7;
        }
        .taille-btn.low-stock { border-color: #ffb347; }
        .taille-btn.low-stock.active { background: #ff8c00; border-color: #ff8c00; }

        /* Info stock ── */
        .stock-status {
            font-size: 11px; margin-top: 10px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 6px;
            min-height: 18px;
        }
        .stock-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .stock-dot.green  { background: #2ecc71; }
        .stock-dot.orange { background: #f39c12; }
        .stock-dot.red    { background: var(--rouge); }

        /* ── Boutons action ── */
        .actions-wrap { display: flex; gap: 12px; margin-bottom: 20px; }
        .btn-add-cart {
            flex: 1; height: 56px;
            background: var(--noir); color: #fff;
            border: none; border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-weight: 700; font-size: 13px;
            letter-spacing: 2px; text-transform: uppercase;
            cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-add-cart:hover {
            background: #222;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }
        .btn-add-cart:active { transform: translateY(0); }
        .btn-add-cart i { font-size: 16px; }

        .btn-wishlist-detail {
            width: 56px; height: 56px;
            border: 1.5px solid var(--gris-mid);
            border-radius: 12px; background: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: var(--transition);
            flex-shrink: 0;
        }
        .btn-wishlist-detail i { font-size: 18px; color: #aaa; transition: var(--transition); }
        .btn-wishlist-detail:hover { border-color: var(--rouge); }
        .btn-wishlist-detail:hover i { color: var(--rouge); }
        .btn-wishlist-detail.active { border-color: var(--rouge); background: #fff5f5; }
        .btn-wishlist-detail.active i { color: var(--rouge); font-weight: 900; }

        /* ── Livraison info ── */
        .livraison-info {
            background: var(--gris); border-radius: 12px;
            padding: 16px 20px; margin-bottom: 24px;
            display: flex; flex-direction: column; gap: 10px;
        }
        .livraison-item {
            display: flex; align-items: center; gap: 12px;
            font-size: 12px; color: #555;
        }
        .livraison-item i { font-size: 15px; color: var(--noir); width: 18px; text-align: center; }

        /* ── Accordéon description ── */
        .accord-item {
            border-top: 1px solid var(--gris-mid);
        }
        .accord-item:last-child { border-bottom: 1px solid var(--gris-mid); }
        .accord-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 0; cursor: pointer;
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            font-weight: 600; color: var(--noir);
            transition: color 0.2s;
        }
        .accord-header:hover { color: var(--gris-text); }
        .accord-header i { font-size: 10px; transition: transform 0.3s; }
        .accord-header.open i { transform: rotate(180deg); }
        .accord-body {
            overflow: hidden; max-height: 0;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .accord-body.open { max-height: 300px; }
        .accord-content {
            padding: 0 0 20px;
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.05rem; line-height: 1.8; color: #666;
        }

        /* ════ PRODUITS SIMILAIRES ════ */
        .similaires-section { padding: 60px 0; background: var(--gris); }
        .similaires-section h2 {
            font-family: 'Anton', sans-serif;
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            text-transform: uppercase; letter-spacing: 2px;
            margin-bottom: 40px; text-align: center;
        }
        .sim-card {
            background: #fff; border-radius: 14px; overflow: hidden;
            transition: var(--transition); text-decoration: none; display: block;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }
        .sim-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
        .sim-card img { width: 100%; height: 260px; object-fit: cover; display: block; transition: transform 0.5s ease; }
        .sim-card:hover img { transform: scale(1.05); }
        .sim-card-body { padding: 16px; }
        .sim-card-name { font-weight: 700; font-size: 0.9rem; color: var(--noir); margin-bottom: 4px; }
        .sim-card-price { font-weight: 800; font-size: 0.95rem; color: var(--noir); }

        /* ════ ANIMATION ENTRÉE ════ */
        .fade-in-up {
            opacity: 0; transform: translateY(30px);
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

        /* ════ ZOOM OVERLAY ════ */
        .zoom-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.92); z-index: 9999;
            align-items: center; justify-content: center;
            cursor: zoom-out;
        }
        .zoom-overlay.open { display: flex; }
        .zoom-overlay img {
            max-width: 90vw; max-height: 90vh;
            object-fit: contain; border-radius: 8px;
        }
        .zoom-close {
            position: fixed; top: 20px; right: 24px;
            background: none; border: none; color: #fff;
            font-size: 28px; cursor: pointer; z-index: 10000;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .product-details { padding-left: 0; padding-top: 30px; }
            .gallery-wrap { position: static; }
            .detail-title { font-size: 1.8rem; }
            .detail-price { font-size: 2rem; }
        }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>

<!-- ════ ZOOM OVERLAY ════ -->
<div class="zoom-overlay" id="zoomOverlay" onclick="closeZoom()">
    <button class="zoom-close" onclick="closeZoom()"><i class="fas fa-times"></i></button>
    <img src="" id="zoomImg" alt="">
</div>

<!-- ════ BREADCRUMB ════ -->
<div class="breadcrumb-velvet">
    <div class="container">
        <a href="index.php">Accueil</a>
        <i class="fa fa-chevron-right"></i>
        <?php if (!empty($produit['NOM_CATEGORIE'])): ?>
            <a href="index.php"><?= htmlspecialchars($produit['NOM_CATEGORIE']) ?></a>
            <i class="fa fa-chevron-right"></i>
        <?php endif; ?>
        <?php if (!empty($produit['NOM_SOUS_CATEGORIE'])): ?>
            <a href="index.php"><?= htmlspecialchars($produit['NOM_SOUS_CATEGORIE']) ?></a>
            <i class="fa fa-chevron-right"></i>
        <?php endif; ?>
        <span><?= htmlspecialchars($produit['NOM_PRODUIT']) ?></span>
    </div>
</div>

<!-- ════ SECTION PRINCIPALE ════ -->
<section class="detail-section">
    <div class="container">
        <div class="row">

            <!-- ── GALERIE ── -->
            <div class="col-lg-6 fade-in-up" style="animation-delay:0.1s">
                <div class="gallery-wrap">

                    <!-- Photo principale -->
                    <div class="main-photo" onclick="openZoom(this.querySelector('img').src)">
                        <img src="<?= htmlspecialchars($images[0]) ?>"
                             id="mainPhoto"
                             alt="<?= htmlspecialchars($produit['NOM_PRODUIT']) ?>"
                             onerror="this.src='images/placeholder.jpg'">

                        <div class="photo-badge">
                            <?php if ($en_promo): ?>
                                <span class="badge-pill promo">-<?= $reduction ?>%</span>
                            <?php else: ?>
                                <span class="badge-pill new">NOUVEAU</span>
                            <?php endif; ?>
                            <?php if ($stock_total > 0 && $stock_total <= 5): ?>
                                <span class="badge-pill dispo">⚡ <?= $stock_total ?> restants</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Thumbnails -->
                    <?php if (count($images) > 1): ?>
                    <div class="thumbs-wrap">
                        <?php foreach ($images as $idx => $img): ?>
                        <div class="thumb <?= $idx === 0 ? 'active' : '' ?>"
                             onclick="changePhoto('<?= htmlspecialchars($img) ?>', this)">
                            <img src="<?= htmlspecialchars($img) ?>"
                                 alt="Photo <?= $idx + 1 ?>"
                                 onerror="this.src='images/placeholder.jpg'">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ── INFOS ── -->
            <div class="col-lg-6 fade-in-up" style="animation-delay:0.25s">
                <div class="product-details">

                    <!-- Catégorie -->
                    <div class="detail-category">
                        <?= htmlspecialchars($produit['NOM_SOUS_CATEGORIE'] ?? 'Collection') ?>
                    </div>

                    <!-- Nom -->
                    <h1 class="detail-title"><?= htmlspecialchars($produit['NOM_PRODUIT']) ?></h1>

                    <!-- Prix -->
                    <div class="detail-price-wrap">
                        <div class="detail-price <?= $en_promo ? 'promo' : '' ?>">
                            <?= number_format($prix_final, 0, ',', '') ?> DH
                        </div>
                        <?php if ($en_promo): ?>
                            <div class="detail-price-old">
                                <?= number_format($produit['PRIX'], 0, ',', '') ?> DH
                            </div>
                            <span class="badge-reduction">-<?= $reduction ?>%</span>
                        <?php endif; ?>
                    </div>

                    <div class="divider"></div>

                    <!-- Description -->
                    <?php if (!empty($produit['DESCRIPTION']) && $produit['DESCRIPTION'] !== 'N'): ?>
                    <p class="detail-description">
                        <?= htmlspecialchars($produit['DESCRIPTION']) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Couleurs -->
                    <?php if (!empty($couleurs)): ?>
                    <div class="mb-4">
                        <div class="section-label">Couleur disponible</div>
                        <div class="couleurs-wrap">
                            <?php
                            $color_map = [
                                'noir'   => '#1a1a1a', 'noir'    => '#1a1a1a',
                                'blanc'  => '#f5f5f0', 'blanc'   => '#f5f5f0',
                                'gris'   => '#8e8e8e', 'gris'    => '#8e8e8e',
                                'beige'  => '#d4b896', 'camel'   => '#c19a6b',
                                'marron' => '#6b3a2a', 'brun'    => '#7d4b2f',
                                'rouge'  => '#c0392b', 'rose'    => '#e8a0b0',
                                'bleu'   => '#2c5f8a', 'marine'  => '#1a3a5c',
                                'vert'   => '#2d6a4f', 'kaki'    => '#6b7c45',
                                'violet' => '#7b4f8e', 'mauve'   => '#9b7fb5',
                                'jaune'  => '#d4aa20', 'orange'  => '#d46b20',
                                'ivoire' => '#f5f0e8', 'crème'   => '#f0e8d8',
                            ];
                            foreach ($couleurs as $idx => $couleur):
                                $couleur_lower = strtolower(trim($couleur));
                                $bg = $color_map[$couleur_lower] ?? '#888';
                            ?>
                            <div class="couleur-dot <?= $idx === 0 ? 'active' : '' ?>"
                                 style="background:<?= $bg ?>;"
                                 title="<?= htmlspecialchars($couleur) ?>"
                                 onclick="selectCouleur(this)">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Tailles -->
                    <?php if (!empty($tailles_dispo)): ?>
                    <div class="mb-0">
                        <div class="tailles-header">
                            <div class="section-label">Sélectionner une taille</div>
                            <span class="guide-taille">Guide des tailles →</span>
                        </div>
                        <div class="tailles-wrap">
                            <?php
                            $first_dispo = null;
                            foreach ($tailles_dispo as $taille => $info):
                                $dispo    = $info['quantite'] > 0;
                                $low      = $dispo && $info['quantite'] <= 3;
                                $isFirst  = $dispo && $first_dispo === null;
                                if ($isFirst) $first_dispo = $taille;
                                $cls = 'taille-btn';
                                if (!$dispo)        $cls .= ' epuise';
                                elseif ($isFirst)   $cls .= ' active';
                                if ($low && $dispo) $cls .= ' low-stock';
                            ?>
                            <button class="<?= $cls ?>"
                                    data-taille="<?= htmlspecialchars($taille) ?>"
                                    data-quantite="<?= (int)$info['quantite'] ?>"
                                    <?= !$dispo ? 'disabled' : '' ?>
                                    onclick="selectTaille(this)">
                                <?= htmlspecialchars($taille) ?>
                                <?php if ($low && $dispo): ?>
                                    <span class="taille-stock"><?= $info['quantite'] ?> restants</span>
                                <?php elseif (!$dispo): ?>
                                    <span class="taille-stock">épuisé</span>
                                <?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Stock status -->
                        <div class="stock-status" id="stockStatus">
                            <?php if ($stock_total <= 0): ?>
                                <div class="stock-dot red"></div>
                                <span style="color:var(--rouge);font-size:11px;letter-spacing:0.5px;">
                                    Produit épuisé
                                </span>
                            <?php elseif ($stock_total <= 5): ?>
                                <div class="stock-dot orange"></div>
                                <span style="color:#f39c12;font-size:11px;letter-spacing:0.5px;">
                                    Presque épuisé — <?= $stock_total ?> articles restants
                                </span>
                            <?php else: ?>
                                <div class="stock-dot green"></div>
                                <span style="color:#555;font-size:11px;letter-spacing:0.5px;">
                                    En stock
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Boutons action -->
                    <div class="actions-wrap">
                        <button class="btn-add-cart" onclick="addToCart()">
                            <i class="fas fa-shopping-bag"></i>
                            Ajouter au Panier
                        </button>
                        <button class="btn-wishlist-detail <?= $isFavori ? 'active' : '' ?>"
                                id="wishlistBtn"
                                onclick="toggleWishlist(this)"
                                title="<?= $isFavori ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                            <i class="<?= $isFavori ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                    </div>

                    <!-- Livraison -->
                    <div class="livraison-info">
                        <div class="livraison-item">
                            <i class="fas fa-shipping-fast"></i>
                            <span>Livraison gratuite à partir de <strong>500 DH</strong></span>
                        </div>
                        <div class="livraison-item">
                            <i class="fas fa-undo"></i>
                            <span>Retours gratuits sous <strong>30 jours</strong></span>
                        </div>
                        <div class="livraison-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Paiement <strong>100% sécurisé</strong></span>
                        </div>
                    </div>

                    <!-- Accordéon -->
                    <div class="accord-item">
                        <div class="accord-header" onclick="toggleAccord(this)">
                            <span>Description</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accord-body">
                            <div class="accord-content">
                                <?php if (!empty($produit['DESCRIPTION']) && $produit['DESCRIPTION'] !== 'N'): ?>
                                    <?= nl2br(htmlspecialchars($produit['DESCRIPTION'])) ?>
                                <?php else: ?>
                                    Pièce soigneusement sélectionnée par Velvet Fashion pour sa qualité et son style unique.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="accord-item">
                        <div class="accord-header" onclick="toggleAccord(this)">
                            <span>Composition & Entretien</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accord-body">
                            <div class="accord-content">
                                Matière premium sélectionnée pour sa durabilité et son confort.<br>
                                Lavage recommandé à 30°C. Ne pas tumble dry.
                            </div>
                        </div>
                    </div>
                    <div class="accord-item">
                        <div class="accord-header" onclick="toggleAccord(this)">
                            <span>Livraison & Retours</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="accord-body">
                            <div class="accord-content">
                                Livraison sous 2 à 5 jours ouvrables partout au Maroc.<br>
                                Livraison gratuite à partir de 500 DH d'achat.<br>
                                Retours acceptés sous 30 jours dans leur emballage d'origine.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════ PRODUITS SIMILAIRES ════ -->
<?php if (!empty($similaires)): ?>
<section class="similaires-section">
    <div class="container">
        <h2>Vous aimerez aussi</h2>
        <div class="row g-4">
            <?php foreach ($similaires as $i => $sim):
                $sim_prix    = (!empty($sim['EN_PROMO']) && !empty($sim['PRIX_PROMO']))
                    ? $sim['PRIX_PROMO'] : $sim['PRIX'];
                $sim_img_src = $sim['IMAGE1'] ? '../' . $sim['IMAGE1'] : '';
            ?>
            <div class="col-6 col-md-3 fade-in-up" style="animation-delay:<?= 0.1 + $i * 0.1 ?>s">
                <a href="produit.php?id=<?= $sim['ID_PRODUIT'] ?>" class="sim-card">
                    <div style="overflow:hidden;">
                        <?php if ($sim_img_src): ?>
                            <img src="<?= htmlspecialchars($sim_img_src) ?>"
                                 alt="<?= htmlspecialchars($sim['NOM_PRODUIT']) ?>"
                                 onerror="this.style.display='none'">
                        <?php else: ?>
                            <div style="width:100%;height:260px;background:#f2f1ef;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-shirt" style="font-size:2rem;color:#ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="sim-card-body">
                        <div class="sim-card-name"><?= htmlspecialchars($sim['NOM_PRODUIT']) ?></div>
                        <div class="sim-card-price"><?= number_format($sim_prix, 0, ',', '') ?> DH</div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php
// ── Charger les avis existants ────────────────────────────────────
$avisListe = [];
$moyenneNote = 0;
try {
    $stmtAvis = $pdo->prepare("
        SELECT a.*, CONCAT(cl.PRENOM_CLIENT, ' ', cl.NOM_CLIENT) AS NOM_AUTEUR
        FROM avis a
        LEFT JOIN client cl ON a.ID_CLIENT = cl.ID_CLIENT
        WHERE a.ID_PRODUIT = ?
        ORDER BY a.DATE_AVIS DESC
    ");
    $stmtAvis->execute([$id]);
    $avisListe = $stmtAvis->fetchAll();
    if (!empty($avisListe)) {
        $moyenneNote = round(array_sum(array_column($avisListe, 'NOTE')) / count($avisListe), 1);
    }
} catch (PDOException $e) {}

// ── Soumettre un avis ─────────────────────────────────────────────
$avisMsg = '';
$avisMsgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soumettre_avis'])) {
    if (!isset($_SESSION['client_id'])) {
        $avisMsg = 'Vous devez être connecté pour laisser un avis.';
        $avisMsgType = 'error';
    } else {
        $note       = max(1, min(5, (int)($_POST['note'] ?? 5)));
        $commentaire = trim($_POST['commentaire'] ?? '');
        if (empty($commentaire)) {
            $avisMsg = 'Veuillez écrire un commentaire.';
            $avisMsgType = 'error';
        } else {
            try {
                // Vérifier si déjà un avis
                $chk = $pdo->prepare("SELECT COUNT(*) FROM avis WHERE ID_CLIENT=? AND ID_PRODUIT=?");
                $chk->execute([$_SESSION['client_id'], $id]);
                if ($chk->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE avis SET NOTE=?, COMMENTAIRE=?, DATE_AVIS=CURDATE() WHERE ID_CLIENT=? AND ID_PRODUIT=?")
                        ->execute([$note, $commentaire, $_SESSION['client_id'], $id]);
                } else {
                    $pdo->prepare("INSERT INTO avis (ID_CLIENT, ID_PRODUIT, NOTE, COMMENTAIRE, DATE_AVIS) VALUES(?,?,?,?,CURDATE())")
                        ->execute([$_SESSION['client_id'], $id, $note, $commentaire]);
                }
                $avisMsg = 'Votre avis a été publié !';
                $avisMsgType = 'success';
                // Reload avis
                $stmtAvis->execute([$id]);
                $avisListe = $stmtAvis->fetchAll();
                if (!empty($avisListe)) {
                    $moyenneNote = round(array_sum(array_column($avisListe, 'NOTE')) / count($avisListe), 1);
                }
            } catch (PDOException $e) {
                $avisMsg = 'Erreur lors de la publication.';
                $avisMsgType = 'error';
            }
        }
    }
}
?>

<!-- ═══ SECTION AVIS ═══ -->
<section class="avis-section">
    <div class="container">
        <div class="avis-header">
            <div>
                <h2 class="avis-title">Avis clients</h2>
                <?php if (!empty($avisListe)): ?>
                <div class="avis-summary">
                    <span class="avis-avg"><?= $moyenneNote ?></span>
                    <div class="avis-stars-avg">
                        <?php for ($s=1;$s<=5;$s++): ?>
                            <i class="<?= $s <= round($moyenneNote) ? 'fas' : 'far' ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="avis-count">(<?= count($avisListe) ?> avis)</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="avis-layout">

            <!-- ── Liste des avis ── -->
            <div class="avis-liste">
                <?php if (empty($avisListe)): ?>
                <div class="avis-empty">
                    <i class="far fa-comment-dots"></i>
                    <p>Aucun avis pour ce produit. Soyez le premier !</p>
                </div>
                <?php else: ?>
                <?php foreach ($avisListe as $av): ?>
                <div class="avis-item">
                    <div class="avis-item-header">
                        <div class="avis-avatar"><?= mb_strtoupper(mb_substr($av['NOM_AUTEUR'] ?? '?', 0, 1)) ?></div>
                        <div class="avis-item-meta">
                            <strong class="avis-author"><?= htmlspecialchars($av['NOM_AUTEUR'] ?? 'Client') ?></strong>
                            <div class="avis-stars">
                                <?php for ($s=1;$s<=5;$s++): ?>
                                    <i class="<?= $s <= (int)$av['NOTE'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <span class="avis-date"><?= date('d/m/Y', strtotime($av['DATE_AVIS'])) ?></span>
                    </div>
                    <?php if (!empty($av['COMMENTAIRE'])): ?>
                    <p class="avis-comment"><?= nl2br(htmlspecialchars($av['COMMENTAIRE'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ── Formulaire ── -->
            <div class="avis-form-wrap">
                <h3 class="avis-form-title">
                    <?= isset($_SESSION['client_id']) ? 'Laisser un avis' : 'Connectez-vous pour écrire un avis' ?>
                </h3>

                <?php if ($avisMsg): ?>
                <div class="avis-alert <?= $avisMsgType ?>">
                    <?= htmlspecialchars($avisMsg) ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['client_id'])): ?>
                <form method="POST" class="avis-form">
                    <input type="hidden" name="soumettre_avis" value="1">

                    <!-- Étoiles interactives -->
                    <div class="avis-star-input" id="starInput">
                        <input type="hidden" name="note" id="noteVal" value="5">
                        <?php for ($s=5;$s>=1;$s--): ?>
                        <label data-val="<?= $s ?>">
                            <i class="fas fa-star"></i>
                        </label>
                        <?php endfor; ?>
                    </div>

                    <textarea name="commentaire" class="avis-textarea"
                              placeholder="Partagez votre expérience avec ce produit…"
                              rows="4" required></textarea>

                    <button type="submit" class="avis-submit-btn">
                        <i class="fas fa-paper-plane"></i> Publier mon avis
                    </button>
                </form>
                <?php else: ?>
                <a href="../login.php" class="avis-login-btn">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
/* ── Avis Section ── */
.avis-section {
    padding: 60px 0 80px;
    background: #f7f5f2;
    border-top: 1px solid #eee;
}
.avis-header { margin-bottom: 36px; }
.avis-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2rem;
    font-weight: 600;
    color: #111;
    margin-bottom: 8px;
}
.avis-summary {
    display: flex;
    align-items: center;
    gap: 10px;
}
.avis-avg {
    font-family: 'Anton', sans-serif;
    font-size: 2.2rem;
    color: #000;
    line-height: 1;
}
.avis-stars-avg i, .avis-stars i { color: #f5a623; font-size: 14px; }
.avis-count { font-size: 13px; color: #999; }

.avis-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 48px;
    align-items: start;
}
@media (max-width: 900px) { .avis-layout { grid-template-columns: 1fr; } }

/* ── Liste ── */
.avis-liste { display: flex; flex-direction: column; gap: 0; }
.avis-item {
    padding: 22px 0;
    border-bottom: 1px solid #eee;
}
.avis-item:first-child { padding-top: 0; }
.avis-item-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 10px;
}
.avis-avatar {
    width: 38px; height: 38px;
    background: #111;
    color: #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Anton', sans-serif;
    font-size: 1rem;
    flex-shrink: 0;
}
.avis-item-meta { flex: 1; }
.avis-author { font-size: 13px; font-weight: 700; color: #111; display: block; margin-bottom: 3px; }
.avis-stars i { font-size: 12px; }
.avis-date { font-size: 11px; color: #bbb; white-space: nowrap; }
.avis-comment { font-size: 14px; color: #444; line-height: 1.6; margin: 0; padding-left: 52px; }
.avis-empty {
    text-align: center;
    padding: 48px 20px;
    color: #bbb;
}
.avis-empty i { font-size: 2.5rem; display: block; margin-bottom: 12px; }
.avis-empty p { font-size: 13px; }

/* ── Formulaire ── */
.avis-form-wrap {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    position: sticky;
    top: 20px;
}
.avis-form-title {
    font-size: 16px;
    font-weight: 700;
    color: #111;
    margin-bottom: 20px;
}
.avis-alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 16px;
}
.avis-alert.success { background: #e8f5e9; color: #2e7d32; }
.avis-alert.error   { background: #fce4ec; color: #c62828; }

/* Star input */
.avis-star-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 4px;
    margin-bottom: 16px;
}
.avis-star-input label i {
    font-size: 24px;
    color: #ddd;
    cursor: pointer;
    transition: color .15s;
}
.avis-star-input label:hover i,
.avis-star-input label:hover ~ label i,
.avis-star-input label.active i,
.avis-star-input label.active ~ label i { color: #f5a623; }

.avis-textarea {
    width: 100%;
    border: 1.5px solid #e0e0e0;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
    font-family: 'DM Sans', sans-serif;
    resize: vertical;
    outline: none;
    transition: border-color .2s;
    color: #333;
    margin-bottom: 14px;
    display: block;
}
.avis-textarea:focus { border-color: #000; }
.avis-submit-btn {
    width: 100%;
    background: #000;
    color: #fff;
    border: none;
    padding: 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 1px;
    cursor: pointer;
    transition: background .2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.avis-submit-btn:hover { background: #222; }
.avis-login-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #000;
    color: #fff;
    padding: 14px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s;
}
.avis-login-btn:hover { background: #222; color: #fff; }
</style>

<script>
// ── Star rating interaction ──
(function() {
    const labels = document.querySelectorAll('.avis-star-input label');
    const hidden  = document.getElementById('noteVal');
    if (!labels.length || !hidden) return;
    // Init — highlight default 5 stars
    labels.forEach((lbl, i) => {
        lbl.classList.toggle('active', i === 0); // first label = 5 stars (reversed)
    });
    labels.forEach(lbl => {
        lbl.addEventListener('click', () => {
            const val = parseInt(lbl.dataset.val);
            hidden.value = val;
            labels.forEach(l => l.classList.toggle('active', parseInt(l.dataset.val) >= val));
        });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="../JS/Main.js"></script>
<script>
// ── Changer photo principale ──
function changePhoto(src, thumb) {
    document.getElementById('mainPhoto').src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

// ── Zoom photo ──
function openZoom(src) {
    document.getElementById('zoomImg').src = src;
    document.getElementById('zoomOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeZoom() {
    document.getElementById('zoomOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeZoom(); });

// ── Sélection taille ──
let tailleSelectionnee = null;
document.querySelectorAll('.taille-btn:not(.epuise)').forEach(btn => {
    if (btn.classList.contains('active')) tailleSelectionnee = btn.dataset.taille;
});

function selectTaille(btn) {
    document.querySelectorAll('.taille-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    tailleSelectionnee = btn.dataset.taille;
    const q = parseInt(btn.dataset.quantite);
    const el = document.getElementById('stockStatus');
    if (q <= 0) {
        el.innerHTML = '<div class="stock-dot red"></div><span style="color:var(--rouge);font-size:11px;">Épuisé</span>';
    } else if (q <= 3) {
        el.innerHTML = `<div class="stock-dot orange"></div><span style="color:#f39c12;font-size:11px;">⚠ Plus que ${q} en stock</span>`;
    } else {
        el.innerHTML = `<div class="stock-dot green"></div><span style="color:#555;font-size:11px;">En stock (${q} disponibles)</span>`;
    }
}

// ── Sélection couleur ──
function selectCouleur(dot) {
    document.querySelectorAll('.couleur-dot').forEach(d => d.classList.remove('active'));
    dot.classList.add('active');
}

// ── Toggle favoris (AJAX) → actions.php unifié ──
function toggleWishlist(btn) {
    const produitId = <?= $id ?>;
    const data = new FormData();
    data.append('action', 'toggle_fav');
    data.append('id_produit', produitId);

    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.requireLogin) {
                showToast('⚠ Connectez-vous pour ajouter aux favoris.', 'warning');
                return;
            }
            if (res.success) {
                if (res.added) {
                    btn.classList.add('active');
                    btn.querySelector('i')?.classList.replace('far', 'fas');
                    showToast('❤ Ajouté aux favoris !', 'success');
                } else {
                    btn.classList.remove('active');
                    btn.querySelector('i')?.classList.replace('fas', 'far');
                    showToast('Retiré des favoris.', 'info');
                }
            } else {
                showToast('✗ Erreur, réessayez.', 'error');
            }
        })
        .catch(() => showToast('✗ Erreur de connexion.', 'error'));
}

// ── Ajouter au panier (AJAX) → actions.php unifié ──
function addToCart() {
    if (!tailleSelectionnee) {
        const tw = document.querySelector('.tailles-wrap');
        if (tw) { tw.style.animation = 'shake 0.4s ease'; setTimeout(() => tw.style.animation = '', 500); }
        showToast('⚠ Veuillez sélectionner une taille.', 'warning');
        return;
    }

    const btn      = document.querySelector('.btn-add-cart');
    const original = btn.innerHTML;
    btn.disabled   = true;
    btn.innerHTML  = '<i class="fas fa-spinner fa-spin"></i> Ajout en cours...';

    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('id_produit', <?= $id ?>);
    formData.append('qte', 1);

    fetch('actions.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML        = '<i class="fas fa-check"></i> Ajouté !';
                btn.style.background = '#2ecc71';
                updateNavBadge(data.panier_count);
                showToast('✓ Produit ajouté au panier !', 'success');
                setTimeout(() => {
                    btn.innerHTML        = original;
                    btn.style.background = '';
                    btn.disabled         = false;
                }, 2500);
            } else {
                btn.innerHTML = original; btn.disabled = false;
                showToast('✗ ' + (data.message || 'Erreur.'), 'error');
            }
        })
        .catch(() => {
            btn.innerHTML = original; btn.disabled = false;
            showToast('✗ Erreur de connexion.', 'error');
        });
}

// ── Mettre à jour le badge panier dans la navbar ──
function updateNavBadge(count) {
    let badge = document.getElementById('nav-cart-badge');
    if (!badge) {
        // Créer le badge s'il n'existe pas encore
        const cartLink = document.querySelector('.fa-shopping-bag')?.closest('a');
        if (!cartLink) return;
        cartLink.style.position = 'relative';
        badge = document.createElement('span');
        badge.id = 'nav-cart-badge';
        badge.style.cssText = `
            position:absolute; top:-8px; right:-8px;
            background:#e63946; color:#fff;
            border-radius:50%; min-width:18px; height:18px;
            font-size:10px; font-weight:700;
            display:flex; align-items:center; justify-content:center;
            padding:0 3px; pointer-events:none;
            font-family:'Inter',sans-serif;
            box-shadow: 0 2px 6px rgba(230,57,70,0.5);
            animation: badgePop 0.3s cubic-bezier(0.4,0,0.2,1);
        `;
        cartLink.appendChild(badge);
        // Animation style
        const s = document.createElement('style');
        s.textContent = `@keyframes badgePop { 0%{transform:scale(0)} 70%{transform:scale(1.2)} 100%{transform:scale(1)} }`;
        document.head.appendChild(s);
    }
    if (count > 0) {
        badge.textContent  = count;
        badge.style.display = 'flex';
        // Re-trigger animation
        badge.style.animation = 'none';
        badge.offsetHeight;
        badge.style.animation = 'badgePop 0.3s cubic-bezier(0.4,0,0.2,1)';
    } else {
        badge.style.display = 'none';
    }
}

// ── Mini popup "Produit ajouté" avec image ──
function showCartPopup(data) {
    let popup = document.getElementById('cart-popup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'cart-popup';
        popup.style.cssText = `
            position:fixed; top:90px; right:20px; z-index:9998;
            background:#fff; border-radius:14px;
            box-shadow:0 8px 40px rgba(0,0,0,0.15);
            padding:16px; display:flex; align-items:center; gap:14px;
            max-width:320px; width:calc(100vw - 40px);
            transform:translateX(120%); transition:transform 0.4s cubic-bezier(0.4,0,0.2,1);
            border-left:4px solid #2ecc71;
            font-family:'Inter',sans-serif;
        `;
        document.body.appendChild(popup);
    }

    const imgHtml = data.image
        ? `<img src="${data.image}" style="width:60px;height:74px;object-fit:cover;border-radius:8px;flex-shrink:0;" onerror="this.style.display='none'">`
        : `<div style="width:60px;height:74px;background:#f4f4f4;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-shirt" style="color:#ccc;font-size:1.5rem;"></i></div>`;

    popup.innerHTML = `
        ${imgHtml}
        <div style="flex:1;min-width:0;">
            <div style="font-size:10px;color:#2ecc71;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">
                <i class="fas fa-check-circle"></i> Ajouté au panier
            </div>
            <div style="font-size:13px;font-weight:600;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px;">
                ${data.nom || ''}
            </div>
            <div style="font-size:14px;font-weight:800;color:#111;">${data.prix || ''} DH</div>
        </div>
        <a href="client/panier.php" style="
            background:#000;color:#fff;text-decoration:none;
            font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;
            padding:8px 12px;border-radius:8px;white-space:nowrap;flex-shrink:0;
        ">Voir panier</a>
    `;

    // Afficher
    clearTimeout(popup._t);
    setTimeout(() => popup.style.transform = 'translateX(0)', 10);

    // Fermer après 4 secondes
    popup._t = setTimeout(() => {
        popup.style.transform = 'translateX(120%)';
    }, 4000);
}

// ── Toast notification ──
/* showToast — 7 secondes */
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'toast-msg ' + type + ' show';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.className = 'toast-msg'; }, 7000);
}

// ── Accordéon ──
function toggleAccord(header) {
    const body = header.nextElementSibling;
    const isOpen = body.classList.contains('open');
    // Fermer tous
    document.querySelectorAll('.accord-body').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('.accord-header').forEach(h => h.classList.remove('open'));
    if (!isOpen) {
        body.classList.add('open');
        header.classList.add('open');
    }
}

// ── Animation shake (tailles non sélectionnées) ──
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-8px); }
        40% { transform: translateX(8px); }
        60% { transform: translateX(-5px); }
        80% { transform: translateX(5px); }
    }
`;
document.head.appendChild(shakeStyle);

// ── Ouvrir description par défaut ──
document.querySelector('.accord-header')?.click();
</script>

<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
</body>
</html>