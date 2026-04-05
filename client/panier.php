<?php
session_start();
require_once __DIR__ . '/../db.php';

$clientId           = $_SESSION['client_id'] ?? null;
$navReturnToAccount = true;
$panierId           = $_SESSION['panier_id'] ?? null;

$items     = [];
$sousTotal = 0;

if ($panierId) {
    $stmt = $pdo->prepare("
        SELECT i.ID_PRODUIT, i.QUANTITE,
               p.NOM_PRODUIT, p.IMAGE1, p.IMAGE2, p.IMAGE3,
               p.PRIX, p.EN_PROMO, p.PRIX_PROMO,
               sc.NOM_SOUS_CATEGORIE,
               (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
        FROM inclure i
        JOIN produit p ON i.ID_PRODUIT = p.ID_PRODUIT
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        WHERE i.ID_PANIER = ?
        ORDER BY i.ID_PRODUIT
    ");
    $stmt->execute([$panierId]);
    $items = $stmt->fetchAll();
    foreach ($items as $it) {
        $p = ($it['EN_PROMO'] && $it['PRIX_PROMO']) ? $it['PRIX_PROMO'] : $it['PRIX'];
        $sousTotal += $p * $it['QUANTITE'];
    }
}

$livraison  = $sousTotal >= 500 ? 0 : 30;
$total      = $sousTotal + $livraison;
$nbArticles = array_sum(array_column($items, 'QUANTITE'));

function getCartImg(array $p): string {
    return $p['IMAGE1'] ?: ($p['IMAGE2'] ?: ($p['IMAGE3'] ?: ''));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* ── Hero (collection-homme style) ── */
        .cart-hero {
            position: relative;
            background: #0a0a0a;
            padding: 90px 0 70px;
            overflow: hidden;
            text-align: center;
        }
        .cart-hero-bg {
            position: absolute; inset: 0;
            font-family: 'Anton', sans-serif;
            font-size: clamp(100px, 18vw, 200px);
            color: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            pointer-events: none; letter-spacing: -5px;
            user-select: none;
        }
        .cart-hero-label {
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
        .cart-hero h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3rem, 7vw, 5.5rem);
            font-weight: 300;
            color: #fff;
            letter-spacing: 2px;
            margin-bottom: 10px;
            line-height: 1.1;
        }
        .cart-hero h1 em { font-style: italic; color: rgba(255,255,255,0.9); }
        .cart-hero p {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin: 0;
        }
        .cart-hero-line {
            width: 40px; height: 1px;
            background: rgba(255,255,255,0.5);
            margin: 18px auto;
        }

        /* ── Breadcrumb ── */
        .breadcrumb-bar {
            background: #f7f5f2;
            padding: 11px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .breadcrumb-bar a { color:#999; text-decoration:none; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; }
        .breadcrumb-bar a:hover { color:#000; }
        .breadcrumb-bar span { color:#000; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; font-weight:600; }
        .breadcrumb-bar i { font-size:8px; color:#ccc; margin: 0 8px; }

        /* ── Content ── */
        .cart-wrap { padding: 50px 0 80px; }

        /* ── Delivery banner ── */
        .delivery-banner {
            background: #fff;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
            color: #555;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .delivery-banner strong { color: #000; }
        .delivery-banner.free { color: #2e7d32; }
        .delivery-banner i { margin-right: 8px; }
        .delivery-progress {
            height: 4px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .delivery-fill {
            height: 100%;
            background: linear-gradient(90deg,#000,#555);
            border-radius: 4px;
            transition: width .6s ease;
        }

        /* ── Product cards (same as collection-homme) ── */
        .prod-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
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

        /* Badges */
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

        /* Remove button on card */
        .btn-card-remove {
            position: absolute; bottom: 12px; right: 12px;
            width: 36px; height: 36px;
            background: #fff; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            cursor: pointer; transition: all 0.2s; z-index: 2;
        }
        .btn-card-remove i { color: #999; font-size: 14px; }
        .btn-card-remove:hover { background: #e63946; }
        .btn-card-remove:hover i { color: #fff; }

        /* Card body */
        .prod-body { padding: 14px 16px 16px; }
        .prod-sc { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; color: #bbb; margin-bottom: 3px; }
        .prod-name { font-size: 13px; font-weight: 700; color: #111; margin-bottom: 8px; line-height: 1.3; }
        .prod-prices { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .price-final { font-size: 15px; font-weight: 800; color: #000; }
        .price-final.sale { color: #e63946; }
        .price-old { font-size: 11px; color: #bbb; text-decoration: line-through; }

        /* Qty controls */
        .qty-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .qty-controls {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid #e0e0e0;
            border-radius: 40px;
            overflow: hidden;
        }
        .qty-btn {
            width: 30px; height: 30px;
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            color: #333;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s;
        }
        .qty-btn:hover { background: #f0eeec; }
        .qty-value {
            min-width: 28px;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            color: #000;
        }
        .line-total {
            font-family: 'Anton', sans-serif;
            font-size: 1rem;
            color: #000;
            letter-spacing: 0.5px;
            margin-left: auto;
        }

        /* Animation */
        .prod-col {
            opacity: 0;
            transform: translateY(22px);
            animation: fadeUp 0.5s ease forwards;
        }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .prod-col.removing {
            animation: cardOut 0.4s ease forwards;
        }
        @keyframes cardOut {
            to { opacity: 0; transform: scale(0.9) translateY(10px); }
        }

        /* ── Summary card ── */
        .summary-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
        .summary-title {
            font-family: 'Anton', sans-serif;
            font-size: 1.3rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #000;
            margin-bottom: 24px;
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #555;
            padding: 10px 0;
            border-bottom: 1px solid #f0eeec;
        }
        .summary-line:last-of-type { border-bottom: none; }
        .summary-line .free-tag { color: #27ae60; font-weight: 600; }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Anton', sans-serif;
            font-size: 1.6rem;
            color: #000;
            letter-spacing: 1px;
            margin: 20px 0;
            padding: 16px 0;
            border-top: 2px solid #000;
        }
        .checkout-btn {
            width: 100%;
            background: #000;
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Anton', sans-serif;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s, transform .15s;
        }
        .checkout-btn:hover { background: #222; transform: translateY(-1px); }
        .checkout-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .continue-link {
            display: block;
            text-align: center;
            font-size: 12px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #999;
            text-decoration: none;
            margin-top: 16px;
        }
        .continue-link:hover { color: #000; }

        /* ── Empty state ── */
        .cart-empty {
            text-align: center;
            padding: 100px 20px;
        }
        .cart-empty i { font-size: 4rem; color: #ddd; display: block; margin-bottom: 20px; }
        .cart-empty h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            color: #333;
            margin-bottom: 10px;
        }
        .cart-empty p { color: #999; font-size: 14px; margin-bottom: 28px; }
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

        /* Toast */
        #toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
                 background: #000; color: #fff; padding: 12px 24px; border-radius: 40px;
                 font-size: 13px; font-weight: 600; z-index: 9999;
                 opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap; }
        #toast.show { opacity: 1; }
        #toast.success { background: #111; }
        #toast.error { background: #e63946; }
        #toast.info { background: #333; }
        #toast.warning { background: #f39c12; }

        /* Responsive */
        @media (max-width: 991px) {
            .summary-card { position: static; margin-top: 30px; }
        }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>

<!-- ════ HERO ════ -->
<div class="cart-hero">
    <div class="cart-hero-bg">PANIER</div>
    <div class="container position-relative">
        <div class="cart-hero-label"><i class="fas fa-shopping-bag" style="font-size:9px;"></i> Mon panier</div>
        <h1>Mon <em>Panier</em></h1>
        <div class="cart-hero-line"></div>
        <p id="cartCountLabel"><?= $nbArticles
            ? $nbArticles . ' article' . ($nbArticles > 1 ? 's' : '')
            : 'Votre panier est vide' ?></p>
    </div>
</div>

<!-- ════ BREADCRUMB ════ -->
<div class="breadcrumb-bar">
    <div class="container">
        <a href="<?= $clientId ? 'index.php' : '../index.php' ?>">Accueil</a>
        <i class="fa fa-chevron-right"></i>
        <span>Mon Panier</span>
    </div>
</div>

<!-- ════ CONTENT ════ -->
<section class="cart-wrap">
    <div class="container">

        <?php if (empty($items)): ?>
        <div class="cart-empty">
            <i class="fas fa-shopping-bag"></i>
            <h2>Votre panier est vide</h2>
            <p>Ajoutez des articles pour commencer votre commande.</p>
            <a href="collection-femme.php" class="btn-shop me-2">Collection Femmes</a>
            <a href="collection-homme.php" class="btn-shop" style="background:#555;">Collection Hommes</a>
        </div>

        <?php else: ?>
        <div class="row g-4">

            <!-- ── Left: product cards grid ── -->
            <div class="col-lg-8">

                <!-- Delivery bar -->
                <?php if ($sousTotal < 500): ?>
                <div class="delivery-banner" id="deliveryBanner">
                    <div>
                        <i class="fas fa-truck"></i>
                        Plus que <strong id="deliveryRemain"><?= number_format(500 - $sousTotal,0) ?> DH</strong>
                        pour la livraison gratuite !
                    </div>
                    <div class="delivery-progress">
                        <div class="delivery-fill" id="deliveryFill"
                             style="width:<?= min(100, round($sousTotal/500*100)) ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="delivery-banner free" id="deliveryBanner">
                    <i class="fas fa-check-circle"></i>
                    <strong>Livraison gratuite</strong> — merci pour votre commande !
                </div>
                <?php endif; ?>

                <!-- Cart items as cards -->
                <div class="row g-3 g-md-4" id="cartGrid">
                    <?php foreach ($items as $i => $it):
                        $img      = getCartImg($it);
                        $promo    = $it['EN_PROMO'] && $it['PRIX_PROMO'];
                        $prixUnit = $promo ? $it['PRIX_PROMO'] : $it['PRIX'];
                        $subtotal = $prixUnit * $it['QUANTITE'];
                        $stock    = (int)$it['stock_total'];
                        $stockMax = min(10, max(1, $stock));
                    ?>
                    <div class="col-6 col-md-4 prod-col" id="cart-col-<?= $it['ID_PRODUIT'] ?>" style="animation-delay: <?= $i * 0.045 ?>s;">
                        <div class="prod-card">

                            <!-- Image -->
                            <div class="prod-img-wrap">
                                <a href="produit.php?id=<?= $it['ID_PRODUIT'] ?>">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars('../' . $img) ?>"
                                             alt="<?= htmlspecialchars($it['NOM_PRODUIT']) ?>"
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

                                <button class="btn-card-remove" title="Retirer du panier"
                                        onclick="removeItem(<?= $it['ID_PRODUIT'] ?>, <?= (float)$prixUnit ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>

                            <!-- Info -->
                            <div class="prod-body">
                                <?php if ($it['NOM_SOUS_CATEGORIE']): ?>
                                    <p class="prod-sc"><?= htmlspecialchars($it['NOM_SOUS_CATEGORIE']) ?></p>
                                <?php endif; ?>
                                <a href="produit.php?id=<?= $it['ID_PRODUIT'] ?>" style="text-decoration:none;">
                                    <p class="prod-name"><?= htmlspecialchars($it['NOM_PRODUIT']) ?></p>
                                </a>
                                <div class="prod-prices">
                                    <?php if ($promo): ?>
                                        <span class="price-final sale"><?= number_format($prixUnit, 0) ?> DH</span>
                                        <span class="price-old"><?= number_format($it['PRIX'], 0) ?> DH</span>
                                    <?php else: ?>
                                        <span class="price-final"><?= number_format($it['PRIX'], 0) ?> DH</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Qty + line total -->
                                <div class="qty-row">
                                    <div class="qty-controls">
                                        <button class="qty-btn"
                                                onclick="changeQty(<?= $it['ID_PRODUIT'] ?>, -1, <?= $stockMax ?>)"
                                                title="Diminuer">−</button>
                                        <span class="qty-value" id="qty-<?= $it['ID_PRODUIT'] ?>"><?= $it['QUANTITE'] ?></span>
                                        <button class="qty-btn"
                                                onclick="changeQty(<?= $it['ID_PRODUIT'] ?>, +1, <?= $stockMax ?>)"
                                                title="Augmenter">+</button>
                                    </div>
                                    <span class="line-total" id="total-<?= $it['ID_PRODUIT'] ?>"><?= number_format($subtotal,0) ?> DH</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Right: summary ── -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h3 class="summary-title">Récapitulatif</h3>
                    <div class="summary-line">
                        <span>Sous-total</span>
                        <span id="summSousTotal"><?= number_format($sousTotal,0) ?> DH</span>
                    </div>
                    <div class="summary-line">
                        <span>Livraison</span>
                        <span id="summLivraison" class="<?= $livraison === 0 ? 'free-tag' : '' ?>">
                            <?= $livraison === 0 ? 'Gratuite' : $livraison . ' DH' ?>
                        </span>
                    </div>
                    <div class="summary-line">
                        <span>Destination</span>
                        <span>Oujda, MA</span>
                    </div>
                    <div class="summary-total">
                        <span>Total</span>
                        <span id="summTotal"><?= number_format($total,0) ?> DH</span>
                    </div>

                    <?php if ($clientId): ?>
                    <button class="checkout-btn" id="checkoutBtn" onclick="passerCommande()">
                        <i class="fas fa-lock me-2"></i>Passer la commande
                    </button>
                    <?php else: ?>
                    <a href="../login.php" class="checkout-btn" style="text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-sign-in-alt me-2"></i>Se connecter pour commander
                    </a>
                    <?php endif; ?>
                    <a href="<?= $clientId ? 'index.php' : '../index.php' ?>" class="continue-link">
                        <i class="fas fa-arrow-left me-1"></i>Continuer mes achats
                    </a>
                </div>
            </div>

        </div>
        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/profile_modal.php'; ?>
<div id="toast" class="toast-msg"></div>

<script>
/* ── Data state ── */
const PRIX = {
    <?php foreach ($items as $it):
        $promo = $it['EN_PROMO'] && $it['PRIX_PROMO'];
        $pu    = $promo ? $it['PRIX_PROMO'] : $it['PRIX'];
    ?>
    <?= $it['ID_PRODUIT'] ?>: <?= (float)$pu ?>,
    <?php endforeach; ?>
};
const STOCK = {
    <?php foreach ($items as $it): ?>
    <?= $it['ID_PRODUIT'] ?>: <?= min(10, max(1,(int)$it['stock_total'])) ?>,
    <?php endforeach; ?>
};

/* ── Change quantity ── */
function changeQty(produitId, delta, stockMax) {
    const qtyEl = document.getElementById('qty-' + produitId);
    if (!qtyEl) return;
    let current = parseInt(qtyEl.textContent, 10);
    let newQty  = current + delta;
    if (newQty < 1) { removeItem(produitId, PRIX[produitId]); return; }
    if (newQty > (STOCK[produitId] || 10)) {
        showToast('Stock maximum atteint.', 'warning'); return;
    }
    qtyEl.textContent = newQty;

    const lineTotal = document.getElementById('total-' + produitId);
    if (lineTotal) lineTotal.textContent = Math.round(PRIX[produitId] * newQty).toLocaleString('fr-MA') + ' DH';

    const data = new FormData();
    data.append('action', 'update_cart_qty');
    data.append('id_produit', produitId);
    data.append('qte', newQty);

    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) updateSummary(res);
        })
        .catch(() => showToast('Erreur réseau', 'error'));
}

/* ── Remove item ── */
function removeItem(produitId, prixUnit) {
    const col = document.getElementById('cart-col-' + produitId);
    if (col) col.classList.add('removing');

    const data = new FormData();
    data.append('action', 'remove_from_cart');
    data.append('id_produit', produitId);

    fetch('actions.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                setTimeout(() => { if (col) col.remove(); }, 400);
                updateSummary(res);
                showToast('Article retiré du panier.', 'info');
                if (res.panier_count === 0) {
                    setTimeout(() => location.reload(), 800);
                }
                updateNavBadge(res.panier_count);
            }
        })
        .catch(() => showToast('Erreur réseau', 'error'));
}

/* ── Update summary ── */
function updateSummary(res) {
    const sST = document.getElementById('summSousTotal');
    const sLV = document.getElementById('summLivraison');
    const sTT = document.getElementById('summTotal');
    const dFill = document.getElementById('deliveryFill');
    const dRemain = document.getElementById('deliveryRemain');
    const dBanner = document.getElementById('deliveryBanner');
    const cLabel  = document.getElementById('cartCountLabel');

    if (sST) sST.textContent = Math.round(res.sous_total).toLocaleString('fr-MA') + ' DH';
    if (sTT) sTT.textContent = Math.round(res.total).toLocaleString('fr-MA') + ' DH';
    if (sLV) {
        if (res.livraison === 0) {
            sLV.textContent = 'Gratuite';
            sLV.classList.add('free-tag');
        } else {
            sLV.textContent = res.livraison + ' DH';
            sLV.classList.remove('free-tag');
        }
    }
    if (dFill) {
        const pct = Math.min(100, Math.round(res.sous_total / 500 * 100));
        dFill.style.width = pct + '%';
    }
    if (res.sous_total >= 500 && dBanner) {
        dBanner.classList.add('free');
        dBanner.innerHTML = '<i class="fas fa-check-circle"></i> <strong>Livraison gratuite</strong> — merci pour votre commande !';
    } else if (dRemain) {
        dRemain.textContent = Math.round(500 - res.sous_total).toLocaleString('fr-MA') + ' DH';
    }
    if (cLabel && res.panier_count !== undefined) {
        cLabel.textContent = res.panier_count
            ? res.panier_count + ' article' + (res.panier_count > 1 ? 's' : '')
            : 'Votre panier est vide';
    }
    updateNavBadge(res.panier_count);
}

/* ── Update nav badge ── */
function updateNavBadge(count) {
    const badges = document.querySelectorAll('.panier-nav-badge');
    badges.forEach(b => {
        if (count > 0) { b.textContent = count; b.style.display = 'flex'; }
        else { b.style.display = 'none'; }
    });
}

/* ── Checkout ── */
async function passerCommande() {
    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement…';
    try {
        const res  = await fetch('checkout.php', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check me-2"></i>Commande confirmée !';
            btn.style.background = '#2e7d32';
            setTimeout(() => { window.location.href = 'index.php?commande=ok'; }, 1500);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i>Passer la commande';
            showToast((data.message || 'Une erreur est survenue.'), 'error');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock me-2"></i>Passer la commande';
        showToast('Erreur réseau. Veuillez réessayer.', 'error');
    }
}
</script>
<script src="../JS/script.js"></script>
</body>
</html>
