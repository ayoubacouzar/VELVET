<?php
session_start();
require_once __DIR__ . '/../db.php';
$base = '../';

$scId = (int)($_GET['id'] ?? 0);
if (!$scId) { header('Location: ../index.php'); exit; }

// Récupérer la sous-catégorie + catégorie parent
$stmtSc = $pdo->prepare("
    SELECT sc.*, c.NOM_CATEGORIE, c.ID_CATEGORIE
    FROM sous_categorie sc
    JOIN categorie c ON sc.ID_CATEGORIE = c.ID_CATEGORIE
    WHERE sc.ID_SOUS_CATEGORIE = ?
");
$stmtSc->execute([$scId]);
$sc = $stmtSc->fetch(PDO::FETCH_ASSOC);
if (!$sc) { header('Location: ../index.php'); exit; }

// Pagination
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Tri
$sortAllowed = [
    'date_desc' => 'p.DATE_PRODUIT DESC, p.ID_PRODUIT DESC',
    'prix_asc'  => 'p.PRIX ASC',
    'prix_desc' => 'p.PRIX DESC',
    'nom_asc'   => 'p.NOM_PRODUIT ASC',
];
$sort    = array_key_exists($_GET['sort'] ?? '', $sortAllowed) ? $_GET['sort'] : 'date_desc';
$orderBy = $sortAllowed[$sort];

// Total
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM produit WHERE ID_SOUS_CATEGORIE = ?");
$stmtCount->execute([$scId]);
$total      = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Produits
$stmtProd = $pdo->prepare("
    SELECT p.*
    FROM produit p
    WHERE p.ID_SOUS_CATEGORIE = ?
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
");
$stmtProd->execute([$scId]);
$produits = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Lien catégorie parent
$nomCat = strtolower(trim($sc['NOM_CATEGORIE']));
if ($nomCat === 'femmes')      $catLink = 'collection-femme.php';
elseif ($nomCat === 'hommes')  $catLink = 'collection-homme.php';
else                           $catLink = 'produits.php?categorie=' . $sc['ID_CATEGORIE'];

$scNom = ucfirst(strtolower(trim($sc['NOM_SOUS_CATEGORIE'])));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($scNom) ?> — Velvet</title>
    <link rel="icon" type="image/png" href="../images/velvet.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
    /* ── Page Hero Header ── */
    .sc-hero {
        background: #111;
        color: #fff;
        padding: 64px 0 48px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .sc-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: repeating-linear-gradient(
            -45deg,
            transparent,
            transparent 40px,
            rgba(255,255,255,.015) 40px,
            rgba(255,255,255,.015) 80px
        );
    }
    .sc-breadcrumb-hero {
        font-size: 11px;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: rgba(255,255,255,.4);
        margin-bottom: 16px;
        position: relative;
    }
    .sc-breadcrumb-hero a { color: rgba(255,255,255,.4); text-decoration: none; transition: color .2s; }
    .sc-breadcrumb-hero a:hover { color: rgba(255,255,255,.8); }
    .sc-breadcrumb-hero span { color: rgba(255,255,255,.7); }
    .sc-hero-title {
        font-family: 'Anton', sans-serif;
        font-size: clamp(3rem, 8vw, 6rem);
        letter-spacing: 3px;
        text-transform: uppercase;
        line-height: 1;
        margin-bottom: 12px;
        position: relative;
    }
    .sc-hero-meta {
        font-size: 12px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: rgba(255,255,255,.35);
        position: relative;
    }

    /* ── Toolbar ── */
    .sc-toolbar {
        background: #fff;
        border-bottom: 1px solid #eee;
        padding: 14px 0;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .sc-toolbar-inner {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .sc-count {
        font-size: 13px;
        color: #888;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .sc-count strong { color: #111; font-weight: 700; }
    .sc-sort {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .sc-sort label { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #999; }
    .sc-sort select {
        border: 1.5px solid #e0e0e0;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 13px;
        font-family: 'Inter', sans-serif;
        color: #111;
        background: #fff;
        cursor: pointer;
        outline: none;
        transition: border-color .2s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23999' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 34px;
    }
    .sc-sort select:focus { border-color: #111; }

    /* ── Products grid ── */
    .sc-page-body {
        max-width: 1280px;
        margin: 0 auto;
        padding: 48px 24px 80px;
    }

    .sc-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 28px;
    }
    @media (max-width: 1100px) { .sc-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 720px)  { .sc-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; } }
    @media (max-width: 420px)  { .sc-grid { grid-template-columns: 1fr; } }

    /* ── Product card ── */
    /* ── prod-card (unified design) ── */
    .prod-card { background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);transition:transform .3s,box-shadow .3s; }
    .prod-card:hover { transform:translateY(-5px);box-shadow:0 12px 30px rgba(0,0,0,.10); }
    .prod-img-wrap { position:relative;overflow:hidden;aspect-ratio:3/4; }
    .prod-img-wrap img { width:100%;height:100%;object-fit:cover;transition:transform .55s; display:block; }
    .prod-card:hover .prod-img-wrap img { transform:scale(1.06); }
    .prod-no-img { width:100%;height:100%;background:#f0ece8;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem; }
    .badge-nouveau { position:absolute;top:12px;left:12px;background:#000;color:#fff;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;z-index:2; }
    .badge-promo { position:absolute;top:12px;left:12px;background:#e63946;color:#fff;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;z-index:2; }
    .btn-wish { position:absolute;bottom:12px;right:12px;width:36px;height:36px;background:#fff;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);cursor:pointer;transition:all .2s;z-index:2; }
    .btn-wish i { color:#777;font-size:14px;transition:.2s; }
    .btn-wish:hover { background:#e63946; }
    .btn-wish:hover i,.btn-wish.active i { color:#fff; }
    .prod-body { padding:14px 16px 16px; }
    .prod-sc { font-size:9px;text-transform:uppercase;letter-spacing:2px;color:#bbb;margin-bottom:3px; }
    .prod-name { font-size:13px;font-weight:700;color:#111;margin-bottom:8px;line-height:1.3; }
    .prod-prices { display:flex;align-items:center;gap:8px;margin-bottom:10px; }
    .price-final { font-size:15px;font-weight:800;color:#000; }
    .price-final.sale { color:#e63946; }
    .price-old { font-size:11px;color:#bbb;text-decoration:line-through; }
    .btn-cart { display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;background:#000;color:#fff;border:none;border-radius:50%;font-size:15px;cursor:pointer;transition:all .2s; }
    .btn-cart:hover { background:#333;transform:scale(1.08); }
    .btn-cart:disabled { background:#ccc;cursor:not-allowed;transform:none; }
    .btn-cart.added { background:#27ae60; }
    .vc_REMOVED {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
        transition: transform .3s ease, box-shadow .3s ease;
        position: relative;
        cursor: pointer;
    }
    .vc:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 32px rgba(0,0,0,.10);
    }
    .vc-img-wrap {
        position: relative;
        overflow: hidden;
        aspect-ratio: 3/4;
    }
    .vc-img-wrap img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform .55s ease;
        display: block;
    }
    .vc:hover .vc-img-wrap img { transform: scale(1.07); }

    /* badges */
    .vc-badge {
        position: absolute;
        top: 12px; left: 12px;
        font-size: 9px; font-weight: 700;
        letter-spacing: 1.5px; text-transform: uppercase;
        padding: 4px 11px; border-radius: 20px;
    }
    .vc-badge-new   { background: #111; color: #fff; }
    .vc-badge-promo { background: #e74c3c; color: #fff; }

    /* Wishlist btn */
    .vc-wish {
        position: absolute;
        top: 10px; right: 10px;
        width: 34px; height: 34px;
        background: rgba(255,255,255,.9);
        border: none; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 13px; color: #555;
        box-shadow: 0 2px 8px rgba(0,0,0,.10);
        transition: all .2s;
        backdrop-filter: blur(4px);
    }
    .vc-wish:hover { background: #111; color: #fff; transform: scale(1.1); }
    .vc-cart {
        position: absolute; top: 50px; right: 10px;
        width: 34px; height: 34px;
        background: rgba(255,255,255,.9); border: none; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 13px; color: #555;
        box-shadow: 0 2px 8px rgba(0,0,0,.10);
        transition: all .2s; backdrop-filter: blur(4px);
    }
    .vc-cart:hover { background: #111; color: #fff; transform: scale(1.1); }

    /* Quick-add overlay */
    .vc-add-overlay {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        background: rgba(0,0,0,.82);
        color: #fff;
        padding: 14px;
        text-align: center;
        font-size: 11px; font-weight: 700;
        letter-spacing: 2px; text-transform: uppercase;
        transform: translateY(100%);
        transition: transform .3s ease;
        cursor: pointer;
        border: none;
        width: 100%;
        font-family: 'Anton', sans-serif;
    }
    .vc:hover .vc-add-overlay { transform: translateY(0); }

    /* Info */
    .vc-info { padding: 14px 16px 18px; }
    .vc-cat  { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #aaa; margin-bottom: 4px; }
    .vc-name { font-weight: 600; font-size: 14px; color: #111; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vc-price { display: flex; align-items: baseline; gap: 8px; }
    .vc-price .pp-normal { font-weight: 800; font-size: 15px; color: #111; }
    .vc-price .pp-promo  { font-weight: 800; font-size: 15px; color: #e74c3c; }
    .vc-price .pp-old    { font-size: 12px; color: #bbb; text-decoration: line-through; }

    /* ── Empty state ── */
    .sc-empty {
        text-align: center;
        padding: 80px 20px;
        color: #aaa;
    }
    .sc-empty i { font-size: 3.5rem; display: block; margin-bottom: 16px; color: #ddd; }
    .sc-empty h3 { font-family: 'Anton', sans-serif; font-size: 1.6rem; letter-spacing: 2px; text-transform: uppercase; color: #ccc; margin-bottom: 8px; }
    .sc-empty p  { font-size: 14px; }

    /* ── Pagination ── */
    .sc-pager {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        margin-top: 60px;
        flex-wrap: wrap;
    }
    .sc-pager a,
    .sc-pager span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px; height: 42px;
        border: 1.5px solid #e0e0e0;
        border-radius: 10px;
        font-size: 13px; font-weight: 600;
        color: #555;
        text-decoration: none;
        transition: all .2s;
    }
    .sc-pager a:hover { border-color: #111; background: #111; color: #fff; }
    .sc-pager span.cur { background: #111; color: #fff; border-color: #111; }
    .sc-pager .pager-dots { border: none; color: #aaa; width: auto; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<!-- ── Hero Header ── -->
<div class="sc-hero">
    <div class="sc-breadcrumb-hero">
        <a href="../index.php">Accueil</a>
        <span> / </span>
        <a href="<?= htmlspecialchars($catLink) ?>">Mode <?= htmlspecialchars(ucfirst(strtolower($sc['NOM_CATEGORIE']))) ?></a>
        <span> / </span>
        <span><?= htmlspecialchars($scNom) ?></span>
    </div>
    <h1 class="sc-hero-title"><?= htmlspecialchars(strtoupper($sc['NOM_SOUS_CATEGORIE'])) ?></h1>
    <p class="sc-hero-meta"><?= $total ?> article<?= $total > 1 ? 's' : '' ?> disponible<?= $total > 1 ? 's' : '' ?></p>
</div>

<!-- ── Toolbar ── -->
<div class="sc-toolbar">
    <div class="sc-toolbar-inner">
        <div class="sc-count">
            <strong><?= $total ?></strong> produit<?= $total > 1 ? 's' : '' ?>
        </div>
        <div class="sc-sort">
            <label>Trier :</label>
            <select onchange="window.location.href=this.value">
                <?php foreach (['date_desc'=>'Plus récents','prix_asc'=>'Prix croissant','prix_desc'=>'Prix décroissant','nom_asc'=>'Nom A–Z'] as $k => $v): ?>
                <option value="?id=<?= $scId ?>&sort=<?= $k ?>&page=1"
                    <?= $sort === $k ? 'selected' : '' ?>>
                    <?= $v ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- ── Body ── -->
<div class="sc-page-body">

    <?php if (empty($produits)): ?>
    <div class="sc-empty">
        <i class="fas fa-tshirt"></i>
        <h3>Aucun produit</h3>
        <p>Aucun article n'est disponible dans cette catégorie pour le moment.</p>
    </div>
    <?php else: ?>

    <div class="row g-3 g-md-4">
        <?php foreach ($produits as $p):
            $isPromo = !empty($p['EN_PROMO']) && !empty($p['PRIX_PROMO']);
            $imgSrc  = !empty($p['IMAGE1']) ? '../' . htmlspecialchars($p['IMAGE1']) : 'https://via.placeholder.com/400x533/f4f4f4/999?text=VELVET';
            $lien    = 'produit.php?id=' . (int)$p['ID_PRODUIT'];
        ?>
        <div class="col-6 col-md-4 col-lg-3"><div class="prod-card">
            <div class="prod-img-wrap">
                <a href="<?= $lien ?>">
                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($p['NOM_PRODUIT']) ?>" loading="lazy"
                         onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-tshirt\'></i></div>'">
                </a>
                <?php if ($isPromo): ?>
                    <span class="badge-promo">Promo</span>
                <?php else: ?>
                    <span class="badge-nouveau">New</span>
                <?php endif; ?>
                <button class="btn-wish" onclick="toggleFav(<?= (int)$p['ID_PRODUIT'] ?>, this)" title="Favoris">
                    <i class="far fa-heart"></i>
                </button>
            </div>
            <div class="prod-body">
                <p class="prod-sc"><?= htmlspecialchars($scNom) ?></p>
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
                <button class="btn-cart" data-add-cart="<?= (int)$p['ID_PRODUIT'] ?>"
                        data-cart-id="<?= (int)$p['ID_PRODUIT'] ?>" title="Ajouter au panier">
                    <i class="fas fa-shopping-bag"></i>
                </button>
            </div>
        </div></div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="sc-pager">
        <?php if ($page > 1): ?>
        <a href="?id=<?= $scId ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>">
            <i class="fas fa-chevron-left" style="font-size:11px;"></i>
        </a>
        <?php endif; ?>

        <?php
        // Smart pagination: show first, last, and pages around current
        $range = 2;
        $pages = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i === 1 || $i === $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                $pages[] = $i;
            }
        }
        $prev = null;
        foreach ($pages as $pg):
            if ($prev !== null && $pg - $prev > 1): ?>
                <span class="pager-dots">…</span>
            <?php endif;
            if ($pg === $page): ?>
                <span class="cur"><?= $pg ?></span>
            <?php else: ?>
                <a href="?id=<?= $scId ?>&sort=<?= $sort ?>&page=<?= $pg ?>"><?= $pg ?></a>
            <?php endif;
            $prev = $pg;
        endforeach; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?id=<?= $scId ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>">
            <i class="fas fa-chevron-right" style="font-size:11px;"></i>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
<script>
function vcWish(id, btn) {
    toggleFav(id, btn);
}

function vcCart(id, btn) {
    const icon = btn.querySelector('i');
    const origClass = icon ? icon.className : '';
    if (icon) icon.className = 'fas fa-spinner fa-spin';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'add_to_cart');
    fd.append('id_produit', id);
    fd.append('qte', 1);

    fetch('actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast('✓ Ajouté au panier !', 'success');
                if (icon) icon.className = 'fas fa-check';
                btn.style.background = '#28a745';
                btn.style.color = '#fff';
                // Badge navbar
                const badges = document.querySelectorAll('.panier-nav-badge');
                badges.forEach(b => { b.textContent = d.panier_count; b.style.display = 'flex'; });
                setTimeout(() => {
                    if (icon) icon.className = origClass;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.disabled = false;
                }, 1800);
            } else {
                if (icon) icon.className = origClass;
                btn.disabled = false;
                showToast('✗ ' + (d.message || 'Erreur'), 'error');
            }
        })
        .catch(() => {
            if (icon) icon.className = origClass;
            btn.disabled = false;
            showToast('✗ Erreur réseau', 'error');
        });
}
</script>
</body>
</html>
