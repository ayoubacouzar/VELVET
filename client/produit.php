<?php


session_start();
require_once __DIR__ . '/../db.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}


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


$couleurs = array_unique(array_column($modeles, 'COULEUR'));
$couleurs = array_filter($couleurs);


$tailles_dispo = [];
foreach ($modeles as $m) {
    $tailles_dispo[$m['TAILLE']] = [
        'quantite' => $m['QUANTITE'],
        'couleur'  => $m['COULEUR'],
    ];
}


$images = array_filter([
    $produit['IMAGE1'] ? '../' . $produit['IMAGE1'] : null,
    $produit['IMAGE2'] ? '../' . $produit['IMAGE2'] : null,
    $produit['IMAGE3'] ? '../' . $produit['IMAGE3'] : null,
]);
if (empty($images)) $images = ['../images/VELVET_LOGO_blanc.png'];
$images = array_values($images);


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


$prix_final  = (!empty($produit['EN_PROMO']) && !empty($produit['PRIX_PROMO']))
    ? $produit['PRIX_PROMO']
    : $produit['PRIX'];
$en_promo    = !empty($produit['EN_PROMO']) && !empty($produit['PRIX_PROMO']);
$reduction   = $en_promo
    ? round((1 - $produit['PRIX_PROMO'] / $produit['PRIX']) * 100)
    : 0;


$stock_total = array_sum(array_column($modeles, 'QUANTITE'));


$isFavori = false;
if (!empty($_SESSION['client_id'])) {
    $stmtFv = $pdo->prepare("SELECT 1 FROM aime WHERE ID_CLIENT = ? AND ID_PRODUIT = ?");
    $stmtFv->execute([$_SESSION['client_id'], $id]);
    $isFavori = (bool)$stmtFv->fetch();
}


$avisListe = [];
$moyenneNote = 0;
$avisMsg = '';
$avisMsgType = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soumettre_avis']) && !empty($_SESSION['client_id'])) {
    $note = max(1, min(5, (int)($_POST['note'] ?? 5)));
    $commentaire = trim($_POST['commentaire'] ?? '');
    if (!empty($commentaire)) {
        try {
            $stmtAvis = $pdo->prepare("INSERT INTO avis (ID_CLIENT, ID_PRODUIT, NOTE, COMMENTAIRE, DATE_AVIS) VALUES (?, ?, ?, ?, CURDATE())");
            $stmtAvis->execute([$_SESSION['client_id'], $id, $note, $commentaire]);
            $avisMsg = 'Merci ! Votre avis a été publié.';
            $avisMsgType = 'success';
        } catch (PDOException $e) {
            $avisMsg = 'Erreur lors de la soumission de votre avis.';
            $avisMsgType = 'error';
        }
    } else {
        $avisMsg = 'Veuillez écrire un commentaire.';
        $avisMsgType = 'error';
    }
}


