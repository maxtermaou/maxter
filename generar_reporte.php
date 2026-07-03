<?php
require_once 'seguridad.php';
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;
require_once 'conexion.php';

// =========================================================================
// 🎚️ EL INTERRUPTOR MÁGICO: Cambia la 'B' por una 'A' para probar la otra opción
$formato_reporte = 'B'; 
// Opción A: Todo mezclado en una sola tabla infinita.
// Opción B: Dos tablas (1. Asistencias, 2. Excepciones/Inasistencias).
// =========================================================================

// ==========================================
// 1. CEREBRO MULTI-SEDE Y VARIABLES
// ==========================================
$q_user = $conexion->prepare("SELECT rol, id_nucleo FROM usuarios WHERE usuario = :user");
$q_user->execute([':user' => $_SESSION['usuario']]);
$datos_usuario = $q_user->fetch(PDO::FETCH_ASSOC);

$mi_rol = $datos_usuario['rol'] ?? 'Admin_Sede';
$mi_nucleo = $datos_usuario['id_nucleo'];

$filtro_sede_sql = "";
if ($mi_rol !== 'SuperAdmin') {
    $filtro_sede_sql = " AND e.id_nucleo = " . (int)$mi_nucleo;
}

$nombre_sede = "VISIÓN NACIONAL (TODOS LOS NÚCLEOS)";
if ($mi_rol !== 'SuperAdmin' && $mi_nucleo) {
    $q_sede = $conexion->prepare("SELECT nombre_nucleo FROM nucleos WHERE id_nucleo = :id");
    $q_sede->execute([':id' => $mi_nucleo]);
    $res_sede = $q_sede->fetch(PDO::FETCH_ASSOC);
    if($res_sede) {
        // Formateo para que se vea formal en el membrete del PDF
        $nombre_sede = "NÚCLEO / EXTENSIÓN: " . strtoupper($res_sede['nombre_nucleo']);
    }
}

// Filtros de la URL
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

$periodo_texto = "PERÍODO: HISTÓRICO COMPLETO";
if ($fecha_inicio !== '' && $fecha_fin !== '') {
    $periodo_texto = "PERÍODO: " . date("d/m/Y", strtotime($fecha_inicio)) . " AL " . date("d/m/Y", strtotime($fecha_fin));
} elseif ($fecha_inicio !== '') {
    $periodo_texto = "PERÍODO: DESDE EL " . date("d/m/Y", strtotime($fecha_inicio));
}

// 1. Preparar las condiciones de búsqueda para las 3 tablas
$cond_r = []; $cond_a = []; $cond_j = []; $parametros = [];

if ($busqueda !== '') {
    $busq_str = "(e.nombres LIKE :busqueda OR e.apellidos LIKE :busqueda OR e.cedula LIKE :busqueda)";
    $cond_r[] = $busq_str; $cond_a[] = $busq_str; $cond_j[] = $busq_str;
    $parametros[':busqueda'] = "%$busqueda%";
}
if ($fecha_inicio !== '') {
    $cond_r[] = "DATE(r.fecha_hora) >= :fecha_inicio";
    $cond_a[] = "a.fecha_analisis >= :fecha_inicio";
    $cond_j[] = "j.fecha_inicio >= :fecha_inicio";
    $parametros[':fecha_inicio'] = $fecha_inicio;
}
if ($fecha_fin !== '') {
    $cond_r[] = "DATE(r.fecha_hora) <= :fecha_fin";
    $cond_a[] = "a.fecha_analisis <= :fecha_fin";
    $cond_j[] = "j.fecha_inicio <= :fecha_fin";
    $parametros[':fecha_fin'] = $fecha_fin;
}

$where_r = count($cond_r) > 0 ? " WHERE " . implode(" AND ", $cond_r) : " WHERE 1=1";
$where_a = count($cond_a) > 0 ? " WHERE " . implode(" AND ", $cond_a) : " WHERE 1=1";
$where_j = count($cond_j) > 0 ? " WHERE " . implode(" AND ", $cond_j) : " WHERE 1=1";

// Aplicamos el filtro de la sede a todas las consultas
$where_r .= $filtro_sede_sql;
$where_a .= $filtro_sede_sql;
$where_j .= $filtro_sede_sql;

// 2. Extraer los datos y unificarlos en arreglos de PHP
$registros_totales = [];
$solo_asistencias = [];
$solo_excepciones = [];

