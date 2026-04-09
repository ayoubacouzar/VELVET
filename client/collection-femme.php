<?php
session_start();
require_once __DIR__ . '/../db.php';

$base = '../';

$stmtCat = $pdo->prepare("SELECT ID_CATEGORIE FROM categorie WHERE LOWER(NOM_CATEGORIE) = 'femmes' LIMIT 1");
$stmtCat->execute();
$catFemme = $stmtCat->fetch();
$catId = $catFemme ? $catFemme['ID_CATEGORIE'] : 0;

$sousCats = [];
if ($catId) {
    $stmt = $pdo->prepare("SELECT * FROM sous_categorie WHERE ID_CATEGORIE = ? ORDER BY NOM_SOUS_CATEGORIE");
    $stmt->execute([$catId]);
    $sousCats = $stmt->fetchAll();
}

$filterSc    = isset($_GET['sc'])     ? (int)$_GET['sc']     : 0;
$filterPromo = isset($_GET['promo'])  && $_GET['promo'] === '1';
$sort        = $_GET['sort'] ?? 'newest';

$where  = ['sc.ID_CATEGORIE = :catId'];
$params = [':catId' => $catId];

if ($filterSc) {
    $where[]          = 'p.ID_SOUS_CATEGORIE = :scId';
    $params[':scId']  = $filterSc;
}
if ($filterPromo) {
    $where[] = 'p.EN_PROMO = 1';
}

$orderBy = match($sort) {
    'price_asc'  => 'prix_final ASC',
    'price_desc' => 'prix_final DESC',
    'name'       => 'p.NOM_PRODUIT ASC',
    default      => 'p.ID_PRODUIT DESC',
};

$whereSQL = implode(' AND ', $where);

$perPage  = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));

try {
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM produit p
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        WHERE $whereSQL
    ");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $totalPages = max(1, ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("
        SELECT p.*,
            sc.NOM_SOUS_CATEGORIE,
            COALESCE(NULLIF(p.PRIX_PROMO,0)*p.EN_PROMO, p.PRIX) AS prix_final,
            (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
        FROM produit p
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        WHERE $whereSQL
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    $produits = [];
    $total = 0;
    $totalPages = 1;
    $page = 1;
}

$taillesParProduit = [];
if (!empty($produits)) {
    $ids = array_column($produits, 'ID_PRODUIT');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT ID_PRODUIT, TAILLE, COULEUR, QUANTITE
        FROM modele_produit
        WHERE ID_PRODUIT IN ($placeholders)
        ORDER BY FIELD(TAILLE,'XS','S','M','L','XL','XXL'), TAILLE
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $taillesParProduit[$row['ID_PRODUIT']][] = $row;
    }
}

