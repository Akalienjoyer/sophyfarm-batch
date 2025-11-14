<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'db.class.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

class Logica {
    private $db;
    private $config;

    public function __construct() {

        $configPath = __DIR__ . '/config/config.ini';
        if (!file_exists($configPath)) {
            die("No se encontró el archivo de configuración en: $configPath");
        }

        $this->config = parse_ini_file($configPath, true);

        if (isset($this->config['general']['timezone'])) {
            date_default_timezone_set($this->config['general']['timezone']);
        }

        $this->db = new Database();
    }

public function cargarInformacion($rutaArchivo) {
    if (!file_exists($rutaArchivo)) {
        return ["ok" => false, "msg" => "El archivo no existe: $rutaArchivo"];
    }

    $archivo = fopen($rutaArchivo, "r");
    if (!$archivo) {
        return ["ok" => false, "msg" => "No se pudo abrir el archivo: $rutaArchivo"];
    }

    $insertados = 0;
    $errores = 0;
    $total = 0;

    $conn = $this->db->connect();

    $validUnidades = [];
    $validCategorias = [];

    try {
        $stmtU = $conn->query("SELECT id FROM unidad");
        $validUnidades = $stmtU->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {}

    try {
        $stmtC = $conn->query("SELECT id FROM categoria");
        $validCategorias = $stmtC->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Exception $e) {}

    $cabecera = fgetcsv($archivo);
    if ($cabecera === false) {
        fclose($archivo);
        return ["ok" => false, "msg" => "El archivo está vacío o no tiene cabecera"];
    }

    $expectedCols = 15;
    if (count($cabecera) < $expectedCols) {
        fclose($archivo);
        return ["ok" => false, "msg" => "Cabecera inválida: se esperaban $expectedCols columnas."];
    }

    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logPath = $logDir . '/errores_' . date('Ymd_His') . '.log';
    $logFile = fopen($logPath, 'a');

    $sql = "INSERT INTO elemento (
        codigo_elemnto, nmbre_elemnto, dscrpcion_elemnto, ctgria_elemnto, und_elemnto,
        exstncia_elemnto, bdga_elemnto, precio_venta_ac, precio_venta_an,
        costo_venta, mrgen_utldad, tiene_iva, stock_minimo, stock_maximo, estado
    ) VALUES (
        :codigo, :nombre, :descripcion, :categoria, :unidad,
        :existencia, :bodega, :precio_ac, :precio_an,
        :costo, :margen, :tiene_iva, :stock_min, :stock_max, :estado
    )";

    $stmtInsert = $conn->prepare($sql);

    $regexCodigo = '/^PROD\d{6}$/';
    $regexNombre = '/^NOM_PROD\d+$/';
    $regexDescr  = '/^DES_PROD\d+$/';

    while (($fila = fgetcsv($archivo)) !== false) {
        $total++;

        if (count($fila) < $expectedCols) {
            $errores++;
            fwrite($logFile, "Línea $total: columnas insuficientes\n");
            continue;
        }

        if (count($fila) === 16) array_shift($fila);

        [$codigo, $nombre, $descripcion, $categoria, $unidad, $existencia, $bodega,
         $precio_ac, $precio_an, $costo, $margen, $tiene_iva, $stock_min, $stock_max, $estado] = $fila;

        $codigo = trim($codigo);
        $nombre = trim($nombre);
        $descripcion = trim($descripcion);

        $lineOk = true;
        $msgs = [];

        if (!preg_match($regexCodigo, $codigo)) { $lineOk = false; $msgs[] = "Código inválido"; }
        if (!preg_match($regexNombre, $nombre)) { $lineOk = false; $msgs[] = "Nombre inválido"; }
        if (!preg_match($regexDescr, $descripcion)) { $lineOk = false; $msgs[] = "Descripción inválida"; }

        if (!$lineOk) {
            $errores++;
            fwrite($logFile, "Línea $total: " . implode("; ", $msgs) . "\n");
            continue;
        }

        try {
            $stmtInsert->execute([
                ":codigo" => $codigo,
                ":nombre" => $nombre,
                ":descripcion" => $descripcion,
                ":categoria" => $categoria,
                ":unidad" => $unidad,
                ":existencia" => $existencia,
                ":bodega" => $bodega,
                ":precio_ac" => $precio_ac,
                ":precio_an" => $precio_an,
                ":costo" => $costo,
                ":margen" => $margen,
                ":tiene_iva" => $tiene_iva,
                ":stock_min" => $stock_min,
                ":stock_max" => $stock_max,
                ":estado" => $estado
            ]);
            $insertados++;
        } catch (PDOException $e) {
            $errores++;
            fwrite($logFile, "Línea $total: Error BD - " . $e->getMessage() . "\n");
        }
    }

    fclose($archivo);
    fclose($logFile);

    try {
        $stmtAudit = $conn->prepare("
            INSERT INTO auditoria (
                nombre_archivo, fecha_carga, registros_insertados,
                registros_fallidos, total_registros, detalle_error
            ) VALUES (
                :archivo, NOW(), :ins, :err, :tot, :detalle
            )
        ");

        $stmtAudit->execute([
            ":archivo" => basename($rutaArchivo),
            ":ins" => $insertados,
            ":err" => $errores,
            ":tot" => $total,
            ":detalle" => $logPath   
        ]);
    } catch (PDOException $e) {
    }

    return [
        "ok" => true,
        "archivo" => basename($rutaArchivo),
        "insertados" => $insertados,
        "errores" => $errores,
        "total" => $total,
        "log" => $logPath
    ];
}


    public function enviarCorreoResumen($nombreArchivo, $insertados, $errores, $total) {
        $cfg = $this->config['email'];
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $cfg['port'];

            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($cfg['to_email'], $cfg['to_name']);
            $mail->isHTML(true);
            $mail->Subject = "Carga completada: $nombreArchivo";
            $mail->Body = "
                <h3>Resumen del proceso de carga</h3>
                <p><b>Archivo:</b> $nombreArchivo</p>
                <p><b>Total de registros:</b> $total</p>
                <p><b>Insertados correctamente:</b> $insertados</p>
                <p><b>Errores:</b> $errores</p>
                <p>Hora del proceso: " . date("Y-m-d H:i:s") . "</p>
            ";

            $mail->send();
            echo "📨 Correo enviado correctamente.<br>";
        } catch (Exception $e) {
            echo "No se pudo enviar el correo: " . $e->getMessage() . "<br>";
        }
    }

    public function enviarSMSResumen($nombreArchivo, $insertados, $errores, $total) {
        $cfg = $this->config['sms'];
        $mensaje = "Carga completada: $nombreArchivo\nInsertados: $insertados\nErrores: $errores\nTotal: $total\nHora:" . date("Y-m-d H:i:s");

        $url = "https://api.callmebot.com/whatsapp.php?phone={$cfg['phone']}&text=" . urlencode($mensaje) . "&apikey={$cfg['apikey']}";

        try {
            $response = file_get_contents($url);
            if (strpos($response, 'Message queued') !== false) {
                echo "📱 Mensaje de WhatsApp enviado correctamente.<br>";
            } else {
                echo "⚠️ No se pudo confirmar el envío. Respuesta: $response<br>";
            }
        } catch (Exception $e) {
            echo "Error al enviar mensaje WhatsApp: " . $e->getMessage() . "<br>";
        }
    }
}
?>
