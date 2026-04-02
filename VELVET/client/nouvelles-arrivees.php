<?php
session_start();
require_once __DIR__ . '/../db.php';
$base = '../';

$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

$sortAllowed = [
    'date_desc' => 'p.DATE_PRODUIT DESC, p.ID_PRODUIT DESC',
    'prix_asc'  => 'p.PRIX ASC',
    'prix_desc' => 'p.PRIX DESC',
    'nom_asc'   => 'p.NOM_PRODUIT ASC',
];
$sort    = array_key_exists($_GET['sort'] ?? '', $sortAllowed) ? $_GET['sort'] : 'date_desc';
$orderBy = $sortAllowed[$sort];

$total      = (int)$pdo->query("SELECT COUNT(*) FROM produit")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT p.*, sc.NOM_SOUS_CATEGORIE
    FROM produit p
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelles Arrivées — Velvet</title>
    <link rel="icon" type="image/png" href="../images/velvet.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/main.css">
    <style>
    .sc-hero { background:#111;color:#fff;padding:64px 0 48px;text-align:center;position:relative;overflow:hidden; }
    .sc-hero::before { content:'';position:absolute;inset:0;background:repeating-linear-gradient(-45deg,transparent,transparent 40px,rgba(255,255,255,.015) 40px,rgba(255,255,255,.015) 80px); }
    .sc-breadcrumb-hero { font-size:11px;letter-spacing:2.5px;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:16px;position:relative; }
    .sc-breadcrumb-hero a { color:rgba(255,255,255,.4);text-decoration:none;transition:color .2s; }
    .sc-breadcrumb-hero a:hover { color:rgba(255,255,255,.8); }
    .sc-hero-title { font-family:'Anton',sans-serif;font-size:clamp(3rem,8vw,6rem);letter-spacing:3px;text-transform:uppercase;line-height:1;margin-bottom:12px;position:relative; }
    .sc-hero-meta  { font-size:12px;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.35);position:relative; }
    .sc-toolbar { background:#fff;border-bottom:1px solid #eee;padding:14px 0;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.04); }
    .sc-toolbar-inner { max-width:1280px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap; }
    .sc-count { font-size:13px;color:#888;letter-spacing:1px;text-transform:uppercase; }
    .sc-count strong { color:#111;font-weight:700; }
    .sc-sort { display:flex;align-items:center;gap:10px; }
    .sc-sort label { font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#999; }
    .sc-sort select { border:1.5px solid #e0e0e0;border-radius:8px;padding:8px 34px 8px 14px;font-size:13px;font-family:'Inter',sans-serif;color:#111;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23999' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 12px center;cursor:pointer;outline:none;appearance:none;transition:border-color .2s; }
    .sc-sort select:focus { border-color:#111; }
    .sc-page-body { max-width:1280px;margin:0 auto;padding:48px 24px 80px; }
    .sc-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:28px; }
    @media(max-width:1100px){.sc-grid{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:720px) {.sc-grid{grid-template-columns:repeat(2,1fr);gap:16px;}}
    @media(max-width:420px) {.sc-grid{grid-template-columns:1fr;}}
    .sc-empty { text-align:center;padding:80px 20px;color:#aaa; }
    .sc-empty i { font-size:3.5rem;display:block;margin-bottom:16px;color:#ddd; }
    .sc-pager { display:flex;justify-content:center;align-items:center;gap:6px;margin-top:60px;flex-wrap:wrap; }
    .sc-pager a,.sc-pager span { display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:13px;font-weight:600;color:#555;text-decoration:none;transition:all .2s; }
    .sc-pager a:hover { border-color:#111;background:#111;color:#fff; }
    .sc-pager span.cur { background:#111;color:#fff;border-color:#111; }
    .sc-pager .pager-dots { border:none;color:#aaa;width:auto; }
    /* ── Prod-card (same as collection) ── */
    .prod-card { background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.05);transition:transform .3s,box-shadow .3s; }
    .prod-card:hover { transform:translateY(-5px);box-shadow:0 12px 30px rgba(0,0,0,.10); }
    .prod-img-wrap { position:relative;overflow:hidden;aspect-ratio:3/4; }
    .prod-img-wrap img { width:100%;height:100%;object-fit:cover;transition:transform .55s; }
    .prod-card:hover .prod-img-wrap img { transform:scale(1.06); }
    .prod-no-img { width:100%;height:100%;background:#f0ece8;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem; }
    .badge-nouveau { position:absolute;top:12px;left:12px;background:#000;color:#fff;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;z-index:2; }
    .badge-promo   { position:absolute;top:12px;left:12px;background:#e63946;color:#fff;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px;z-index:2; }
    .btn-wish { position:absolute;bottom:12px;right:12px;width:36px;height:36px;background:#fff;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);cursor:pointer;transition:all .2s;z-index:2; }
    .btn-wish i { color:#777;font-size:14px;transition:.2s; }
    .btn-wish:hover { background:#e63946; }
    .btn-wish:hover i { color:#fff; }
    .btn-wish.active i { color:#e63946; }
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
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="sc-hero">
    <div class="sc-breadcrumb-hero">
        <a href="../index.php">Accueil</a> <span>/</span> <span style="color:rgba(255,255,255,.7)">Nouvelles Arrivées</span>
    </div>
    <h1 class="sc-hero-title">NOUVELLES ARRIVÉES</h1>
    <p class="sc-hero-meta"><?= $total ?> article<?= $total > 1 ? 's' : '' ?> disponible<?= $total > 1 ? 's' : '' ?></p>
</div>

<div class="sc-toolbar">
    <div class="sc-toolbar-inner">
        <div class="sc-count"><strong><?= $total ?></strong> produit<?= $total > 1 ? 's' : '' ?></div>
        <div class="sc-sort">
            <label>Trier :</label>
            <select onchange="window.location.href=this.value">
                <?php foreach (['date_desc'=>'Plus récents','prix_asc'=>'Prix croissant','prix_desc'=>'Prix décroissant','nom_asc'=>'Nom A–Z'] as $k=>$v): ?>
                <option value="?sort=<?= $k ?>&page=1" <?= $sort===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="sc-page-body">
    <?php if (empty($produits)): ?>
    <div class="sc-empty"><i class="fas fa-tshirt"></i><p>Aucun produit disponible pour le moment.</p></div>
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
                <button class="btn-wish" title="Favoris"
                        onclick="toggleFav(<?= (int)$p['ID_PRODUIT'] ?>, this)">
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
                        title="Ajouter au panier">
                    <i class="fas fa-shopping-bag"></i>
                </button>
            </div>
        </div></div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="sc-pager">
        <?php if ($page > 1): ?><a href="?sort=<?= $sort ?>&page=<?= $page-1 ?>"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a><?php endif; ?>
        <?php $prev=null; $pages=[];
        for($i=1;$i<=$totalPages;$i++) if($i===1||$i===$totalPages||($i>=$page-2&&$i<=$page+2)) $pages[]=$i;
        foreach($pages as $pg):
            if($prev!==null&&$pg-$prev>1): ?><span class="pager-dots">…</span><?php endif;
            if($pg===$page): ?><span class="cur"><?=$pg?></span>
            <?php else: ?><a href="?sort=<?=$sort?>&page=<?=$pg?>"><?=$pg?></a><?php endif;
            $prev=$pg;
        endforeach; ?>
        <?php if ($page < $totalPages): ?><a href="?sort=<?= $sort ?>&page=<?= $page+1 ?>"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
<script src="../JS/Main.js"></script>
<script>
function vcWish(id, btn) {
    <?php if (!isset($_SESSION['client_id'])): ?>
    window.location.href = '../login.php'; return;
    <?php endif; ?>
    fetch('actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_fav&id_produit=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        const icon = btn.querySelector('i');
        if (d.added) { icon.className = 'fas fa-heart'; btn.style.background = '#111'; btn.style.color = '#fff'; }
        else         { icon.className = 'far fa-heart';  btn.style.background = '';    btn.style.color = '';    }
    }).catch(() => {});
}
function vcCart(id, btn) {
    <?php if (!isset($_SESSION['client_id'])): ?>
    window.location.href = '../login.php'; return;
    <?php endif; ?>
    fetch('actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_to_cart&id_produit=' + id + '&qte=1'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            updatePanierBadge(d.panier_count || 0);
            const icon = btn.querySelector('i');
            icon.className = 'fas fa-check';
            btn.style.background = '#28a745'; btn.style.color = '#fff';
            setTimeout(() => { icon.className = 'fas fa-shopping-bag'; btn.style.background = ''; btn.style.color = ''; }, 1200);
        } else {
            window.location.href = 'produit.php?id=' + id;
        }
    }).catch(() => { window.location.href = 'produit.php?id=' + id; });
}
</script>
</body>
</html>