// -> Buscar Asistencias Normales
$sql_r = "SELECT r.fecha_hora, r.tipo_marca AS accion, e.cedula, e.nombres, e.apellidos, d.nombre AS departamento 
          FROM registros_asistencia r JOIN empleados e ON r.anviz_id = e.anviz_id LEFT JOIN departamentos d ON e.id_departamento = d.id_departamento $where_r";
$stmt = $conexion->prepare($sql_r); $stmt->execute($parametros);
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $item = ['fecha_orden' => $row['fecha_hora'], 'fecha_mostrar' => date("d/m/Y h:i A", strtotime($row['fecha_hora'])), 'cedula' => $row['cedula'], 'empleado' => strtoupper($row['nombres'].' '.$row['apellidos']), 'departamento' => strtoupper($row['departamento'] ?? 'N/A'), 'accion' => strtoupper($row['accion'])];
    $solo_asistencias[] = $item; $registros_totales[] = $item;
}

// -> Buscar Inasistencias y Retardos (Tabla de la IA)
$sql_a = "SELECT a.fecha_analisis AS fecha, a.tipo_observacion AS accion, e.cedula, e.nombres, e.apellidos, d.nombre AS departamento 
          FROM analisis_ia a JOIN empleados e ON a.id_empleado = e.id_empleado LEFT JOIN departamentos d ON e.id_departamento = d.id_departamento $where_a";
$stmt = $conexion->prepare($sql_a); $stmt->execute($parametros);
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $item = ['fecha_orden' => $row['fecha']." 00:00:00", 'fecha_mostrar' => date("d/m/Y", strtotime($row['fecha'])), 'cedula' => $row['cedula'], 'empleado' => strtoupper($row['nombres'].' '.$row['apellidos']), 'departamento' => strtoupper($row['departamento'] ?? 'N/A'), 'accion' => strtoupper($row['accion'])];
    $solo_excepciones[] = $item; $registros_totales[] = $item;
}

// -> Buscar Permisos y Reposos
$sql_j = "SELECT j.fecha_inicio AS fecha, j.motivo AS accion, e.cedula, e.nombres, e.apellidos, d.nombre AS departamento 
          FROM justificaciones j JOIN empleados e ON j.id_empleado = e.id_empleado LEFT JOIN departamentos d ON e.id_departamento = d.id_departamento $where_j";
$stmt = $conexion->prepare($sql_j); $stmt->execute($parametros);
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $item = ['fecha_orden' => $row['fecha']." 00:00:00", 'fecha_mostrar' => date("d/m/Y", strtotime($row['fecha'])), 'cedula' => $row['cedula'], 'empleado' => strtoupper($row['nombres'].' '.$row['apellidos']), 'departamento' => strtoupper($row['departamento'] ?? 'N/A'), 'accion' => "JUSTIFICADO: ".strtoupper($row['accion'])];
    $solo_excepciones[] = $item; $registros_totales[] = $item;
}

// Ordenamos los arreglos para que el más reciente salga arriba
usort($registros_totales, function($a, $b) { return strtotime($b['fecha_orden']) - strtotime($a['fecha_orden']); });
usort($solo_excepciones, function($a, $b) { return strtotime($b['fecha_orden']) - strtotime($a['fecha_orden']); });

// 3. Preparar Logos para DOMPDF
$logo_izq_base64 = file_exists('logo_gobierno.png') ? 'data:image/png;base64,' . base64_encode(file_get_contents('logo_gobierno.png')) : '';
$logo_der_base64 = file_exists('logo_unefa.png') ? 'data:image/png;base64,' . base64_encode(file_get_contents('logo_unefa.png')) : '';

