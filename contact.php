<?php

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'contact') {
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}

$nom     = trim($_POST['nom']     ?? '');
$prenom  = trim($_POST['prenom']  ?? '');
$email   = trim($_POST['email']   ?? '');
$problem = trim($_POST['problem'] ?? '');

if (empty($nom) || empty($prenom) || empty($problem)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit;
}

$nomComplet = $prenom . ' ' . $nom;

$pdo->prepare("
    INSERT INTO message (NOM_COMPLET, EMAIL_CLIENT, OBJET, CONTENU, DATE_MESSAGE)
    VALUES (?, ?, 'Contact via site web', ?, CURDATE())
")->execute([$nomComplet, $email ?: null, $problem]);

echo json_encode(['success' => true]);