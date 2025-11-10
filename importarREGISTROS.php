<?php
session_start();
require_once 'tlogica.php';

$logica = new Logica();
$carpetaArchivos = __DIR__ . "/archivos";
$archivos = glob($carpetaArchivos . "/*.csv");

echo "<h2>🚀 Proceso de importación Batch</h2>";

if (empty($archivos)) {
    echo "<p>⚠️ No se encontraron archivos .csv en la carpeta <b>archivos/</b>.</p>";
    exit;
}

$resultadosLote = [];
$horaLote = date("Y-m-d H:i:s");

foreach ($archivos as $archivo) {
    echo "<hr>";
    echo "<h3>📦 Procesando archivo: " . basename($archivo) . "</h3>";

    $resultado = $logica->cargarInformacion($archivo);

    if ($resultado["ok"]) {
        echo "<p>✅ Archivo procesado correctamente.</p>";
        echo "<ul>";
        echo "<li><b>Total:</b> {$resultado['total']}</li>";
        echo "<li><b>Insertados:</b> {$resultado['insertados']}</li>";
        echo "<li><b>Errores:</b> {$resultado['errores']}</li>";
        echo "</ul>";

        $resultadosLote[] = [
            "archivo" => basename($archivo),
            "insertados" => $resultado["insertados"],
            "errores" => $resultado["errores"],
            "total" => $resultado["total"],
            "fecha" => $horaLote
        ];
    } else {
        echo "<p>❌ Error: {$resultado['msg']}</p>";
        $resultadosLote[] = [
            "archivo" => basename($archivo),
            "insertados" => 0,
            "errores" => 0,
            "total" => 0,
            "error" => $resultado["msg"],
            "fecha" => $horaLote
        ];
    }
}

// Guarda el lote completo en un JSON
if (!empty($resultadosLote)) {
    $dataFinal = [
        "hora_lote" => $horaLote,
        "archivos" => $resultadosLote
    ];

    file_put_contents(__DIR__ . "/ultimo_proceso.json", json_encode($dataFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "<p>🧾 Archivo de resumen guardado correctamente con " . count($resultadosLote) . " archivos.</p>";
}

echo "<hr><b>✅ Proceso completado.</b><br>";
?>
