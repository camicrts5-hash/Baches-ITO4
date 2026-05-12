<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// === CONFIGURACIÓN DE LA BASE DE DATOS ===
$host = "172.20.101.107";
$port = "5432";
$dbname = "Baches";
$user = "postgres";
$password = "1155.Jona";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la base de datos: " . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'listar') {
    $sql = "SELECT r.folio, r.fecha, r.telefono, r.Medios_Reporte as medios_reporte,
                   r.estatus, r.comentarios,
                   b.calle, b.numero, b.colonia, b.codigo_POSTAL as codigo_postal, b.prioridad
            FROM Reporte r
            JOIN Baches b ON r.id_bache = b.id_bache
            ORDER BY r.folio DESC";
    $stmt = $pdo->query($sql);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($resultados);
}
elseif ($action === 'consultar') {
    $folio = $_GET['folio'] ?? 0;
    $sql = "SELECT r.folio, r.fecha, r.telefono, r.Medios_Reporte as medios_reporte,
                   r.estatus, r.comentarios,
                   b.calle, b.numero, b.colonia, b.codigo_POSTAL as codigo_postal, b.prioridad
            FROM Reporte r
            JOIN Baches b ON r.id_bache = b.id_bache
            WHERE r.folio = :folio";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':folio' => $folio]);
    $reporte = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($reporte ?: null);
}
elseif ($action === 'nuevo') {
    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['calle', 'numero', 'colonia', 'codigo_postal', 'prioridad', 'telefono', 'medios_reporte'];
    foreach ($required as $campo) {
        if (empty($data[$campo])) {
            http_response_code(400);
            echo json_encode(["error" => "Falta el campo $campo"]);
            exit;
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insertar en Baches
        $sqlBache = "INSERT INTO Baches (numero, calle, colonia, codigo_POSTAL, prioridad, coordenada_x, coordenada_y)
                     VALUES (:numero, :calle, :colonia, :cp, :prioridad, :coordenada_x, :coordenada_y ) RETURNING id_bache";
        $stmt = $pdo->prepare($sqlBache);
        $stmt->execute([
            ':numero' => $data['numero'],
            ':calle' => $data['calle'],
            ':colonia' => $data['colonia'],
            ':cp' => $data['codigo_postal'],
            ':prioridad' => $data['prioridad'],
	    ':coordenada_x, ' => $data['coordenada_x'], 
	    ':coordenada_y, ' => $data['coordenada_y'] 
        ]);
        $id_bache = $stmt->fetchColumn();
        
        // Insertar en Reporte
        $sqlReporte = "INSERT INTO Reporte (id_bache, telefono, Medios_Reporte, Estatus, Comentarios)
                       VALUES (:id_bache, :telefono, :medios, 'Pendiente', :comentarios) RETURNING folio";
        $stmt = $pdo->prepare($sqlReporte);
        $stmt->execute([
            ':id_bache' => $id_bache,
            ':telefono' => $data['telefono'],
            ':medios' => $data['medios_reporte'],
            ':comentarios' => $data['comentarios'] ?? null
        ]);
        $folio = $stmt->fetchColumn();
        
        $pdo->commit();
        echo json_encode(['success' => true, 'folio' => $folio]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
else {
    http_response_code(404);
    echo json_encode(["error" => "Acción no válida"]);
}
?>