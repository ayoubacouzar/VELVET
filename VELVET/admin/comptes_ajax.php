<?php
session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); exit; }
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
if (!$client_id) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("
    SELECT c.ID_COMMANDE, c.DATE_COMMANDE, c.MONTANT_TOTAL AS TOTAL, c.STATUT_COMMANDE
    FROM commande c
    WHERE c.ID_CLIENT = ?
    ORDER BY c.DATE_COMMANDE DESC
");
$stmt->execute([$client_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);