<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: ../login.php');
    exit;
}

$clientId           = $_SESSION['client_id'];
$navReturnToAccount = true;

$stmt = $pdo->prepare("
    SELECT p.ID_PRODUIT, p.NOM_PRODUIT, p.IMAGE1, p.IMAGE2, p.IMAGE3,
           p.PRIX, p.EN_PROMO, p.PRIX_PROMO,
           sc.NOM_SOUS_CATEGORIE, a.DATE_AIME,
           (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
    FROM aime a
    JOIN produit p ON a.ID_PRODUIT = p.ID_PRODUIT
    LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
    WHERE a.ID_CLIENT = ?
    ORDER BY a.DATE_AIME DESC
");
$stmt->execute([$clientId]);
$favoris = $stmt->fetchAll();
$count   = count($favoris);


$taillesParProduit = [];
if (!empty($favoris)) {
    $ids = array_column($favoris, 'ID_PRODUIT');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtT = $pdo->prepare("
        SELECT ID_PRODUIT, TAILLE, COULEUR, QUANTITE
        FROM modele_produit
        WHERE ID_PRODUIT IN ($placeholders)
        ORDER BY FIELD(TAILLE,'XS','S','M','L','XL','XXL'), TAILLE
    ");
    $stmtT->execute($ids);
    foreach ($stmtT->fetchAll() as $row) {
        $taillesParProduit[$row['ID_PRODUIT']][] = $row;
    }
}

function getFavImg(array $p): string {
    return $p['IMAGE1'] ?: ($p['IMAGE2'] ?: ($p['IMAGE3'] ?: ''));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Favoris — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        
        .fav-hero {
            position: relative;
            background: #0a0a0a;
            padding: 90px 0 70px;
            overflow: hidden;
            text-align: center;
        }
        .fav-hero-bg {
            position: absolute; inset: 0;
            font-family: 'Anton', sans-serif;
            font-size: clamp(100px, 18vw, 200px);
            color: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            pointer-events: none; letter-spacing: -5px;
            user-select: none;
        }
        .fav-hero-label {
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
        .fav-hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3rem, 7vw, 5.5rem);
            font-weight: 300;
            color: #fff;
            letter-spacing: 2px;
            margin-bottom: 10px;
            line-height: 1.1;
        }
        .fav-hero h1 em { font-style: italic; color: rgba(255,255,255,0.9); }
        .fav-hero p {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin: 0;
        }
        .fav-hero-line {
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

        
        .fav-wrap { padding: 50px 0 80px; }

        
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

        
        .btn-wish-remove {
            position: absolute; bottom: 12px; right: 12px;
            width: 36px; height: 36px;
            background: #fff; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            cursor: pointer; transition: all 0.2s; z-index: 2;
        }
        .btn-wish-remove i { color: #e63946; font-size: 14px; font-weight: 900; }
        .btn-wish-remove:hover { background: #e63946; }
        .btn-wish-remove:hover i { color: #fff; }

        
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
        .btn-cart.added { background: #27ae60; }

        
        .prod-col {
            opacity: 0;
            transform: translateY(22px);
            animation: fadeUp 0.5s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        
        .fav-empty {
            text-align: center;
            padding: 100px 20px;
        }
        .fav-empty i {
            font-size: 4rem;
            color: #ddd;
            display: block;
            margin-bottom: 20px;
        }
        .fav-empty h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }
        .fav-empty p { color: #999; font-size: 14px; margin-bottom: 28px; }
        .btn-shop {
            display: inline-block;
            background: #000; color: #fff;
            padding: 14px 36px;
            border-radius: 40px;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-shop:hover { background: #333; color: #fff; }

        
        .prod-col.removing {
            animation: cardOut 0.4s ease forwards;
        }
        @keyframes cardOut {
            to { opacity: 0; transform: scale(0.9) translateY(10px); }
        }

        
        #toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
                 background: #000; color: #fff; padding: 12px 24px; border-radius: 40px;
                 font-size: 13px; font-weight: 600; z-index: 9999;
                 opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap; }
        #toast.show { opacity: 1; }
        #toast.success { background: #27ae60; }
        #toast.error { background: #e63946; }
        #toast.info { background: #333; }
        #toast.warning { background: #f39c12; }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>


<div class="fav-hero">
    <div class="fav-hero-bg">FAVORIS</div>
    <div class="container position-relative">
        <div class="fav-hero-label">♥ Ma sélection</div>
        <h1>Mes <em>Favoris</em></h1>
        <div class="fav-hero-line"></div>
        <p><?= $count ?> article<?= $count > 1 ? 's' : '' ?> sauvegardé<?= $count > 1 ? 's' : '' ?></p>
    </div>
</div>


<div class="breadcrumb-bar">
    <div class="container">
        <a href="index.php">Mon compte</a>
        <i class="fa fa-chevron-right"></i>
        <span>Mes Favoris</span>
    </div>
</div>


<section class="fav-wrap">
    <div class="container">

        <?php if (empty($favoris)): ?>
        <div class="fav-empty">
            <i class="far fa-heart"></i>
            <h2>Aucun favori pour le moment</h2>
            <p>Explorez notre collection et sauvegardez vos pièces préférées.</p>
            <a href="collection-femme.php" class="btn-shop me-2">Collection Femmes</a>
            <a href="collection-homme.php" class="btn-shop" style="background:#555;">Collection Hommes</a>
        </div>

        <?php else: ?>
        <div class="row g-3 g-md-4" id="favGrid">
            <?php foreach ($favoris as $i => $fav):
                $img     = getFavImg($fav);
                $tailles = $taillesParProduit[$fav['ID_PRODUIT']] ?? [];
                $promo   = $fav['EN_PROMO'] && $fav['PRIX_PROMO'];
                $stock   = (int)$fav['stock_total'];
                $firstAvail = null;
                foreach ($tailles as $t) {
                    if ($t['QUANTITE'] > 0) { $firstAvail = $t; break; }
                }
            ?>
            <div class="col-6 col-md-4 col-lg-3 prod-col" id="fav-col-<?= $fav['ID_PRODUIT'] ?>" style="animation-delay: <?= $i * 0.045 ?>s;">
                <div class="prod-card">

                    
                    <div class="prod-img-wrap">
                        <a href="produit.php?id=<?= $fav['ID_PRODUIT'] ?>">
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars('../' . $img) ?>"
                                     alt="<?= htmlspecialchars(ucwords(strtolower($fav['NOM_PRODUIT']))) ?> — Velvet Fashion"
                                     loading="lazy"
                                     onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-shirt\'></i></div>'">
                            <?php else: ?>
                                <div class="prod-no-img"><i class="fas fa-shirt"></i></div>
                            <?php endif; ?>
                        </a>

                        <?php if ($promo): ?>
                            <span class="badge-promo">Promo</span>
                        <?php endif; ?>

                        <?php if ($stock <= 0): ?>
                            <span class="badge-epuise">Épuisé</span>
                        <?php endif; ?>

                        <button class="btn-wish-remove" title="Retirer des favoris"
                                onclick="removeFav(<?= $fav['ID_PRODUIT'] ?>)">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>

                    
                    <div class="prod-body">
                        <?php if ($fav['NOM_SOUS_CATEGORIE']): ?>
                            <p class="prod-sc"><?= htmlspecialchars($fav['NOM_SOUS_CATEGORIE']) ?></p>
                        <?php endif; ?>
                        <a href="produit.php?id=<?= $fav['ID_PRODUIT'] ?>" style="text-decoration:none;">
                            <p class="prod-name"><?= htmlspecialchars($fav['NOM_PRODUIT']) ?></p>
                        </a>
                        <div class="prod-prices">
                            <?php if ($promo): ?>
                                <span class="price-final sale"><?= number_format($fav['PRIX_PROMO'], 0) ?> DH</span>
                                <span class="price-old"><?= number_format($fav['PRIX'], 0) ?> DH</span>
                            <?php else: ?>
                                <span class="price-final"><?= number_format($fav['PRIX'], 0) ?> DH</span>
                            <?php endif; ?>
                        </div>

                        
                        <?php if (!empty($tailles)): ?>
                        <div class="sizes-row" id="sizes-<?= $fav['ID_PRODUIT'] ?>">
                            <?php foreach ($tailles as $t):
                                $dispo  = $t['QUANTITE'] > 0;
                                $active = ($firstAvail && $t['TAILLE'] === $firstAvail['TAILLE']);
                                $cls    = 'sz';
                                if (!$dispo)  $cls .= ' sz-out';
                                elseif ($active) $cls .= ' sz-active';
                            ?>
                            <span class="<?= $cls ?>"
                                  data-qty="<?= (int)$t['QUANTITE'] ?>"
                                  onclick="selectSize(this, <?= $fav['ID_PRODUIT'] ?>)">
                                <?= htmlspecialchars($t['TAILLE']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <p class="sz-stock <?= ($firstAvail && $firstAvail['QUANTITE'] <= 3) ? 'low' : '' ?>"
                           id="stock-<?= $fav['ID_PRODUIT'] ?>">
                            <?php if ($firstAvail): ?>
                                <?php if ($firstAvail['QUANTITE'] <= 3): ?>
                                    Plus que <?= $firstAvail['QUANTITE'] ?> en stock
                                <?php else: ?>
                                    En stock (<?= $firstAvail['QUANTITE'] ?> dispo.)
                                <?php endif; ?>
                            <?php elseif ($stock <= 0): ?>
                                <span style="color:#ccc;">Rupture de stock</span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>

                        
                        <button class="btn-cart"
                                id="cartbtn-<?= $fav['ID_PRODUIT'] ?>"
                                data-add-cart="<?= $fav['ID_PRODUIT'] ?>"
                                data-cart-id="<?= $fav['ID_PRODUIT'] ?>"
                                <?= $stock <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-bag"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/profile_modal.php'; ?>
<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
<script>

function removeFav(prodId) {
    const col = document.getElementById('fav-col-' + prodId);
    if (col) col.classList.add('removing');

    const data = new FormData();
    data.append('action', 'remove_favorite');
    data.append('produit_id', prodId);

    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                setTimeout(() => {
                    if (col) col.remove();
                    const grid = document.getElementById('favGrid');
                    if (grid && grid.children.length === 0) location.reload();
                }, 400);
                showToast('Retiré des favoris.', 'info');
            } else {
                if (col) col.classList.remove('removing');
                showToast('Erreur, réessayez.', 'error');
            }
        })
        .catch(() => {
            if (col) col.classList.remove('removing');
            showToast('Erreur réseau.', 'error');
        });
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
            stockEl.innerHTML = 'Plus que ' + qty + ' en stock';
            stockEl.className = 'sz-stock low';
        } else {
            stockEl.innerHTML = 'En stock (' + qty + ' dispo.)';
            stockEl.className = 'sz-stock';
        }
    }
}
</script>
</body>
</html>
