<?php
// api/tree.php

header('Content-Type: application/json');
require_once '../includes/database.php';

function buildTree($pdo, $personId)
{
    $stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
    $stmt->execute([$personId]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$person) return null;

    $treeNode = [
        'id' => $person['id'],
        'name' => $person['nombre'],
        'photo' => $person['foto']
    ];

    // Obtener hijos (descendientes directos)
    $stmt = $pdo->prepare("SELECT p.* FROM personas p 
                          JOIN ascendientes a ON p.id = a.id_descendiente 
                          WHERE a.id_ascendiente = ?");
    $stmt->execute([$personId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($children) {
        $treeNode['children'] = [];
        foreach ($children as $child) {
            $treeNode['children'][] = buildTree($pdo, $child['id']);
        }
    }

    return $treeNode;
}

// Obtener el ID de la raíz (persona más antigua sin ascendientes)
$rootId = null;
$stmt = $pdo->query("SELECT p.id FROM personas p 
                    LEFT JOIN ascendientes a ON p.id = a.id_descendiente 
                    WHERE a.id_ascendiente IS NULL 
                    LIMIT 1");
$rootId = $stmt->fetchColumn();

if ($rootId) {
    $treeData = buildTree($pdo, $rootId);
    echo json_encode($treeData);
} else {
    echo json_encode(['error' => 'No se encontró la raíz del árbol']);
}