function getImg(array $p): string {
    return $p['IMAGE1'] ?: ($p['IMAGE2'] ?: ($p['IMAGE3'] ?: ''));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection Femmes — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        .coll-hero {
            position: relative;
            background: #0a0a0a;
            padding: 90px 0 70px;
            overflow: hidden;
            text-align: center;
        }
        .coll-hero-bg {
            position: absolute; inset: 0;
            font-family: 'Anton', sans-serif;
            font-size: clamp(120px, 20vw, 220px);
            color: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            pointer-events: none; letter-spacing: -5px;
            user-select: none;
        }
        .coll-hero-label {
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
        .coll-hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3.5rem, 8vw, 6rem);
            font-weight: 300;
            color: #fff;
            letter-spacing: 2px;
            margin-bottom: 10px;
            line-height: 1.1;
        }
        .coll-hero h1 em {
            font-style: italic;
            color: rgba(255,255,255,0.9);
        }
        .coll-hero p {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin: 0;
        }
        .coll-hero-line {
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
        .breadcrumb-bar i { font-size:8px; color:#ccc; margin: 0 8px; }

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
        .filter-chip.promo-chip.active { background: #e63946; border-color: #e63946; }

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

        .collection-wrap { padding: 50px 0 80px; }

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
        .btn-wish.liked i { color: #e63946; font-weight: 900; }

        .prod-body { padding: 14px 16px 16px; }
        .prod-sc { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: #bbb; margin-bottom: 3px; }
        .prod-name { font-size: 13px; font-weight: 700; color: #111; margin-bottom: 8px; line-height: 1.3; }
        .prod-prices { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .price-final { font-size: 15px; font-weight: 800; color: #000; }
        .price-final.sale { color: #e63946; }
        .price-old { font-size: 11px; color: #bbb; text-decoration: line-through; }

        .sizes-row { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 12px; }
        .sz {
            border: 1.5px solid #e0e0e0;
            border-radius: 5px;
            padding: 3px 9px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            color: #444;
            user-select: none;
        }
        .sz:hover:not(.sz-out) { border-color: #000; background: #000; color: #fff; }
        .sz.sz-active { border-color: #000; background: #000; color: #fff; }
        .sz.sz-out { color: #ccc; border-color: #eee; cursor: not-allowed; text-decoration: line-through; }
        .sz-stock { font-size: 9px; color: #bbb; margin-bottom: 10px; }
        .sz-stock.low { color: #e63946; font-weight: 700; }

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
        .btn-cart.added   { background: #27ae60; }

        .prod-col {
            opacity: 0;
            transform: translateY(22px);
            animation: fadeUp 0.5s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        .empty-coll { text-align: center; padding: 80px 0; color: #bbb; }
        .empty-coll i { font-size: 3rem; margin-bottom: 16px; display: block; }
        .empty-coll p { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; }

        #toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
                background: #000; color: #fff; padding: 12px 24px; border-radius: 40px;
                font-size: 13px; font-weight: 600; z-index: 9999;
                opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap; }
        #toast.show { opacity: 1; }
        #toast.success { background: #111; }
        #toast.error { background: #e63946; }
        #toast.warning { background: #f39c12; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="coll-hero">
    <div class="coll-hero-bg">FEMME</div>
    <div class="container position-relative">
        <div class="coll-hero-label">✦ Velvet Collection</div>
        <h1>Mode <em>Femme</em></h1>
        <div class="coll-hero-line"></div>
        <p> <?= $total ?> article<?= $total > 1 ? 's' : '' ?> disponible<?= $total > 1 ? 's' : '' ?></p>
    </div>
</div>

<div class="breadcrumb-bar">
    <div class="container">
        <a href="../index.php">Accueil</a>
        <i class="fa fa-chevron-right"></i>
        <span>Collection Femmes</span>
    </div>
</div>

<div class="filter-bar">
    <div class="container">
        <div class="filter-inner">
            <span class="filter-label">Filtrer :</span>
            <div class="filter-chips">
                <?php
                $baseUrl = 'collection-femme.php?';
                $qSort   = $sort !== 'newest' ? 'sort='.$sort.'&' : '';
                ?>
                <a href="<?= $baseUrl.$qSort ?>" class="filter-chip <?= !$filterSc && !$filterPromo ? 'active' : '' ?>">
                    Tout voir
                </a>
                <?php foreach ($sousCats as $sc): ?>
                <a href="<?= $baseUrl.$qSort ?>sc=<?= $sc['ID_SOUS_CATEGORIE'] ?><?= $filterPromo ? '&promo=1' : '' ?>"
                   class="filter-chip <?= $filterSc === (int)$sc['ID_SOUS_CATEGORIE'] ? 'active' : '' ?>">
                    <?= htmlspecialchars(ucfirst(strtolower($sc['NOM_SOUS_CATEGORIE']))) ?>
                </a>
                <?php endforeach; ?>
                <a href="<?= $baseUrl.$qSort ?><?= $filterSc ? 'sc='.$filterSc.'&' : '' ?>promo=1"
                   class="filter-chip promo-chip <?= $filterPromo ? 'active' : '' ?>">
                    <i class="fas fa-tag" style="font-size:9px;"></i> Promos
                </a>
            </div>
            <select class="sort-select" onchange="applySort(this.value)">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Nouveautés</option>
                <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Prix croissant</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>A → Z</option>
            </select>
            <span class="results-count"><?= $total ?> article<?= $total > 1 ? 's' : '' ?></span>
        </div>
    </div>
</div>

<section class="collection-wrap">
    <div class="container">
        <?php if (empty($produits)): ?>
        <div class="empty-coll">
            <i class="fas fa-tshirt"></i>
            <p>Aucun article trouvé.</p>
            <a href="collection-femme.php" style="display:inline-block;margin-top:16px;background:#000;color:#fff;padding:10px 24px;border-radius:8px;font-size:11px;letter-spacing:2px;text-transform:uppercase;text-decoration:none;">Voir tout</a>
        </div>
        <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($produits as $i => $p):
                $img     = getImg($p);
                $tailles = $taillesParProduit[$p['ID_PRODUIT']] ?? [];
                $promo   = $p['EN_PROMO'] && $p['PRIX_PROMO'];
                $stock   = (int)$p['stock_total'];
                $firstAvail = null;
                foreach ($tailles as $t) {
                    if ($t['QUANTITE'] > 0) { $firstAvail = $t; break; }
                }
            ?>
            <div class="col-6 col-md-4 col-lg-3 prod-col" style="animation-delay: <?= $i * 0.045 ?>s;">
                <div class="prod-card">

                    <div class="prod-img-wrap">
                        <a href="produit.php?id=<?= $p['ID_PRODUIT'] ?>">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars('../' . $img) ?>"
                                    alt="<?= htmlspecialchars($p['NOM_PRODUIT']) ?>"
                                    onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-tshirt\'></i></div>'">
                            <?php else: ?>
                                <div class="prod-no-img"><i class="fas fa-tshirt"></i></div>
                            <?php endif; ?>
                        </a>

                        <?php if ($promo): ?>
                            <span class="badge-promo">Promo</span>
                        <?php else: ?>
                            <span class="badge-nouveau">Nouveau</span>
                        <?php endif; ?>

                        <?php if ($stock <= 0): ?>
                            <span class="badge-epuise">Épuisé</span>
                        <?php endif; ?>

                        <button class="btn-wish" title="Favoris" data-toggle-fav="<?= $p['ID_PRODUIT'] ?>">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>

                    
                    <div class="prod-body">
                        <?php if ($p['NOM_SOUS_CATEGORIE']): ?>
                            <p class="prod-sc"><?= htmlspecialchars($p['NOM_SOUS_CATEGORIE']) ?></p>
                        <?php endif; ?>
                        <a href="produit.php?id=<?= $p['ID_PRODUIT'] ?>" style="text-decoration:none;">
                            <p class="prod-name"><?= htmlspecialchars($p['NOM_PRODUIT']) ?></p>
                        </a>
                        <div class="prod-prices">
                            <?php if ($promo): ?>
                                <span class="price-final sale"><?= number_format($p['PRIX_PROMO'], 0) ?> DH</span>
                                <span class="price-old"><?= number_format($p['PRIX'], 0) ?> DH</span>
                            <?php else: ?>
                                <span class="price-final"><?= number_format($p['PRIX'], 0) ?> DH</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($tailles)): ?>
                        <div class="sizes-row" id="sizes-<?= $p['ID_PRODUIT'] ?>">
                            <?php foreach ($tailles as $t):
                                $dispo  = $t['QUANTITE'] > 0;
                                $active = ($firstAvail && $t['TAILLE'] === $firstAvail['TAILLE']);
                                $cls    = 'sz';
                                if (!$dispo)  $cls .= ' sz-out';
                                elseif ($active) $cls .= ' sz-active';
                            ?>
                            <span class="<?= $cls ?>"
                                data-qty="<?= (int)$t['QUANTITE'] ?>"
                                onclick="selectSize(this, <?= $p['ID_PRODUIT'] ?>)">
                                <?= htmlspecialchars($t['TAILLE']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <p class="sz-stock <?= ($firstAvail && $firstAvail['QUANTITE'] <= 3) ? 'low' : '' ?>"
                        id="stock-<?= $p['ID_PRODUIT'] ?>">
                            <?php if ($firstAvail): ?>
                                <?php if ($firstAvail['QUANTITE'] <= 3): ?>
                                    ⚠ Plus que <?= $firstAvail['QUANTITE'] ?> en stock
                                <?php else: ?>
                                    En stock (<?= $firstAvail['QUANTITE'] ?> dispo.)
                                <?php endif; ?>
                            <?php elseif ($stock <= 0): ?>
                                <span style="color:#ccc;">Rupture de stock</span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>

                        <button class="btn-cart"
                                id="cartbtn-<?= $p['ID_PRODUIT'] ?>"
                                data-add-cart="<?= $p['ID_PRODUIT'] ?>"
                                data-cart-id="<?= $p['ID_PRODUIT'] ?>"
                                <?= $stock <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-bag"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="velvet-pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="vpg-btn vpg-prev">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++):
                $active = $p === $page;
                if ($p === 1 || $p === $totalPages || abs($p - $page) <= 2):
            ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                    class="vpg-btn <?= $active ? 'vpg-active' : '' ?>"><?= $p ?>
                </a>
            <?php elseif (abs($p - $page) === 3): ?>
                <span class="vpg-dots">…</span>
            <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="vpg-btn vpg-next">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<div id="toast" class="toast-msg"></div>

<script src="../JS/script.js"></script>
<script>

function applySort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    window.location.href = url.toString();
}

function selectSize(el, prodId) {
    if (el.classList.contains('sz-out')) return;
    const wrap = document.getElementById('sizes-' + prodId);
    wrap.querySelectorAll('.sz').forEach(s => s.classList.remove('sz-active'));
    el.classList.add('sz-active');
    const qty = parseInt(el.dataset.qty || '0');
    const stockEl = document.getElementById('stock-' + prodId);
    if (stockEl) {
        if (qty <= 0) {
            stockEl.innerHTML = '<span style="color:#ccc;">Rupture de stock</span>';
            stockEl.className = 'sz-stock';
        } else if (qty <= 3) {
            stockEl.innerHTML = `⚠ Plus que ${qty} en stock`;
            stockEl.className = 'sz-stock low';
        } else {
            stockEl.innerHTML = `En stock (${qty} dispo.)`;
            stockEl.className = 'sz-stock';
        }
    }
}

function toggleWish(btn) {
    const prodId = btn.dataset.toggleFav || btn.dataset.prodId;
    if (prodId) { toggleFav(prodId, btn); return; }
    btn.classList.toggle('liked');
    const icon = btn.querySelector('i');
    if (icon) {
        if (btn.classList.contains('liked')) { icon.classList.replace('far','fas'); icon.style.color='#e63946'; }
        else { icon.classList.replace('fas','far'); icon.style.color=''; }
    }
}
</script>
</body>
</html>