try {
    $stmtAvis = $pdo->prepare("
        SELECT a.NOTE, a.COMMENTAIRE, a.DATE_AVIS,
               CONCAT(c.PRENOM_CLIENT, ' ', LEFT(c.NOM_CLIENT, 1), '.') AS NOM_AUTEUR
        FROM avis a
        LEFT JOIN client c ON a.ID_CLIENT = c.ID_CLIENT
        WHERE a.ID_PRODUIT = ?
        ORDER BY a.DATE_AVIS DESC
    ");
    $stmtAvis->execute([$id]);
    $avisListe = $stmtAvis->fetchAll();
    if (!empty($avisListe)) {
        $moyenneNote = round(array_sum(array_column($avisListe, 'NOTE')) / count($avisListe), 1);
    }
} catch (PDOException $e) {
    $avisListe = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <title><?= htmlspecialchars($produit['NOM_PRODUIT']) ?> — Velvet Fashion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        
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

        
        .detail-section { padding: 40px 0 60px; }

        
        .gallery-wrap { position: sticky; top: 90px; }

        .main-photo {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: var(--gris);
            aspect-ratio: 4/5;
            max-height: 75vh;
            cursor: zoom-in;
        }
        .main-photo img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            display: block;
        }
        .main-photo:hover img { transform: scale(1.04); }

        
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
            font-family: 'Inter', sans-serif;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.5px; margin-bottom: 16px;
            color: var(--noir);
        }

        
        .detail-price-wrap { display: flex; align-items: baseline; gap: 14px; margin-bottom: 24px; }
        .detail-price {
            font-family: 'Inter', sans-serif;
            font-size: 1.8rem; font-weight: 800; color: var(--noir);
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

        
        .divider { height: 1px; background: var(--gris-mid); margin: 24px 0; }

        
        .detail-description {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem; line-height: 1.7;
            color: #666;
            margin-bottom: 24px;
        }

        
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

        
        .stock-status {
            font-size: 11px; margin-top: 10px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 6px;
            min-height: 18px;
        }
        .stock-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .stock-dot.green  { background: #2ecc71; }
        .stock-dot.orange { background: #f39c12; }
        .stock-dot.red    { background: var(--rouge); }

        
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

        
        .similaires-section {
            padding: 50px 0 60px;
            background: #0a0a0a;
        }
        .similaires-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }
        .similaires-header h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 400;
            color: #fff;
            margin: 0;
            letter-spacing: 1px;
        }
        .similaires-header h2 em { font-style: italic; color: rgba(255,255,255,0.7); }
        .similaires-header .sim-line {
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin-left: 24px;
        }
        .similaires-section .prod-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .similaires-section .prod-card:hover {
            background: rgba(255,255,255,0.08);
            box-shadow: 0 12px 30px rgba(0,0,0,0.3);
        }
        .similaires-section .prod-body { background: transparent; }
        .similaires-section .prod-sc { color: rgba(255,255,255,0.4); }
        .similaires-section .prod-name { color: #fff; }
        .similaires-section .price-final { color: #fff; }
        .similaires-section .price-final.sale { color: #ff6b6b; }
        .similaires-section .price-old { color: rgba(255,255,255,0.35); }
        .similaires-section .btn-cart {
            background: #fff;
            color: #000;
        }
        .similaires-section .btn-cart:hover {
            background: #f0eeec;
        }
        .similaires-section .btn-wish {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
        }
        .similaires-section .btn-wish i { color: rgba(255,255,255,0.6); }
        .similaires-section .btn-wish:hover { background: #e63946; border-color: #e63946; }
        .similaires-section .btn-wish:hover i { color: #fff; }
        .sim-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
        .sim-card img { width: 100%; height: 260px; object-fit: cover; display: block; transition: transform 0.5s ease; }
        .sim-card:hover img { transform: scale(1.05); }
        .sim-card-body { padding: 16px; }
        .sim-card-name { font-weight: 700; font-size: 0.9rem; color: var(--noir); margin-bottom: 4px; }
        .sim-card-price { font-weight: 800; font-size: 0.95rem; color: var(--noir); }

        
        .fade-in-up {
            opacity: 0; transform: translateY(30px);
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } }

        
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

        
        @media (max-width: 991px) {
            .product-details { padding-left: 0; padding-top: 24px; }
            .gallery-wrap { position: static; }
            .main-photo { max-height: 60vh; aspect-ratio: 4/5; }
        }
        @media (max-width: 576px) {
            .main-photo { max-height: 50vh; }
            .detail-title { font-size: 1.4rem; }
            .detail-price { font-size: 1.5rem; }
            .actions-wrap { flex-direction: column; }
            .btn-add-cart { width: 100%; }
            .btn-wishlist-detail { width: 100%; }
        }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>


<div class="zoom-overlay" id="zoomOverlay" onclick="closeZoom()">
    <button class="zoom-close" onclick="closeZoom()"><i class="fas fa-times"></i></button>
    <img src="" id="zoomImg" alt="">
</div>


<div class="breadcrumb-velvet">
    <div class="container">
        <a href="../index.php">Accueil</a>
        <i class="fa fa-chevron-right"></i>
        <?php if (!empty($produit['NOM_CATEGORIE'])): ?>
            <?php
            $catLower = strtolower($produit['NOM_CATEGORIE']);
            $catHref = 'nouvelles-arrivees.php';
            if (strpos($catLower, 'femme') !== false) $catHref = 'collection-femme.php';
            elseif (strpos($catLower, 'homme') !== false) $catHref = 'collection-homme.php';
            ?>
            <a href="<?= $catHref ?>"><?= htmlspecialchars($produit['NOM_CATEGORIE']) ?></a>
            <i class="fa fa-chevron-right"></i>
        <?php endif; ?>
        <?php if (!empty($produit['NOM_SOUS_CATEGORIE'])): ?>
            <a href="sous-categorie.php?id=<?= $produit['ID_SOUS_CATEGORIE'] ?>"><?= htmlspecialchars($produit['NOM_SOUS_CATEGORIE']) ?></a>
            <i class="fa fa-chevron-right"></i>
        <?php endif; ?>
        <span><?= htmlspecialchars($produit['NOM_PRODUIT']) ?></span>
    </div>
</div>


<section class="detail-section">
    <div class="container">
        <div class="row">

            <div class="col-lg-6 fade-in-up" style="animation-delay:0.1s">
                <div class="gallery-wrap">

                    <div class="main-photo" onclick="openZoom(this.querySelector('img').src)">
                        <img src="<?= htmlspecialchars($images[0]) ?>"
                            id="mainPhoto"
                            alt="<?= htmlspecialchars(ucwords(strtolower($produit['NOM_PRODUIT']))) ?> — Velvet Fashion"
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

            
            <div class="col-lg-6 fade-in-up" style="animation-delay:0.25s">
                <div class="product-details">

                    
                    <div class="detail-category">
                        <?= htmlspecialchars($produit['NOM_SOUS_CATEGORIE'] ?? 'Collection') ?>
                    </div>

                    
                    <h1 class="detail-title"><?= htmlspecialchars($produit['NOM_PRODUIT']) ?></h1>

                    
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

                    
                    <?php if (!empty($produit['DESCRIPTION']) && $produit['DESCRIPTION'] !== 'N'): ?>
                    <p class="detail-description">
                        <?= htmlspecialchars($produit['DESCRIPTION']) ?>
                    </p>
                    <?php endif; ?>

                    
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

                    
                    <div class="actions-wrap">
                        <button class="btn-add-cart" id="btnAddCart">
                            <i class="fas fa-shopping-bag"></i>
                            Ajouter au Panier
                        </button>
                        <button class="btn-wishlist-detail <?= $isFavori ? 'active' : '' ?>"
                                id="wishlistBtn"
                                data-toggle-fav="<?= $id ?>"
                                title="<?= $isFavori ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                            <i class="<?= $isFavori ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                    </div>

                    
                    <div class="livraison-info">
                        <div class="livraison-item">
                            <i class="fas fa-shipping-fast"></i>
                            <span>Livraison gratuite à partir de <strong>500 DH</strong></span>
                        </div>
                        <div class="livraison-item">
                            <i class="fas fa-undo"></i>
                            <span>Retours gratuits sous <strong>30 jours</strong></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>


<?php

$ratingCounts = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
foreach ($avisListe as $av) {
    $n = (int)$av['NOTE'];
    if (isset($ratingCounts[$n])) $ratingCounts[$n]++;
}
$totalAvis = count($avisListe);
?>
<section class="avis-section">
    <div class="container">

        <div class="avis-header">
            <div class="avis-header-line"></div>
            <h2 class="avis-header-title">Ce qu'en pensent <em>nos clients</em></h2>
            <div class="avis-header-line"></div>
        </div>

        <div class="avis-top-row">
            
            <div class="avis-summary">
                <?php if ($totalAvis > 0): ?>
                <div class="avis-big-score"><?= $moyenneNote ?></div>
                <div class="avis-summary-stars">
                    <?php for ($s=1;$s<=5;$s++): ?>
                        <i class="<?= $s <= round($moyenneNote) ? 'fas' : 'far' ?> fa-star"></i>
                    <?php endfor; ?>
                </div>
                <p class="avis-summary-count"><?= $totalAvis ?> avis vérifiés</p>
                
                <div class="avis-bars">
                    <?php for ($r=5; $r>=1; $r--):
                        $pct = $totalAvis > 0 ? round($ratingCounts[$r] / $totalAvis * 100) : 0;
                    ?>
                    <div class="avis-bar-row">
                        <span class="avis-bar-label"><?= $r ?> <i class="fas fa-star"></i></span>
                        <div class="avis-bar-track"><div class="avis-bar-fill" style="width:<?= $pct ?>%"></div></div>
                        <span class="avis-bar-pct"><?= $ratingCounts[$r] ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php else: ?>
                <div class="avis-big-score" style="color:#ddd;">—</div>
                <p class="avis-summary-count">Aucun avis pour le moment</p>
                <?php endif; ?>
            </div>

            
            <div class="avis-form-section">
                <?php if ($avisMsg): ?>
                <div class="avis-alert <?= $avisMsgType ?>"><?= htmlspecialchars($avisMsg) ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['client_id'])): ?>
                <p class="avis-form-title">Partagez votre expérience</p>
                <form class="avis-compact-form" id="avisForm" onsubmit="return false;">
                    <input type="hidden" name="note" id="noteVal" value="0">
                    <div class="avis-star-input" id="starInput">
                        <?php for ($s=5;$s>=1;$s--): ?>
                        <label data-val="<?= $s ?>"><i class="far fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                    <input type="text" name="commentaire" id="avisComment" class="avis-field" placeholder="Votre avis..." maxlength="200">
                    <button type="button" class="avis-submit" id="avisSubmitBtn"><i class="fas fa-paper-plane"></i></button>
                </form>
                <?php else: ?>
                <p class="avis-form-title">Vous avez acheté ce produit ?</p>
                <a href="../login.php" class="avis-login-link">
                    <i class="fas fa-sign-in-alt"></i> Connectez-vous pour donner votre avis
                </a>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="avis-liste">
            <?php if (empty($avisListe)): ?>
            <div class="avis-empty">
                <i class="far fa-comment-dots"></i>
                <p>Soyez le premier à donner votre avis !</p>
            </div>
            <?php else: ?>
            <?php foreach ($avisListe as $idx => $av): ?>
            <div class="avis-card" style="animation-delay:<?= $idx * 0.05 ?>s">
                <div class="avis-card-header">
                    <div class="avis-avatar"><?= mb_strtoupper(mb_substr($av['NOM_AUTEUR'] ?? '?', 0, 1)) ?></div>
                    <div class="avis-card-info">
                        <strong><?= htmlspecialchars($av['NOM_AUTEUR'] ?? 'Client') ?></strong>
                        <div class="avis-card-stars">
                            <?php for ($s=1;$s<=5;$s++): ?>
                                <i class="<?= $s <= (int)$av['NOTE'] ? 'fas' : 'far' ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <span class="avis-card-date"><?= date('d M Y', strtotime($av['DATE_AVIS'])) ?></span>
                </div>
                <?php if (!empty($av['COMMENTAIRE'])): ?>
                <p class="avis-card-text"><?= nl2br(htmlspecialchars($av['COMMENTAIRE'])) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>

.avis-section {
    padding: 60px 0 70px;
    background: #faf9f7;
    border-top: 1px solid #eee;
}


.avis-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 40px;
}
.avis-header-line { flex: 1; height: 1px; background: #e0ddd8; }
.avis-header-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(1.4rem, 2.5vw, 1.9rem);
    font-weight: 400;
    color: #111;
    letter-spacing: 0.5px;
    white-space: nowrap;
    margin: 0;
}
.avis-header-title em { font-style: italic; color: #888; }


.avis-top-row {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 40px;
    margin-bottom: 36px;
    align-items: start;
}


.avis-summary {
    background: #fff;
    border-radius: 16px;
    padding: 28px 24px;
    text-align: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    border: 1px solid #f0eeec;
}
.avis-big-score {
    font-family: 'Anton', sans-serif;
    font-size: 3.2rem;
    color: #111;
    line-height: 1;
    margin-bottom: 6px;
}
.avis-summary-stars { margin-bottom: 6px; }
.avis-summary-stars i { color: #f5a623; font-size: 18px; }
.avis-summary-count {
    font-size: 11px;
    color: #999;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 20px;
}


.avis-bars { display: flex; flex-direction: column; gap: 6px; }
.avis-bar-row { display: flex; align-items: center; gap: 8px; }
.avis-bar-label {
    font-size: 11px; color: #999; width: 36px;
    text-align: right; white-space: nowrap;
}
.avis-bar-label i { font-size: 9px; color: #f5a623; }
.avis-bar-track {
    flex: 1; height: 6px;
    background: #f0eeec; border-radius: 3px; overflow: hidden;
}
.avis-bar-fill {
    height: 100%; background: #f5a623; border-radius: 3px;
    transition: width 0.6s cubic-bezier(0.4,0,0.2,1);
}
.avis-bar-pct { font-size: 11px; color: #bbb; width: 20px; }


.avis-form-section {
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.avis-form-title {
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 12px;
    letter-spacing: 0.3px;
}
.avis-compact-form {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1.5px solid #e8e6e3;
    border-radius: 50px;
    padding: 6px 6px 6px 16px;
    transition: border-color .2s;
}
.avis-compact-form:focus-within { border-color: #bbb; }


.avis-star-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 2px;
    flex-shrink: 0;
}
.avis-star-input label {
    cursor: pointer;
    padding: 1px;
    line-height: 1;
}
.avis-star-input label i {
    font-size: 16px;
    color: #ddd;
    transition: color .15s, transform .2s;
}
.avis-star-input label:hover i {
    transform: scale(1.25);
    color: #f5a623;
}
.avis-star-input label:hover ~ label i { color: #f5a623; }
.avis-star-input label.active i { color: #f5a623; }
.avis-star-input label.active ~ label i { color: #f5a623; }


.avis-field {
    flex: 1;
    min-width: 0;
    border: none;
    background: transparent;
    padding: 10px 8px;
    font-size: 13px;
    font-family: 'Inter', sans-serif;
    outline: none;
    color: #333;
}
.avis-field::placeholder { color: #bbb; }


.avis-submit {
    width: 36px; height: 36px;
    background: #000;
    color: #fff;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .2s, transform .15s;
}
.avis-submit:hover { background: #333; transform: scale(1.06); }


.avis-login-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #000;
    color: #fff;
    padding: 12px 22px;
    border-radius: 40px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: background .2s, transform .15s;
}
.avis-login-link:hover { background: #222; color: #fff; transform: translateY(-1px); }

.avis-alert {
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 12px;
    margin-bottom: 14px;
}
.avis-alert.success { background: #e8f5e9; color: #2e7d32; }
.avis-alert.error   { background: #fce4ec; color: #c62828; }


.avis-liste {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.avis-card {
    padding: 22px 0;
    border-bottom: 1px solid #eae8e4;
    animation: avisSlideIn 0.4s ease forwards;
    opacity: 0;
}
@keyframes avisSlideIn { to { opacity: 1; } }
.avis-card:last-child { border-bottom: none; }
.avis-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.avis-avatar {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, #1a1a1a, #333);
    color: #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Inter', sans-serif;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}
.avis-card-info { flex: 1; }
.avis-card-info strong { font-size: 13px; color: #111; display: block; margin-bottom: 2px; }
.avis-card-stars i { color: #f5a623; font-size: 11px; }
.avis-card-date { font-size: 11px; color: #bbb; flex-shrink: 0; }
.avis-card-text {
    font-size: 14px;
    color: #555;
    line-height: 1.75;
    margin: 0;
    padding-left: 50px;
}
.avis-empty {
    text-align: center;
    padding: 50px 20px;
    color: #ccc;
}
.avis-empty i { font-size: 2.5rem; display: block; margin-bottom: 12px; }
.avis-empty p { font-size: 13px; margin: 0; letter-spacing: 0.5px; }

@media (max-width: 768px) {
    .avis-top-row { grid-template-columns: 1fr; gap: 24px; }
    .avis-summary { max-width: 320px; margin: 0 auto; }
}
@media (max-width: 576px) {
    .avis-compact-form { flex-wrap: wrap; border-radius: 16px; padding: 10px 10px 10px 14px; }
    .avis-star-input { width: 100%; justify-content: center; margin-bottom: 4px; }
    .avis-card-text { padding-left: 0; }
    .avis-header-title { font-size: 1.2rem; }
}
</style>

<script>

(function() {
    const labels = document.querySelectorAll('.avis-star-input label');
    const hidden  = document.getElementById('noteVal');
    if (!labels.length || !hidden) return;
    let selected = 0;

    
    labels.forEach(lbl => {
        lbl.addEventListener('mouseenter', () => {
            const val = parseInt(lbl.dataset.val);
            labels.forEach(l => {
                const v = parseInt(l.dataset.val);
                const icon = l.querySelector('i');
                if (v <= val) {
                    icon.classList.replace('far', 'fas');
                } else {
                    icon.classList.replace('fas', 'far');
                }
            });
        });
    });

    
    const container = document.getElementById('starInput');
    if (container) {
        container.addEventListener('mouseleave', () => {
            labels.forEach(l => {
                const v = parseInt(l.dataset.val);
                const icon = l.querySelector('i');
                l.classList.toggle('active', v <= selected);
                if (v <= selected) {
                    icon.classList.replace('far', 'fas');
                } else {
                    icon.classList.replace('fas', 'far');
                }
            });
        });
    }

    
    labels.forEach(lbl => {
        lbl.addEventListener('click', () => {
            selected = parseInt(lbl.dataset.val);
            hidden.value = selected;
            labels.forEach(l => {
                const v = parseInt(l.dataset.val);
                const icon = l.querySelector('i');
                l.classList.toggle('active', v <= selected);
                if (v <= selected) {
                    icon.classList.replace('far', 'fas');
                } else {
                    icon.classList.replace('fas', 'far');
                }
            });
        });
    });

    var submitBtn = document.getElementById('avisSubmitBtn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            var note = parseInt(hidden.value);
            var comment = document.getElementById('avisComment');
            var text = comment ? comment.value.trim() : '';
            if (!note || note < 1) { showToast('Veuillez choisir une note.', 'warning'); return; }
            if (!text) { showToast('Veuillez écrire un commentaire.', 'warning'); return; }
            submitBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'add_avis');
            fd.append('id_produit', <?= $id ?>);
            fd.append('note', note);
            fd.append('commentaire', text);
            fetch('actions.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    submitBtn.disabled = false;
                    if (res.success) {
                        showToast(res.message, 'success');
                        comment.value = '';
                        selected = 0;
                        hidden.value = 0;
                        labels.forEach(function(l) { l.classList.remove('active'); l.querySelector('i').classList.replace('fas','far'); });
                        var av = res.avis;
                        var initial = av.auteur ? av.auteur.charAt(0).toUpperCase() : '?';
                        var starsHtml = '';
                        for (var i = 1; i <= 5; i++) starsHtml += '<i class="' + (i <= av.note ? 'fas' : 'far') + ' fa-star"></i>';
                        var html = '<div class="avis-card" style="animation-delay:0s">'
                            + '<div class="avis-card-header">'
                            + '<div class="avis-avatar">' + initial + '</div>'
                            + '<div class="avis-card-info"><strong>' + av.auteur + '</strong><div class="avis-card-stars">' + starsHtml + '</div></div>'
                            + '<span class="avis-card-date">' + av.date + '</span>'
                            + '</div>'
                            + '<p class="avis-card-text">' + av.commentaire + '</p>'
                            + '</div>';
                        var liste = document.querySelector('.avis-liste');
                        var empty = liste.querySelector('.avis-empty');
                        if (empty) empty.remove();
                        liste.insertAdjacentHTML('afterbegin', html);
                    } else {
                        showToast(res.message || 'Erreur.', 'error');
                    }
                })
                .catch(function() { submitBtn.disabled = false; showToast('Erreur de connexion.', 'error'); });
        });
    }
})();
</script>


<?php if (!empty($similaires)): ?>
<section class="similaires-section">
    <div class="container">
        <div class="similaires-header">
            <h2>Vous aimerez <em>aussi</em></h2>
            <div class="sim-line"></div>
        </div>
        <div class="row g-3 g-md-4">
            <?php foreach ($similaires as $sim):
                $sim_promo   = !empty($sim['EN_PROMO']) && !empty($sim['PRIX_PROMO']);
                $sim_prix    = $sim_promo ? $sim['PRIX_PROMO'] : $sim['PRIX'];
                $sim_img_src = $sim['IMAGE1'] ? '../' . $sim['IMAGE1'] : '';
            ?>
            <div class="col-6 col-md-3">
                <div class="prod-card">
                    <div class="prod-img-wrap">
                        <a href="produit.php?id=<?= $sim['ID_PRODUIT'] ?>">
                            <?php if ($sim_img_src): ?>
                                <img src="<?= htmlspecialchars($sim_img_src) ?>" alt="<?= htmlspecialchars(ucwords(strtolower($sim['NOM_PRODUIT']))) ?> — Velvet Fashion" loading="lazy" onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-tshirt\'></i></div>'">
                            <?php else: ?>
                                <div class="prod-no-img"><i class="fas fa-tshirt"></i></div>
                            <?php endif; ?>
                        </a>
                        <?php if ($sim_promo): ?><span class="badge-promo">Promo</span><?php endif; ?>
                        <button class="btn-wish" title="Favoris" data-toggle-fav="<?= $sim['ID_PRODUIT'] ?>">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="prod-body">
                        <a href="produit.php?id=<?= $sim['ID_PRODUIT'] ?>" style="text-decoration:none;">
                            <p class="prod-name"><?= htmlspecialchars($sim['NOM_PRODUIT']) ?></p>
                        </a>
                        <div class="prod-prices">
                            <?php if ($sim_promo): ?>
                                <span class="price-final sale"><?= number_format($sim['PRIX_PROMO'],0) ?> DH</span>
                                <span class="price-old"><?= number_format($sim['PRIX'],0) ?> DH</span>
                            <?php else: ?>
                                <span class="price-final"><?= number_format($sim_prix,0) ?> DH</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn-cart" data-add-cart="<?= $sim['ID_PRODUIT'] ?>" data-cart-id="<?= $sim['ID_PRODUIT'] ?>" title="Ajouter au panier">
                            <i class="fas fa-shopping-bag"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="../JS/Main.js"></script>
<script>

function changePhoto(src, thumb) {
    document.getElementById('mainPhoto').src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}


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


let tailleSelectionnee = null;

document.querySelectorAll('.taille-btn:not(.epuise)').forEach(btn => {
    if (btn.classList.contains('active')) tailleSelectionnee = btn.dataset.taille;
});
const hasSizes = document.querySelectorAll('.taille-btn').length > 0;

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


function selectCouleur(dot) {
    document.querySelectorAll('.couleur-dot').forEach(d => d.classList.remove('active'));
    dot.classList.add('active');
}


function toggleAccord(header) {
    const body = header.nextElementSibling;
    const isOpen = body.classList.contains('open');
    
    document.querySelectorAll('.accord-body').forEach(b => b.classList.remove('open'));
    document.querySelectorAll('.accord-header').forEach(h => h.classList.remove('open'));
    if (!isOpen) {
        body.classList.add('open');
        header.classList.add('open');
    }
}


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


document.querySelector('.accord-header')?.click();
</script>

<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
<script>
(function() {
    const prodId = <?= $id ?>;
    const hasSz = document.querySelectorAll('.taille-btn').length > 0;

    var cartBtn = document.getElementById('btnAddCart');
    if (cartBtn) {
        cartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var selected = document.querySelector('.taille-btn.active:not(.epuise)');
            if (hasSz && !selected) {
                var tw = document.querySelector('.tailles-wrap');
                if (tw) { tw.style.animation = 'shake 0.4s ease'; setTimeout(function(){ tw.style.animation = ''; }, 500); }
                showToast('Veuillez sélectionner une taille.', 'warning');
                return;
            }
            var original = cartBtn.innerHTML;
            cartBtn.disabled = true;
            cartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout en cours...';

            var selectedTaille = selected ? selected.dataset.taille : '';
            var data = new FormData();
            data.append('action', 'add_to_cart');
            data.append('id_produit', prodId);
            data.append('taille', selectedTaille);
            data.append('qte', 1);

            fetch('actions.php', { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        cartBtn.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                        cartBtn.style.background = '#2ecc71';
                        var badges = document.querySelectorAll('.panier-nav-badge');
                        if (res.panier_count > 0) {
                            badges.forEach(function(b) { b.textContent = res.panier_count; b.style.display = 'flex'; });
                            if (!badges.length) {
                                var wrap = document.querySelector('.nav-panier-wrap');
                                if (wrap) { var b = document.createElement('span'); b.className = 'panier-nav-badge'; b.textContent = res.panier_count; wrap.appendChild(b); }
                            }
                        }
                        showToast('Produit ajouté au panier !', 'success');
                        setTimeout(function() { cartBtn.innerHTML = original; cartBtn.style.background = ''; cartBtn.disabled = false; }, 2500);
                    } else {
                        cartBtn.innerHTML = original; cartBtn.disabled = false;
                        showToast(res.message || 'Erreur.', 'error');
                    }
                })
                .catch(function() { cartBtn.innerHTML = original; cartBtn.disabled = false; showToast('Erreur de connexion.', 'error'); });
        });
    }
})();
</script>
</body>
</html>