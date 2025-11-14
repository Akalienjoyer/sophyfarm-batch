<?php
require_once 'tlogica.php';
$logica = new Logica();

$jsonPath = __DIR__ . "/ultimo_proceso.json";

if (!file_exists($jsonPath)) {
    die("No se encontró el archivo de resumen (ultimo_proceso.json)");
}

$datos = json_decode(file_get_contents($jsonPath), true);

if (!$datos || empty($datos["archivos"])) {
    die("Error al leer los datos del resumen.");
}

$horaLote = $datos["hora_lote"];

foreach ($datos["archivos"] as $a) {
    $nombreArchivo = $a["nombre"];
    $insertados = $a["insertados"];
    $errores = $a["errores"];
    $total = $a["total"];

    $mensaje = "Carga completada: $nombreArchivo\n" .
               "Total: $total | Insertados: $insertados | Errores: $errores\n" .
               "Hora del proceso: " . date("Y-m-d H:i:s") . " (UTC-5)";

    $logica->enviarSMSResumen("Archivo: $nombreArchivo", $insertados, $errores, $total, $mensaje);

    echo "SMS enviado para el archivo: $nombreArchivo<br>";
}
?>
