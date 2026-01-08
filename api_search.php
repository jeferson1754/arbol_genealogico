<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once 'db.php';

try {
    // TU CONSULTA SQL
    $sql = "SELECT 
                p.id,
                p.rut, 
                p.name AS nombre_persona, 
                p.dob AS fecha_nacimiento, 
                MAX(CASE WHEN pd.document_type = 'Certificado de Nacimiento' THEN 'Sí' ELSE 'No' END) AS tiene_nacimiento, 
                MAX(CASE WHEN pd.document_type = 'Certificado de Matrimonio' THEN 'Sí' ELSE 'No' END) AS tiene_matrimonio, 
                MAX(CASE WHEN pd.document_type = 'Certificado de Defunción' THEN 'Sí' ELSE 'No' END) AS tiene_defuncion 
            FROM people p 
            LEFT JOIN person_documents pd ON pd.person_id = p.id 
            WHERE (p.dod IS NULL OR p.dod = '') 
            GROUP BY p.id, p.rut, p.name, p.dob 
            ORDER BY `tiene_nacimiento` ASC, `p`.`rut` DESC";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll();

    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
