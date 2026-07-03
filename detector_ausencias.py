import mysql.connector
from datetime import datetime, timedelta, timezone

print("Iniciando Motor de IA: Detector de Inasistencias en Tiempo Real...\n")

try:
    conexion = mysql.connector.connect(
        host="localhost", user="root", password="", database="unefa_rrhh"
    )
    cursor = conexion.cursor(dictionary=True)

    # 1. Ajuste Horario Venezuela (UTC-4) y Traductor de Días
    hora_utc = datetime.now(timezone.utc)
    hora_venezuela = hora_utc - timedelta(hours=4)
    
    fecha_hoy_str = hora_venezuela.strftime('%Y-%m-%d')
    hora_actual_str = hora_venezuela.strftime('%H:%M:%S')
    
    dias_espanol = {0: 'Lunes', 1: 'Martes', 2: 'Miércoles', 3: 'Jueves', 4: 'Viernes', 5: 'Sábado', 6: 'Domingo'}
    dia_hoy_espanol = dias_espanol[hora_venezuela.weekday()]

    print(f"Analizando inasistencias del día: {fecha_hoy_str} ({dia_hoy_espanol}) hasta las {hora_actual_str}...\n")

    # 2. Buscar empleados que DEBÍAN trabajar HOY, su turno YA TERMINÓ, y NO marcaron entrada
    # AÑADIMOS e.id_nucleo para saber a qué sede pertenece
    sql_ausentes = """
        SELECT e.id_empleado, e.nombres, e.apellidos, h.hora_entrada, h.hora_salida, e.id_nucleo 
        FROM empleados e
        JOIN horarios_empleados h ON e.id_empleado = h.id_empleado
        WHERE e.estado = 'Activo' 
        AND h.dia_semana = %s
        AND h.hora_salida <= %s 
        AND e.id_empleado NOT IN (
            SELECT e2.id_empleado 
            FROM registros_asistencia r
            JOIN empleados e2 ON r.anviz_id = e2.anviz_id
            WHERE DATE(r.fecha_hora) = %s AND r.tipo_marca = 'Entrada'
        )
        GROUP BY e.id_empleado
    """
    cursor.execute(sql_ausentes, (dia_hoy_espanol, hora_actual_str, fecha_hoy_str))
    ausentes = cursor.fetchall()

    if not ausentes:
        print("Todos los empleados con turno finalizado asistieron, o aún hay turnos en curso.")
    else:
        alertas_generadas = 0
        
        # 3. VERIFICACIÓN DE FERIADOS, JUSTIFICACIONES Y DUPLICADOS
        for empleado in ausentes:
            id_emp = empleado['id_empleado']
            id_nuc = empleado['id_nucleo']
            nombre_completo = f"{empleado['nombres']} {empleado['apellidos']}"

            # A) PRIMERO PREGUNTAMOS SI HAY UN FERIADO ACTIVO (Nacional o de su Sede)
            sql_feriado = "SELECT motivo FROM dias_feriados WHERE %s BETWEEN fecha_inicio AND fecha_fin AND (id_nucleo IS NULL OR id_nucleo = %s)"
            cursor.execute(sql_feriado, (fecha_hoy_str, id_nuc))
            feriado = cursor.fetchone()

            if feriado:
                print(f"🌴 SUSPENSIÓN: {nombre_completo} no asistió, pero aplica DÍA FERIADO por: {feriado['motivo']}.")
                continue # Salta al siguiente empleado sin castigar

            # B) LUEGO PREGUNTAMOS SI TIENE PERMISO INDIVIDUAL
            sql_justificacion = "SELECT motivo FROM justificaciones WHERE id_empleado = %s AND %s BETWEEN fecha_inicio AND fecha_fin"
            cursor.execute(sql_justificacion, (id_emp, fecha_hoy_str))
            justificacion = cursor.fetchone()

            if justificacion:
                print(f"✅ OK: {nombre_completo} faltó, pero está JUSTIFICADO por: {justificacion['motivo']}.")
            else:
                observacion = f"Inasistencia injustificada detectada hoy {fecha_hoy_str} ({dia_hoy_espanol}). Turno: {empleado['hora_entrada']} a {empleado['hora_salida']}."
                
                sql_verificar = "SELECT id_analisis FROM analisis_ia WHERE id_empleado = %s AND fecha_analisis = %s AND tipo_observacion LIKE 'Inasistencia injustificada%'"
                cursor.execute(sql_verificar, (id_emp, fecha_hoy_str))
                ya_existe = cursor.fetchone()

                if not ya_existe:
                    print(f"❌ ALERTA: {nombre_completo} tuvo una INASISTENCIA INJUSTIFICADA.")
                    sql_alerta = "INSERT INTO analisis_ia (id_empleado, fecha_analisis, tipo_observacion) VALUES (%s, %s, %s)"
                    cursor.execute(sql_alerta, (id_emp, fecha_hoy_str, observacion))
                    alertas_generadas += 1
                else:
                    print(f"✔️ {nombre_completo}: La inasistencia ya fue reportada en este turno.")

        if alertas_generadas > 0:
            conexion.commit()
            print(f"\nSe han enviado {alertas_generadas} nuevas alertas de inasistencia al Dashboard.")
        else:
            print("\nAnálisis completado: No hay inasistencias nuevas que reportar.")

except mysql.connector.Error as err:
    print(f"Error de base de datos: {err}")

finally:
    if 'conexion' in locals() and conexion.is_connected():
        cursor.close()
        conexion.close()