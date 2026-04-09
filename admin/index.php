<?php
session_start();
if (!isset($_SESSION["admin_id"])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../db.php';

$currentPage = basename($_SERVER['PHP_SELF']);

$stmt = $pdo->query("SELECT COUNT(*) FROM produit");      $totalProduits  = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM commande");     $totalCommandes = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM client");       $totalClients   = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM avis");         $totalAvis      = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM message");      $totalMessages  = $stmt->fetchColumn();

$allMonths = [
    1=>'Jan', 2=>'Fév', 3=>'Mar', 4=>'Avr',
    5=>'Mai', 6=>'Jun', 7=>'Jul', 8=>'Aoû',
    9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Déc'
];

$stmt = $pdo->query("SELECT MONTH(DATE_COMMANDE) as mois, COUNT(*) as total FROM commande GROUP BY MONTH(DATE_COMMANDE)");
$commandesData = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $commandesData[(int)$row['mois']] = (int)$row['total'];
$mois = []; $totaux = [];
foreach($allMonths as $n => $nom) { $mois[] = $nom; $totaux[] = $commandesData[$n] ?? 0; }

$stmt = $pdo->query("SELECT MONTH(DATE_COMMANDE) as mois, SUM(MONTANT_TOTAL) as total_revenu FROM commande GROUP BY MONTH(DATE_COMMANDE)");
$revenusData = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) $revenusData[(int)$row['mois']] = (float)$row['total_revenu'];
$moisRevenu = []; $revenus = [];
foreach($allMonths as $n => $nom) { $moisRevenu[] = $nom; $revenus[] = $revenusData[$n] ?? 0; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Velvet</title>
    <link rel="icon" type="image/png" href="../images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style_admin.css">
    <style>
        
        .global-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            background: #2e7d32;
            color: #fff;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            pointer-events: none;
            font-family: 'Inter', sans-serif;
        }
        .global-toast.error { background: #c62828; }
        .global-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>
<div class="container">

    
    <div class="sidebar">
        <div class="logo-section">
            <a href="index.php"><img src="../images/logo2.png" alt="Logo"></a>
        </div>
        <ul class="menu">
            <li><a href="index.php"          class="<?= $currentPage=='index.php'          ? 'active':'' ?>"><i class="fas fa-chart-line"></i><span> Tableau de bord</span></a></li>
            <li><a href="produits.php"        class="<?= $currentPage=='produits.php'        ? 'active':'' ?>"><i class="fas fa-box"></i><span> Produits</span></a></li>
            <li><a href="categories.php"      class="<?= $currentPage=='categories.php'      ? 'active':'' ?>"><i class="fas fa-tags"></i><span> Catégories</span></a></li>
            <li><a href="comptes.php"         class="<?= $currentPage=='comptes.php'         ? 'active':'' ?>"><i class="fas fa-users"></i><span> Comptes</span></a></li>
            <li><a href="commandes.php"       class="<?= $currentPage=='commandes.php'       ? 'active':'' ?>"><i class="fas fa-shopping-cart"></i><span> Commandes</span></a></li>
            <li><a href="avis.php"            class="<?= $currentPage=='avis.php'            ? 'active':'' ?>"><i class="fas fa-star"></i><span> Avis</span></a></li>
            <li><a href="messages.php"        class="<?= $currentPage=='messages.php'        ? 'active':'' ?>"><i class="fas fa-envelope"></i><span> Messages</span></a></li>
            <li><a href="modifier_profil.php" class="<?= $currentPage=='modifier_profil.php' ? 'active':'' ?>"><i class="fas fa-user-cog"></i><span> Modifier mon profil</span></a></li>
            <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i><span> Déconnexion</span></a></li>
        </ul>
    </div>

    
    <div class="main-content" id="mainContent">
        <h2>Tableau de bord</h2>

        <div class="stats">
            <div class="card"><h3><?= $totalProduits ?></h3><p>Produits</p></div>
            <div class="card"><h3><?= $totalCommandes ?></h3><p>Commandes</p></div>
            <div class="card"><h3><?= $totalClients ?></h3><p>Clients</p></div>
            <div class="card"><h3><?= $totalAvis ?></h3><p>Avis</p></div>
            <div class="card"><h3><?= $totalMessages ?></h3><p>Messages</p></div>
        </div>

        <div class="charts">
            <div class="chart-wrapper">
                <canvas id="commandeChart"></canvas>
            </div>
        </div>

        <div class="charts">
            <div class="chart-wrapper">
                <canvas id="revenuChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const sharedScales = {
    x: {
        ticks: { color: '#6c757d', font: { family: 'Inter', size: 11 }, maxRotation: 45 },
        grid: { display: false }
    },
    y: {
        beginAtZero: true,
        ticks: {
            color: '#6c757d',
            font: { family: 'Inter', size: 11 },
            precision: 0,
            callback: function(value) {
                if (value % 1 === 0) return value;
            }
        },
        grid: { color: '#f0f0f0' }
    }
};

const chart1 = new Chart(document.getElementById('commandeChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($mois) ?>,
        datasets: [{
            label: 'Commandes par mois',
            data: <?= json_encode($totaux) ?>,
            backgroundColor: '#000',
            borderRadius: 5,
            hoverBackgroundColor: '#444'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 800 },
        plugins: { legend: { labels: { color: '#000', font: { family: 'Inter', size: 12 } } } },
        scales: {
            ...sharedScales,
            y: {
                ...sharedScales.y,
                ticks: {
                    ...sharedScales.y.ticks,
                    stepSize: 50
                }
            }
        }
    }
});

const chart2 = new Chart(document.getElementById('revenuChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($moisRevenu) ?>,
        datasets: [{
            label: 'Revenus par mois (DH)',
            data: <?= json_encode($revenus) ?>,
            borderColor: '#000',
            backgroundColor: 'rgba(0,0,0,0.06)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#000',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 800 },
        plugins: { legend: { labels: { color: '#000', font: { family: 'Inter', size: 12 } } } },
        scales: {
            ...sharedScales,
            y: {
                ...sharedScales.y,
                ticks: {
                    ...sharedScales.y.ticks,
                    
                }
            }
        }
    }
});

const ro = new ResizeObserver(() => {
    chart1.resize();
    chart2.resize();
});
ro.observe(document.getElementById('mainContent'));


function showToast(msg, type) {
    let toast = document.getElementById('globalToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'globalToast';
        toast.className = 'global-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.className = 'global-toast ' + (type === 'success' ? 'success' : 'error') + ' show';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), 5000);
}
</script>
<script src="../JS/js_admin.js"></script>
</body>
</html>