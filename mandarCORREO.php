<?php
require_once 'tlogica.php';

$logica = new Logica();

$jsonPath = __DIR__ . "/ultimo_proceso.json";

if (!file_exists($jsonPath)) {
    echo "<p>No se encontró el archivo <b>ultimo_proceso.json</b>. Asegúrate de ejecutar primero importarREGISTROS.php.</p>";
    exit;
}

$data = json_decode(file_get_contents($jsonPath), true);

if (empty($data['archivos'])) {
    echo "<p>No hay archivos registrados en el JSON.</p>";
    exit;
}

echo "<h2>📨 Enviando correos de resumen</h2>";

foreach ($data['archivos'] as $info) {
    $nombreArchivo = $info['archivo'] ?? 'Desconocido';
    $insertados    = $info['insertados'] ?? 0;
    $errores       = $info['errores'] ?? 0;
    $total         = $info['total'] ?? 0;

    echo "<hr><b>Enviando correo del archivo:</b> $nombreArchivo<br>";

    $logica->enviarCorreoResumen($nombreArchivo, $insertados, $errores, $total);
}

echo "<hr><b>Todos los correos fueron procesados.</b><br>";
?>
