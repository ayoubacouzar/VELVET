<?php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: ../login.php'); exit; }
if (!isset($_SESSION['client_id'])) { header('Location: ../login.php'); exit; }

$clientId = $_SESSION['client_id'];
$stmt = $pdo->prepare("SELECT * FROM client WHERE ID_CLIENT = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if (!$client) { session_destroy(); header('Location: ../login.php'); exit; }

// ── Commandes ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.ID_COMMANDE, c.DATE_COMMANDE, c.STATUT_COMMANDE, c.MONTANT_TOTAL,
           MIN(p.IMAGE1) AS IMAGE1,
           COUNT(ct.ID_MODELE) AS NB_ARTICLES
    FROM commande c
    LEFT JOIN contient ct      ON c.ID_COMMANDE = ct.ID_COMMANDE
    LEFT JOIN modele_produit m  ON ct.ID_MODELE  = m.ID_MODELE
    LEFT JOIN produit p         ON m.ID_PRODUIT  = p.ID_PRODUIT
    WHERE c.ID_CLIENT = ?
    GROUP BY c.ID_COMMANDE, c.DATE_COMMANDE, c.STATUT_COMMANDE, c.MONTANT_TOTAL
    ORDER BY c.DATE_COMMANDE DESC
");
$stmt->execute([$clientId]);
$commandes = $stmt->fetchAll();

// ── Panier ─────────────────────────────────────────────────────────────────
$panierId    = $_SESSION['panier_id'] ?? null;
$panierItems = [];
if ($panierId) {
    $stmt = $pdo->prepare("
        SELECT i.QUANTITE, p.NOM_PRODUIT, p.IMAGE1, p.IMAGE2, p.IMAGE3,
               p.PRIX, p.EN_PROMO, p.PRIX_PROMO, p.ID_PRODUIT,
               sc.NOM_SOUS_CATEGORIE,
               (SELECT COALESCE(SUM(QUANTITE),0) FROM modele_produit WHERE ID_PRODUIT = p.ID_PRODUIT) AS stock_total
        FROM inclure i
        JOIN produit p ON i.ID_PRODUIT = p.ID_PRODUIT
        LEFT JOIN sous_categorie sc ON p.ID_SOUS_CATEGORIE = sc.ID_SOUS_CATEGORIE
        WHERE i.ID_PANIER = ?
        LIMIT 4
    ");
    $stmt->execute([$panierId]);
    $panierItems = $stmt->fetchAll();
}

// ── Favoris ────────────────────────────────────────────────────────────────
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
    LIMIT 4
");
$stmt->execute([$clientId]);
$favoris = $stmt->fetchAll();

// ── Helpers ────────────────────────────────────────────────────────────────
function statutBadge(string $s): string {
    $map = [
        'livré'    => ['badge-delivered',  'fa-check',   'Livré'],
        'expédié'  => ['badge-shipped',    'fa-truck',   'Expédié'],
        'en cours' => ['badge-processing', 'fa-spinner', 'En cours'],
        'annulé'   => ['badge-cancelled',  'fa-times',   'Annulé'],
    ];
    $k = mb_strtolower(trim($s));
    [$cls,$ico,$lbl] = $map[$k] ?? ['badge-processing','fa-circle',ucfirst($s)];
    return "<span class='badge-status $cls'><i class='fas $ico'></i> $lbl</span>";
}
function fmtDate(string $d): string {
    $ts = strtotime($d);
    $m  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
    return date('d',$ts).' '.$m[(int)date('m',$ts)-1].' '.date('Y',$ts);
}
function prixHTML(array $p): string {
    if ($p['EN_PROMO'] && $p['PRIX_PROMO'])
        return '<span class="price-promo">'.number_format($p['PRIX_PROMO'],0).' DH</span>
                <span class="price-old">'.number_format($p['PRIX'],0).' DH</span>';
    return '<span class="price-normal">'.number_format($p['PRIX'],0).' DH</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte — Velvet</title>
    <link rel="icon" type="image/png" href="../images/VELVET_LOGO_blanc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=DM+Sans:wght@300;400;500;700&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        /* ── Product cards (same as collection-homme) ── */
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
        .badge-qty {
            position: absolute; top: 12px; right: 12px;
            background: #000; color: #fff;
            font-family: 'Anton', sans-serif;
            font-size: 11px; letter-spacing: 0.5px;
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
        .cart-line-total {
            font-family: 'Anton', sans-serif;
            font-size: 1rem;
            color: #000;
            letter-spacing: 0.5px;
        }

        /* ── Stats diamonds ── */
        .hero-stats-col {
            display: flex;
            flex-direction: column;
            gap: 14px;
            width: 100%;
            max-width: 310px;
        }
        .hero-stat-row {
            display: flex;
            align-items: center;
            gap: 22px;
            background: #fff;
            padding: 22px 32px 22px 28px;
            clip-path: polygon(0 0, 95% 0, 100% 100%, 5% 100%);
            transition: all .3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.13);
            text-decoration: none;
            color: inherit;
        }
        .hero-stat-row:hover {
            clip-path: polygon(0 0, 100% 0, 95% 100%, 0% 100%);
            box-shadow: 0 8px 32px rgba(0,0,0,0.22);
            background: #f7f5f2;
            color: inherit;
            text-decoration: none;
        }
        .hsr-number {
            font-family: 'Anton', sans-serif;
            font-size: 2.4rem;
            line-height: 1;
            color: #000;
            min-width: 44px;
        }
        .hsr-label {
            font-size: 10px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #888;
            font-weight: 500;
        }
        .hsr-icon {
            margin-left: auto;
            font-size: 11px;
            color: #ccc;
        }
    </style>
</head>
<body>

<?php $base = '../'; include __DIR__ . '/../includes/navbar.php'; ?>

<!-- ═══ HERO ═══ -->
<section class="welcome-hero">
    <div class="hero-bg-text" aria-hidden="true">VELVET</div>
    <div class="hero-noise"   aria-hidden="true"></div>
    <div class="container hero-content">
        <div class="row align-items-center g-5">

            <div class="col-lg-7">
                <span class="hero-eyebrow fade-up d1">✦ Espace Client Exclusif</span>
                <h1 class="hero-name fade-up d2">
                    Bonjour,
                    <span class="name-highlight" id="heroFullName">
                        <?= htmlspecialchars($client['PRENOM_CLIENT'].' '.$client['NOM_CLIENT']) ?>
                    </span>
                </h1>
                <p class="hero-sub fade-up d3">
                    Bienvenue dans votre espace personnel Velvet.<br>
                    <em>L'élégance commence ici.</em>
                </p>
            </div>

            <!-- ── 2 stats en losange ── -->
            <div class="col-lg-5 d-none d-lg-flex justify-content-end">
                <div class="hero-stats-col fade-up d3">
                    <!-- Commandes → clique vers #commandes -->
                    <a href="#commandes" class="hero-stat-row" onclick="smoothScroll('commandes')">
                        <span class="hsr-number"><?= count($commandes) ?></span>
                        <span class="hsr-label">Commandes</span>
                        <i class="fas fa-chevron-right hsr-icon"></i>
                    </a>
                    <!-- Favoris -->
                    <div class="hero-stat-row" style="cursor:default;">
                        <span class="hsr-number"><?= count($favoris) ?></span>
                        <span class="hsr-label">Favoris</span>
                        <i class="fas fa-heart hsr-icon" style="color:#e0b0b0;"></i>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ═══ FAVORIS (1er) ═══ -->
<section class="section-light py-5" id="favoris">
    <div class="container py-4">
        <div class="section-header reveal">
            <div>
                <h2 class="section-title">Mes Favoris</h2>
                <div class="divider-line"></div>
            </div>
            <a href="favoris.php" class="see-all-link">Voir tous les favoris <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if (empty($favoris)): ?>
        <div class="empty-state reveal">
            <i class="fas fa-heart"></i>
            <p>Aucun favori enregistré.</p>
        </div>
        <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($favoris as $fav):
                $favImg   = $fav['IMAGE1'] ?: ($fav['IMAGE2'] ?: ($fav['IMAGE3'] ?: ''));
                $favPromo = $fav['EN_PROMO'] && $fav['PRIX_PROMO'];
                $favStock = (int)($fav['stock_total'] ?? 0);
            ?>
            <div class="col-6 col-md-3 reveal" id="fav-card-<?= $fav['ID_PRODUIT'] ?>">
                <div class="prod-card">
                    <div class="prod-img-wrap">
                        <a href="produit.php?id=<?= $fav['ID_PRODUIT'] ?>">
                            <?php if ($favImg): ?>
                                <img src="<?= htmlspecialchars('../' . $favImg) ?>"
                                     alt="<?= htmlspecialchars($fav['NOM_PRODUIT']) ?>" loading="lazy"
                                     onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-shirt\'></i></div>'">
                            <?php else: ?>
                                <div class="prod-no-img"><i class="fas fa-shirt"></i></div>
                            <?php endif; ?>
                        </a>
                        <?php if ($favPromo): ?>
                            <span class="badge-promo">Promo</span>
                        <?php endif; ?>
                        <?php if ($favStock <= 0): ?>
                            <span class="badge-epuise">Épuisé</span>
                        <?php endif; ?>
                        <button class="btn-wish-remove" title="Retirer des favoris"
                                onclick="removeFavorite(<?= $fav['ID_PRODUIT'] ?>)">
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
                            <?php if ($favPromo): ?>
                                <span class="price-final sale"><?= number_format($fav['PRIX_PROMO'], 0) ?> DH</span>
                                <span class="price-old"><?= number_format($fav['PRIX'], 0) ?> DH</span>
                            <?php else: ?>
                                <span class="price-final"><?= number_format($fav['PRIX'], 0) ?> DH</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn-cart"
                                data-add-cart="<?= $fav['ID_PRODUIT'] ?>"
                                data-cart-id="<?= $fav['ID_PRODUIT'] ?>"
                                <?= $favStock <= 0 ? 'disabled' : '' ?>>
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

<!-- ═══ COMMANDES (2ème) ═══ -->
<section class="section-dark py-5" id="commandes">
    <div class="container py-4">
        <div class="section-header reveal">
            <div>
                <h2 class="section-title" style="color:#fff;">Mes Commandes</h2>
                <div class="divider-line" style="background:rgba(255,255,255,0.25);"></div>
            </div>
        </div>
        <?php if (empty($commandes)): ?>
        <div class="empty-state reveal" style="color:rgba(255,255,255,0.4);">
            <i class="fas fa-box-open"></i>
            <p>Aucune commande pour le moment.</p>
        </div>
        <?php else: ?>
        <div class="orders-grid">
            <?php foreach ($commandes as $cmd): ?>
            <div class="order-card reveal" onclick="openOrderModal(<?= $cmd['ID_COMMANDE'] ?>)" style="cursor:pointer;">
                <div class="order-card-thumb">
                    <?php
                    $imgCmd = $cmd['IMAGE1'] ? '../' . htmlspecialchars($cmd['IMAGE1']) : null;
                    if ($imgCmd): ?>
                        <img src="<?= $imgCmd ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="order-no-img"><i class="fas fa-shirt"></i></div>
                    <?php endif; ?>
                    <div class="order-count-badge"><?= $cmd['NB_ARTICLES'] ?> art.</div>
                </div>
                <div class="order-card-body">
                    <div class="order-meta">
                        <span class="order-id">#<?= $cmd['ID_COMMANDE'] ?></span>
                        <?= statutBadge($cmd['STATUT_COMMANDE'] ?? 'en cours') ?>
                    </div>
                    <div class="order-date"><i class="far fa-calendar-alt"></i> <?= fmtDate($cmd['DATE_COMMANDE']) ?></div>
                    <div class="order-total"><?= number_format($cmd['MONTANT_TOTAL'],0) ?> <span>DH</span></div>
                </div>
                <div class="order-card-cta"><i class="fas fa-chevron-right"></i></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ═══ PANIER (3ème) — caché si vide ═══ -->
<?php if (!empty($panierItems)): ?>
<section class="section-light py-5" id="panier">
    <div class="container py-4">
        <div class="section-header reveal">
            <div>
                <h2 class="section-title">Mon Panier</h2>
                <div class="divider-line"></div>
            </div>
            <a href="panier.php" class="see-all-link">Voir le panier complet <i class="fas fa-arrow-right"></i></a>
        </div>

        <div class="row g-3 g-md-4 reveal">
            <?php foreach ($panierItems as $item):
                $panImg   = $item['IMAGE1'] ?: ($item['IMAGE2'] ?: ($item['IMAGE3'] ?: ''));
                $promo    = $item['EN_PROMO'] && $item['PRIX_PROMO'];
                $prixUnit = $promo ? $item['PRIX_PROMO'] : $item['PRIX'];
                $subtotal = $prixUnit * $item['QUANTITE'];
                $panStock = (int)($item['stock_total'] ?? 0);
            ?>
            <div class="col-6 col-md-3">
                <div class="prod-card">
                    <div class="prod-img-wrap">
                        <a href="produit.php?id=<?= $item['ID_PRODUIT'] ?>">
                            <?php if ($panImg): ?>
                                <img src="<?= htmlspecialchars('../' . $panImg) ?>"
                                     alt="<?= htmlspecialchars($item['NOM_PRODUIT']) ?>" loading="lazy"
                                     onerror="this.parentNode.innerHTML='<div class=\'prod-no-img\'><i class=\'fas fa-shirt\'></i></div>'">
                            <?php else: ?>
                                <div class="prod-no-img"><i class="fas fa-shirt"></i></div>
                            <?php endif; ?>
                        </a>
                        <?php if ($promo): ?>
                            <span class="badge-promo">Promo</span>
                        <?php endif; ?>
                        <span class="badge-qty">× <?= $item['QUANTITE'] ?></span>
                    </div>
                    <div class="prod-body">
                        <?php if ($item['NOM_SOUS_CATEGORIE'] ?? null): ?>
                            <p class="prod-sc"><?= htmlspecialchars($item['NOM_SOUS_CATEGORIE']) ?></p>
                        <?php endif; ?>
                        <a href="produit.php?id=<?= $item['ID_PRODUIT'] ?>" style="text-decoration:none;">
                            <p class="prod-name"><?= htmlspecialchars($item['NOM_PRODUIT']) ?></p>
                        </a>
                        <div class="prod-prices">
                            <?php if ($promo): ?>
                                <span class="price-final sale"><?= number_format($prixUnit, 0) ?> DH</span>
                                <span class="price-old"><?= number_format($item['PRIX'], 0) ?> DH</span>
                            <?php else: ?>
                                <span class="price-final"><?= number_format($item['PRIX'], 0) ?> DH</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cart-line-total"><?= number_format($subtotal, 0) ?> DH</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php include __DIR__ . '/../includes/profile_modal.php'; ?>

<!-- ═══ MODAL COMMANDE ═══ -->
<div class="modal-overlay" id="orderModal" onclick="closeOrderModal(event)">
    <div class="modal-box modal-box-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="orderModalTitle">Commande #</h3>
            <button class="modal-close" onclick="closeOrderModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="orderModalBody">
            <div class="modal-loading"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<div id="toast" class="toast-msg"></div>
<script src="../JS/script.js"></script>
<script>
function smoothScroll(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
</body>
</html>
