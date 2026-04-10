<?php
session_start();
require_once __DIR__ . '/../db.php';
$base = '../';


$filterCat = $_GET['cat'] ?? 'all'; 
$validCats = ['all', 'homme', 'femme'];
if (!in_array($filterCat, $validCats)) $filterCat = 'all';


$sortAllowed = [
    'date_desc' => 'p.ID_PRODUIT DESC',
    'prix_asc'  => 'COALESCE(NULLIF(p.PRIX_PROMO,0)*p.EN_PROMO, p.PRIX) ASC',
    'prix_desc' => 'COALESCE(NULLIF(p.PRIX_PROMO,0)*p.EN_PROMO, p.PRIX) DESC',
    'nom_asc'   => 'p.NOM_PRODUIT ASC',
];
$sort    = array_key_exists($_GET['sort'] ?? '', $sortAllowed) ? $_GET['sort'] : 'date_desc';
$orderBy = $sortAllowed[$sort];


$where  = [];
$params = [];
if ($filterCat === 'homme') {
    $where[]  = "LOWER(c.NOM_CATEGORIE) = 'hommes'";
} elseif ($filterCat === 'femme') {
    $where[]  = "LOWER(c.NOM_CATEGORIE) = 'femmes'";
}
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';


$stmtCount = $pdo->prepare("
    SELECT COUNT(*) FROM produit p
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    $whereSQL
");
$stmtCount->execute($params);
$totalReal  = (int)$stmtCount->fetchColumn();
$totalMax   = min(20, $totalReal); 


$perPage    = 10;
$totalPages = max(1, ceil($totalMax / $perPage));
$page       = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset     = ($page - 1) * $perPage;
$limit      = min($perPage, $totalMax - $offset);


$stmt = $pdo->prepare("
    SELECT p.*, sc.NOM_SOUS_CATEGORIE, c.NOM_CATEGORIE,
           COALESCE(NULLIF(p.PRIX_PROMO,0)*p.EN_PROMO, p.PRIX) AS prix_final,
           (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
    FROM produit p
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    $whereSQL
    ORDER BY {$orderBy}
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);


$countAll = min(20, (int)$pdo->query("SELECT COUNT(*) FROM produit")->fetchColumn());
$countH   = min(20, (int)$pdo->query("
    SELECT COUNT(*) FROM produit p
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    WHERE LOWER(c.NOM_CATEGORIE) = 'hommes'
")->fetchColumn());
$countF   = min(20, (int)$pdo->query("
    SELECT COUNT(*) FROM produit p
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    WHERE LOWER(c.NOM_CATEGORIE) = 'femmes'
")->fetchColumn());


function buildUrl($catVal, $sortVal, $pageVal = 1) {
    $params = [];
    if ($catVal !== 'all') $params['cat'] = $catVal;
    if ($sortVal !== 'date_desc') $params['sort'] = $sortVal;
    if ($pageVal > 1) $params['page'] = $pageVal;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelles Arrivées — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        
        .na-hero {
            position: relative;
            background: #0a0a0a;
            padding: 90px 0 70px;
            overflow: hidden;
            text-align: center;
        }
        .na-hero-bg {
            position: absolute; inset: 0;
            font-family: 'Anton', sans-serif;
            font-size: clamp(80px, 14vw, 180px);
            color: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            pointer-events: none; letter-spacing: -5px;
            user-select: none;
        }
        .na-hero-label {
            display: inline-block;
            font-size: 10px;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.75);
            border: 1px solid rgba(255,255,255,0.35);
            padding: 6px 18px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
        .na-hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3rem, 7vw, 5.5rem);
            font-weight: 300;
            color: #fff;
            letter-spacing: 2px;
            margin-bottom: 10px;
            line-height: 1.1;
        }
        .na-hero h1 em { font-style: italic; color: rgba(255,255,255,0.9); }
        .na-hero p {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin: 0;
        }
        .na-hero-line {
            width: 40px; height: 1px;
            background: rgba(255,255,255,0.5);
            margin: 18px auto;
        }

        
        .breadcrumb-bar {
            background: #f7f5f2;
            padding: 11px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .breadcrumb-bar a { color:#999; text-decoration:none; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; }
        .breadcrumb-bar a:hover { color:#000; }
        .breadcrumb-bar span { color:#000; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; font-weight:600; }
        .breadcrumb-bar i.sep { font-size:8px; color:#ccc; margin: 0 8px; }

        
        .filter-bar {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .filter-inner {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-label {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #999;
            white-space: nowrap;
        }
        .filter-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #555;
            text-decoration: none;
            transition: all 0.2s;
            background: #fff;
            white-space: nowrap;
        }
        .filter-chip:hover { border-color: #000; color: #000; background: #f9f9f9; }
        .filter-chip.active { background: #000; color: #fff; border-color: #000; }
        .filter-chip .chip-count {
            font-size: 9px;
            background: rgba(0,0,0,0.08);
            padding: 1px 6px;
            border-radius: 10px;
            color: #888;
        }
        .filter-chip.active .chip-count {
            background: rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
        }
        .sort-select {
            margin-left: auto;
            appearance: none;
            -webkit-appearance: none;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            padding: 7px 32px 7px 12px;
            font-size: 11px;
            letter-spacing: 0.5px;
            font-family: 'DM Sans', sans-serif;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23999'/%3E%3C/svg%3E") no-repeat right 10px center / 10px;
            cursor: pointer;
            outline: none;
            color: #333;
        }
        .sort-select:focus { border-color: #000; }
        .results-count { font-size: 11px; color: #aaa; white-space: nowrap; margin-left: 8px; }

        
        .na-wrap { padding: 50px 0 80px; }

        
        .prod-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .prod-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.10); }
        .prod-img-wrap { position: relative; overflow: hidden; aspect-ratio: 3/4; }
        .prod-img-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.55s ease;
        }
        .prod-card:hover .prod-img-wrap img { transform: scale(1.06); }
        .prod-no-img {
            width: 100%; height: 100%;
            background: #f0ece8;
            display: flex; align-items: center; justify-content: center;
            color: #ccc; font-size: 2.5rem;
        }

        
        .badge-nouveau {
            position: absolute; top: 12px; left: 12px;
            background: #000; color: #fff;
            font-size: 9px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px; z-index: 2;
        }
        .badge-promo {
            position: absolute; top: 12px; left: 12px;
            background: #e63946; color: #fff;
            font-size: 9px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px; z-index: 2;
        }
        .badge-epuise {
            position: absolute; top: 12px; right: 12px;
            background: rgba(0,0,0,0.5); color: #fff;
            font-size: 9px; letter-spacing: 1px;
            padding: 4px 10px; border-radius: 20px; z-index: 2;
        }
        .badge-cat {
            position: absolute; bottom: 12px; left: 12px;
            background: rgba(0,0,0,0.6); color: #fff;
            font-size: 8px; font-weight: 600;
            letter-spacing: 1.5px; text-transform: uppercase;
            padding: 3px 9px; border-radius: 12px; z-index: 2;
        }

        
        .btn-wish {
            position: absolute; bottom: 12px; right: 12px;
            width: 36px; height: 36px;
            background: #fff; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            cursor: pointer; transition: all 0.2s; z-index: 2;
        }
        .btn-wish i { color: #777; font-size: 14px; transition: 0.2s; }
        .btn-wish:hover { background: #e63946; }
        .btn-wish:hover i { color: #fff; }
        .btn-wish.active i { color: #e63946; font-weight: 900; }

        
        .prod-body { padding: 14px 16px 16px; }
        .prod-sc { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: #bbb; margin-bottom: 3px; }
        .prod-name { font-size: 13px; font-weight: 700; color: #111; margin-bottom: 8px; line-height: 1.3; }
        .prod-prices { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .price-final { font-size: 15px; font-weight: 800; color: #000; }
        .price-final.sale { color: #e63946; }
        .price-old { font-size: 11px; color: #bbb; text-decoration: line-through; }

        
        .btn-cart {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 38px;
            background: #000; color: #fff;
            border: none; border-radius: 50%;
            font-size: 15px; cursor: pointer;
            transition: all 0.2s; flex-shrink: 0;
        }
        .btn-cart:hover { background: #333; transform: scale(1.08); }
        .btn-cart:disabled { background: #ccc; cursor: not-allowed; transform: none; }
        .btn-cart.added { background: #27ae60; }

        
        .prod-col {
            opacity: 0;
            transform: translateY(22px);
            animation: fadeUp 0.5s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        
        .na-empty { text-align: center; padding: 80px 20px; color: #bbb; }
        .na-empty i { font-size: 3rem; margin-bottom: 16px; display: block; }
        .na-empty p { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; }

        
        .na-pager {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 60px;
        }
        .na-pager a, .na-pager span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px; height: 42px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            text-decoration: none;
            transition: all 0.2s;
        }
        .na-pager a:hover { border-color: #111; background: #111; color: #fff; }
        .na-pager span.cur { background: #111; color: #fff; border-color: #111; }
        .na-pager .dots { border: none; color: #aaa; width: auto; }

        
        #toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
                 background: #000; color: #fff; padding: 12px 24px; border-radius: 40px;
                 font-size: 13px; font-weight: 600; z-index: 9999;
                 opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap; }
        #toast.show { opacity: 1; }
        #toast.success { background: #27ae60; }
        #toast.error { background: #e63946; }
        #toast.warning { background: #f39c12; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>


<div class="na-hero">
    <div class="na-hero-bg">NOUVEAUTÉS</div>
    <div class="container position-relative">
        <div class="na-hero-label">✦ Collection récente</div>
        <h1>Nouvelles <em>Arrivées</em></h1>
        <div class="na-hero-line"></div>
        <p><?= $totalMax ?> article<?= $totalMax > 1 ? 's' : '' ?> disponible<?= $totalMax > 1 ? 's' : '' ?></p>
    </div>
</div>


<div class="breadcrumb-bar">
    <div class="container">
        <a href="../index.php">Accueil</a>
        <i class="fa fa-chevron-right sep"></i>
        <span>Nouvelles Arrivées</span>
    </div>
</div>


<div class="filter-bar">
    <div class="container">
        <div class="filter-inner">
            <span class="filter-label">Catégorie :</span>
            <div class="filter-chips">
                <a href="<?= buildUrl('all', $sort) ?>" class="filter-chip <?= $filterCat === 'all' ? 'active' : '' ?>">
                    Tout voir <span class="chip-count"><?= $countAll ?></span>
                </a>
                <a href="<?= buildUrl('femme', $sort) ?>" class="filter-chip <?= $filterCat === 'femme' ? 'active' : '' ?>">
                    <i class="fas fa-venus" style="font-size:10px;"></i> Femme <span class="chip-count"><?= $countF ?></span>
                </a>
                <a href="<?= buildUrl('homme', $sort) ?>" class="filter-chip <?= $filterCat === 'homme' ? 'active' : '' ?>">
                    <i class="fas fa-mars" style="font-size:10px;"></i> Homme <span class="chip-count"><?= $countH ?></span>
                </a>
            </div>
            <select class="sort-select" onchange="applySort(this.value)">
                <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Plus récents</option>
                <option value="prix_asc"  <?= $sort === 'prix_asc'  ? 'selected' : '' ?>>Prix croissant</option>
                <option value="prix_desc" <?= $sort === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                <option value="nom_asc"   <?= $sort === 'nom_asc'   ? 'selected' : '' ?>>Nom A → Z</option>
            </select>
            <span class="results-count"><?= $totalMax ?> article<?= $totalMax > 1 ? 's' : '' ?></span>
        </div>
    </div>
</div>


<section class="na-wrap">
    <div class="container">
        <?php if (empty($produits)): ?>
        <div class="na-empty">
            <i class="fas fa-shirt"></i>
            <p>Aucun article trouvé dans cette catégorie.</p>
            <a href="?cat=all" style="display:inline-block;margin-top:16px;background:#000;color:#fff;padding:10px 24px;border-radius:8px;font-size:11px;letter-spacing:2px;text-transform:uppercase;text-decoration:none;">Voir tout</a>
        </div>
        <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($produits as $i => $p):
                $isPromo = !empty($p['EN_PROMO']) && !empty($p['PRIX_PROMO']);
                $imgSrc  = !empty($p['IMAGE1']) ? '../' . htmlspecialchars($p['IMAGE1']) : '';
                $lien    = 'produit.php?id=' . (int)$p['ID_PRODUIT'];
                $stock   = (int)$p['stock_total'];
                $catName = $p['NOM_CATEGORIE'] ?? '';
            ?>
            <div class="col-6 col-md-4 col-lg-3 prod-col" style="animation-delay: <?= $i * 0.045 ?>s;">
                <div class="prod-card">
                    <div class="prod-img-wrap">
                        <a href="<?= $lien ?>">
                            <?php if ($imgSrc): ?>
                                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars(ucwords(strtolower($p['NOM_PRODUIT']))) ?> — Velvet Fashion" loading="lazy"
                                     onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-shirt\'></i></div>'">
                            <?php else: ?>
                                <div class="prod-no-img"><i class="fas fa-shirt"></i></div>
                            <?php endif; ?>
                        </a>
                        <?php if ($isPromo): ?>
                            <span class="badge-promo">Promo</span>
                        <?php else: ?>
                            <span class="badge-nouveau">Nouveau</span>
                        <?php endif; ?>
                        <?php if ($stock <= 0): ?>
                            <span class="badge-epuise">Épuisé</span>
                        <?php endif; ?>
                        <?php if ($filterCat === 'all' && $catName): ?>
                            <span class="badge-cat"><?= htmlspecialchars($catName) ?></span>
                        <?php endif; ?>
                        <button class="btn-wish" title="Favoris" data-toggle-fav="<?= (int)$p['ID_PRODUIT'] ?>">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="prod-body">
                        <p class="prod-sc"><?= htmlspecialchars(ucfirst(strtolower($p['NOM_SOUS_CATEGORIE'] ?? ''))) ?></p>
                        <a href="<?= $lien ?>" style="text-decoration:none;">
                            <p class="prod-name"><?= htmlspecialchars($p['NOM_PRODUIT']) ?></p>
                        </a>
                        <div class="prod-prices">
                            <?php if ($isPromo): ?>
                                <span class="price-final sale"><?= number_format((float)$p['PRIX_PROMO'],0) ?> DH</span>
                                <span class="price-old"><?= number_format((float)$p['PRIX'],0) ?> DH</span>
                            <?php else: ?>
                                <span class="price-final"><?= number_format((float)$p['PRIX'],0) ?> DH</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn-cart"
                                data-add-cart="<?= (int)$p['ID_PRODUIT'] ?>"
                                data-cart-id="<?= (int)$p['ID_PRODUIT'] ?>"
                                <?= $stock <= 0 ? 'disabled' : '' ?>
                                title="Ajouter au panier">
                            <i class="fas fa-shopping-bag"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        
        <?php if ($totalPages > 1): ?>
        <nav class="na-pager">
            <?php if ($page > 1): ?>
                <a href="<?= buildUrl($filterCat, $sort, $page - 1) ?>"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a>
            <?php endif; ?>
            <?php
            $prev = null;
            $pages = [];
            for ($i = 1; $i <= $totalPages; $i++) {
                if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2) $pages[] = $i;
            }
            foreach ($pages as $pg):
                if ($prev !== null && $pg - $prev > 1): ?><span class="dots">…</span><?php endif;
                if ($pg === $page): ?>
                    <span class="cur"><?= $pg ?></span>
                <?php else: ?>
                    <a href="<?= buildUrl($filterCat, $sort, $pg) ?>"><?= $pg ?></a>
                <?php endif;
                $prev = $pg;
            endforeach; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= buildUrl($filterCat, $sort, $page + 1) ?>"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
<script>
function applySort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>
</body>
</html>
