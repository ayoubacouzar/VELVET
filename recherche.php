<?php


session_start();
require_once 'db.php';

$q    = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$produits = [];
$total    = 0;

$perPage    = 15;
$page       = max(1, (int)($_GET['page'] ?? 1));
$totalPages = 1;

if ($q !== '') {
    $orderBy = match($sort) {
        'price_asc'  => 'prix_final ASC',
        'price_desc' => 'prix_final DESC',
        'name'       => 'p.NOM_PRODUIT ASC',
        default      => 'p.ID_PRODUIT DESC',
    };
    $like = '%' . $q . '%';

    try {
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) FROM produit p
            LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
            LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
            WHERE p.NOM_PRODUIT LIKE :q1 OR sc.NOM_SOUS_CATEGORIE LIKE :q2 OR p.DESCRIPTION LIKE :q3
        ");
        $stmtCount->execute([':q1'=>$like,':q2'=>$like,':q3'=>$like]);
        $total = (int)$stmtCount->fetchColumn();

        $totalPages = max(1, ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT p.*,
                   sc.NOM_SOUS_CATEGORIE,
                   c.NOM_CATEGORIE,
                   COALESCE(NULLIF(p.PRIX_PROMO,0)*p.EN_PROMO, p.PRIX) AS prix_final,
                   (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
            FROM produit p
            LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
            LEFT JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
            WHERE p.NOM_PRODUIT LIKE :q1 OR sc.NOM_SOUS_CATEGORIE LIKE :q2 OR p.DESCRIPTION LIKE :q3
            ORDER BY $orderBy
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute([':q1'=>$like,':q2'=>$like,':q3'=>$like]);
        $produits = $stmt->fetchAll();
    } catch (PDOException $e) {
        $produits = [];
        $total    = 0;
    }
}


$favorisIds = [];
if (!empty($_SESSION['client_id'])) {
    $s = $pdo->prepare("SELECT ID_PRODUIT FROM aime WHERE ID_CLIENT = ?");
    $s->execute([$_SESSION['client_id']]);
    $favorisIds = array_flip(array_column($s->fetchAll(), 'ID_PRODUIT'));
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
    <title><?= $q ? 'Résultats pour "'.htmlspecialchars($q).'" — Velvet' : 'Recherche — Velvet' ?></title>
    <link rel="icon" type="image/png" href="images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <style>
        
        .search-header {
            background: #0a0a0a;
            padding: 60px 0 45px;
            text-align: center;
        }
        .search-header-label {
            font-size: 10px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: 14px;
        }
        .search-header h1 {
            font-family: 'Anton', sans-serif;
            font-size: clamp(2rem, 5vw, 3.5rem);
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .search-header h1 em {
            font-style: normal;
            color: rgba(255,255,255,0.45);
        }
        .search-count {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
        }

        
        .search-bar-inline {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .search-bar-form {
            display: flex;
            align-items: center;
            gap: 0;
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid #000;
            border-radius: 10px;
            overflow: hidden;
        }
        .search-bar-form input {
            flex: 1;
            border: none;
            outline: none;
            padding: 12px 18px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: #111;
        }
        .search-bar-form button {
            background: #000;
            color: #fff;
            border: none;
            padding: 12px 22px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-bar-form button:hover { background: #222; }

        
        .sort-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 0;
            flex-wrap: wrap;
        }
        .sort-bar-left {
            font-size: 11px;
            color: #999;
            letter-spacing: 1px;
        }
        .sort-bar-left strong { color: #000; font-size: 13px; }
        .sort-select {
            appearance: none;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            padding: 7px 32px 7px 12px;
            font-size: 11px;
            font-family: 'DM Sans', sans-serif;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23999'/%3E%3C/svg%3E") no-repeat right 10px center / 10px #fff;
            cursor: pointer;
            outline: none;
            color: #333;
        }

        
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
            display: block;
        }
        .prod-card:hover .prod-img-wrap img { transform: scale(1.06); }
        .prod-no-img {
            width: 100%; height: 100%;
            background: #f0ece8;
            display: flex; align-items: center; justify-content: center;
            color: #ccc; font-size: 2.5rem;
        }
        .badge-promo {
            position: absolute; top: 12px; left: 12px;
            background: #e63946; color: #fff;
            font-size: 9px; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; padding: 4px 10px; border-radius: 20px;
        }
        .badge-nouveau {
            position: absolute; top: 12px; left: 12px;
            background: #000; color: #fff;
            font-size: 9px; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; padding: 4px 10px; border-radius: 20px;
        }
        .badge-categorie {
            position: absolute; bottom: 10px; left: 10px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
            font-size: 9px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; padding: 3px 10px; border-radius: 20px;
            color: #333;
        }
        .btn-wish {
            position: absolute; top: 12px; right: 12px;
            width: 34px; height: 34px;
            background: #fff; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            cursor: pointer; transition: all 0.2s; z-index: 2;
        }
        .btn-wish i { font-size: 13px; transition: 0.2s; }
        .btn-wish:hover { background: #e63946; }
        .btn-wish:hover i { color: #fff !important; }
        .btn-wish.active { background: #fff5f5; border: 1.5px solid #e63946; }
        .btn-wish.active i { color: #e63946 !important; }

        .prod-body { padding: 14px 16px 16px; }
        .prod-sc { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: #bbb; margin-bottom: 3px; }
        .prod-name { font-size: 13px; font-weight: 700; color: #111; margin-bottom: 8px; line-height: 1.3; }
        .prod-prices { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .price-final { font-size: 15px; font-weight: 800; color: #000; }
        .price-final.sale { color: #e63946; }
        .price-old { font-size: 11px; color: #bbb; text-decoration: line-through; }
        .btn-cart {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 11px;
            background: #000; color: #fff;
            border: none; border-radius: 8px;
            font-size: 11px; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; cursor: pointer; transition: all 0.2s;
        }
        .btn-cart:hover { background: #222; }
        .btn-cart:disabled { background: #ccc; cursor: not-allowed; }

        
        .prod-col {
            opacity: 0; transform: translateY(20px);
            animation: fadeUp 0.45s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        
        .empty-search { text-align: center; padding: 80px 0; }
        .empty-search i { font-size: 3rem; color: #ddd; display: block; margin-bottom: 20px; }
        .empty-search h3 { font-family: 'Anton', sans-serif; font-size: 1.4rem; margin-bottom: 8px; text-transform: uppercase; }
        .empty-search p { font-size: 13px; color: #999; margin-bottom: 24px; }
        .suggestions-list { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
        .suggestion-tag {
            padding: 7px 16px; border: 1.5px solid #e0e0e0; border-radius: 20px;
            font-size: 11px; font-weight: 600; color: #555; text-decoration: none;
            transition: all 0.2s;
        }
        .suggestion-tag:hover { background: #000; color: #fff; border-color: #000; }

        
        #toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%);
                 background:#000; color:#fff; padding:12px 24px; border-radius:40px;
                 font-size:13px; font-weight:600; z-index:9999;
                 opacity:0; transition:opacity 0.3s; pointer-events:none; white-space:nowrap; }
        #toast.show { opacity:1; }
        #toast.error { background:#e63946; }
        #toast.warning { background:#f39c12; }
    </style>
</head>
<body>

<?php $base = ''; include 'includes/navbar.php'; ?>


<div class="search-header">
    <p class="search-header-label">Résultats de recherche</p>
    <?php if ($q): ?>
        <h1>« <em><?= htmlspecialchars($q) ?></em> »</h1>
        <p class="search-count">
            <?= $total > 0
                ? $total . ' article' . ($total > 1 ? 's' : '') . ' trouvé' . ($total > 1 ? 's' : '')
                : 'Aucun résultat' ?>
        </p>
    <?php else: ?>
        <h1>Recherche</h1>
    <?php endif; ?>
</div>


<div class="search-bar-inline">
    <div class="container">
        <form class="search-bar-form" action="recherche.php" method="GET">
            <input type="text"
                   name="q"
                   value="<?= htmlspecialchars($q) ?>"
                   placeholder="Rechercher un produit, une catégorie..."
                   autofocus>
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>


<div class="container py-4 pb-5">

    <?php if ($q === ''): ?>
    
    <div class="empty-search">
        <i class="fas fa-search"></i>
        <h3>Que cherchez-vous ?</h3>
        <p>Tapez le nom d'un produit ou d'une catégorie ci-dessus.</p>
        <div class="suggestions-list">
            <?php
            $tags = $pdo->query("SELECT NOM_SOUS_CATEGORIE FROM sous_categorie LIMIT 6")->fetchAll();
            foreach ($tags as $t):
            ?>
            <a href="recherche.php?q=<?= urlencode($t['NOM_SOUS_CATEGORIE']) ?>" class="suggestion-tag">
                <?= htmlspecialchars(ucfirst(strtolower($t['NOM_SOUS_CATEGORIE']))) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php elseif (empty($produits)): ?>
    
    <div class="empty-search">
        <i class="fas fa-box-open"></i>
        <h3>Aucun résultat</h3>
        <p>Aucun produit ne correspond à « <?= htmlspecialchars($q) ?> ».</p>
        <p style="font-size:12px;color:#bbb;margin-bottom:20px;">Essayez avec d'autres mots-clés :</p>
        <div class="suggestions-list">
            <?php
            $tags = $pdo->query("SELECT NOM_SOUS_CATEGORIE FROM sous_categorie LIMIT 6")->fetchAll();
            foreach ($tags as $t):
            ?>
            <a href="recherche.php?q=<?= urlencode($t['NOM_SOUS_CATEGORIE']) ?>" class="suggestion-tag">
                <?= htmlspecialchars(ucfirst(strtolower($t['NOM_SOUS_CATEGORIE']))) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else: ?>
    
    <div class="sort-bar">
        <div class="sort-bar-left">
            <strong><?= $total ?></strong> article<?= $total > 1 ? 's' : '' ?> pour
            « <?= htmlspecialchars($q) ?> »
        </div>
        <select class="sort-select" onchange="applySort(this.value)">
            <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Nouveautés</option>
            <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected':'' ?>>Prix croissant</option>
            <option value="price_desc" <?= $sort==='price_desc' ? 'selected':'' ?>>Prix décroissant</option>
            <option value="name"       <?= $sort==='name'       ? 'selected':'' ?>>A → Z</option>
        </select>
    </div>

    <div class="row g-3 g-md-4">
        <?php foreach ($produits as $i => $p):
            $img   = getImg($p);
            $promo = $p['EN_PROMO'] && $p['PRIX_PROMO'];
            $stock = (int)$p['stock_total'];
            $pid   = $p['ID_PRODUIT'];
            $isFav = isset($favorisIds[$pid]);
            
            $imgSrc = $img ? $img : '';
            if ($imgSrc && !preg_match('/^(https?:\/\/|\/\/|\/)/i', $imgSrc)) $imgSrc = $imgSrc;
        ?>
        <div class="col-6 col-md-4 col-lg-3 prod-col" style="animation-delay:<?= $i * 0.04 ?>s">
            <div class="prod-card">
                <div class="prod-img-wrap">
                    <a href="client/produit.php?id=<?= $pid ?>">
                        <?php if ($imgSrc): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="<?= htmlspecialchars($p['NOM_PRODUIT']) ?>"
                                 onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="prod-no-img"><i class="fas fa-shirt"></i></div>
                        <?php endif; ?>
                    </a>

                    <?php if ($promo): ?>
                        <span class="badge-promo">Promo</span>
                    <?php else: ?>
                        <span class="badge-nouveau">New</span>
                    <?php endif; ?>

                    <?php if (!empty($p['NOM_SOUS_CATEGORIE'])): ?>
                        <span class="badge-categorie"><?= htmlspecialchars(ucfirst(strtolower($p['NOM_SOUS_CATEGORIE']))) ?></span>
                    <?php endif; ?>

                    
                    <button class="btn-wish <?= $isFav ? 'active' : '' ?>"
                            onclick="toggleFav(<?= $pid ?>, this)"
                            title="Favoris">
                        <i class="<?= $isFav ? 'fas' : 'far' ?> fa-heart"
                           style="color:<?= $isFav ? '#e63946' : '#777' ?>"></i>
                    </button>
                </div>

                <div class="prod-body">
                    <?php if ($p['NOM_SOUS_CATEGORIE']): ?>
                        <p class="prod-sc"><?= htmlspecialchars($p['NOM_SOUS_CATEGORIE']) ?></p>
                    <?php endif; ?>
                    <a href="client/produit.php?id=<?= $pid ?>" style="text-decoration:none;">
                        <p class="prod-name"><?= htmlspecialchars($p['NOM_PRODUIT']) ?></p>
                    </a>
                    <div class="prod-prices">
                        <?php if ($promo): ?>
                            <span class="price-final sale"><?= number_format($p['PRIX_PROMO'],0) ?> DH</span>
                            <span class="price-old"><?= number_format($p['PRIX'],0) ?> DH</span>
                        <?php else: ?>
                            <span class="price-final"><?= number_format($p['PRIX'],0) ?> DH</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-cart"
                            id="cartbtn-<?= $pid ?>"
                            data-add-cart="<?= $pid ?>"
                            data-cart-id="<?= $pid ?>"
                            <?= $stock <= 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-shopping-bag"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    
    <?php if ($totalPages > 1): ?>
    <div class="velvet-pagination" style="margin-top:40px;">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="vpg-btn vpg-prev"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($p=1;$p<=$totalPages;$p++): if($p===1||$p===$totalPages||abs($p-$page)<=2): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="vpg-btn <?= $p===$page?'vpg-active':'' ?>"><?= $p ?></a>
        <?php elseif(abs($p-$page)===3): ?><span class="vpg-dots">…</span><?php endif; endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="vpg-btn vpg-next"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
<div id="toast" class="toast-msg"></div>
<script src="JS/script.js"></script>
<script>
function applySort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    window.location.href = url.toString();
}

</script>
</body>
</html>