// 4. CONSTRUCCIÓN DEL HTML
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 120px 40px 60px 40px; }
        body { font-family: Arial, sans-serif; font-size: 11px; }
        header { position: fixed; top: -100px; left: 0px; right: 0px; height: 100px; text-align: center; }
        footer { position: fixed; bottom: -40px; left: 0px; right: 0px; height: 30px; text-align: right; font-size: 10px; color: #555; border-top: 1px solid #000; padding-top: 5px; }
        .page-number:after { content: counter(page); }
        .tabla-membrete { width: 100%; border: none; }
        .texto-membrete { text-align: center; font-weight: bold; font-size: 11px; line-height: 1.2; }
        .titulo-reporte { text-align: center; font-size: 13px; font-weight: bold; margin-top: 20px; margin-bottom: 5px; }
        .subtitulo { text-align: center; font-size: 10px; margin-bottom: 20px; }
        .separador { background-color: #f2f2f2; font-weight: bold; text-align: center; font-size: 12px; padding: 10px; border: 1px solid #000; margin-top: 20px; margin-bottom: 0; }
        table.datos { width: 100%; border-collapse: collapse; margin-top: 0px; }
        table.datos th, table.datos td { border-bottom: 1px solid #000; padding: 6px 4px; text-align: left; }
        table.datos th { border-top: 1px solid #000; border-bottom: 2px solid #000; background-color: #fafafa; }
    </style>
</head>
<body>
    <header>
        <table class="tabla-membrete">
            <tr>
                <td width="15%" style="text-align: left;"><img src="' . $logo_izq_base64 . '" width="70"></td>
                <td width="70%" class="texto-membrete">
                    REPÚBLICA BOLIVARIANA DE VENEZUELA<br>
                    MINISTERIO DEL PODER POPULAR PARA LA DEFENSA<br>
                    UNIVERSIDAD NACIONAL EXPERIMENTAL<br>
                    POLITÉCNICA DE LA FUERZA ARMADA NACIONAL BOLIVARIANA<br>
                    U.N.E.F.A<br>
                    ' . $nombre_sede . '
                </td>
                <td width="15%" style="text-align: right;"><img src="' . $logo_der_base64 . '" width="65"></td>
            </tr>
        </table>
    </header>
    <footer>Página <span class="page-number"></span></footer>

    <main>
        <div class="titulo-reporte">REPORTE OFICIAL DE CONTROL DE ASISTENCIA BIOMÉTRICA</div>
        <div class="subtitulo">' . $periodo_texto . '</div>';

        // ================= DIBUJAR TABLAS SEGÚN LA OPCIÓN ELEGIDA =================
        if ($formato_reporte === 'A') {
            // OPCIÓN A: TODO MEZCLADO
            $html .= '<table class="datos"><thead><tr><th>FECHA Y HORA</th><th>CÉDULA</th><th>EMPLEADO</th><th>DEPARTAMENTO</th><th>OBSERVACIÓN</th></tr></thead><tbody>';
            if (count($registros_totales) > 0) {
                foreach ($registros_totales as $reg) {
                    $html .= '<tr><td>'.$reg['fecha_mostrar'].'</td><td>'.$reg['cedula'].'</td><td>'.$reg['empleado'].'</td><td>'.$reg['departamento'].'</td><td>'.$reg['accion'].'</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="5" style="text-align:center;">No hay registros.</td></tr>';
            }
            $html .= '</tbody></table>';

        } else {
            // OPCIÓN B: SEPARADO (ESTÁNDAR GERENCIAL)
            $html .= '<div class="separador">1. REGISTRO FÍSICO DE ENTRADAS Y SALIDAS</div>';
            $html .= '<table class="datos"><thead><tr><th>FECHA Y HORA</th><th>CÉDULA</th><th>EMPLEADO</th><th>DEPARTAMENTO</th><th>MARCA</th></tr></thead><tbody>';
            if (count($solo_asistencias) > 0) {
                foreach ($solo_asistencias as $reg) {
                    $html .= '<tr><td>'.$reg['fecha_mostrar'].'</td><td>'.$reg['cedula'].'</td><td>'.$reg['empleado'].'</td><td>'.$reg['departamento'].'</td><td>'.$reg['accion'].'</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="5" style="text-align:center;">No hay marcas de asistencia.</td></tr>';
            }
            $html .= '</tbody></table>';

            $html .= '<div class="separador" style="margin-top: 30px;">2. REPORTE DE EXCEPCIONES Y ALERTAS DE I.A. (INASISTENCIAS Y REPOSOS)</div>';
            $html .= '<table class="datos"><thead><tr><th>FECHA</th><th>CÉDULA</th><th>EMPLEADO</th><th>DEPARTAMENTO</th><th>OBSERVACIÓN / MOTIVO</th></tr></thead><tbody>';
            if (count($solo_excepciones) > 0) {
                foreach ($solo_excepciones as $reg) {
                    $html .= '<tr><td>'.$reg['fecha_mostrar'].'</td><td>'.$reg['cedula'].'</td><td>'.$reg['empleado'].'</td><td>'.$reg['departamento'].'</td><td>'.$reg['accion'].'</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="5" style="text-align:center;">No hay excepciones reportadas por la Inteligencia Artificial.</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        // =========================================================================

$html .= '
    </main>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Reporte_Asistencia_UNEFA.pdf", array("Attachment" => false));
?>