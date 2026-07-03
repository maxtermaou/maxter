import mysql.connector
from datetime import datetime, timedelta, timezone

print("Iniciando motor de análisis de Talento Humano...")

try:
    conexion = mysql.connector.connect(
        host="localhost", user="root", password="", database="unefa_rrhh"
    )
    cursor = conexion.cursor(dictionary=True)

    # 1. Ajuste Horario Venezuela (UTC-4) y Traductor de Días
    hora_utc = datetime.now(timezone.utc)
    hora_venezuela = hora_utc - timedelta(hours=4)
    fecha_hoy_str = hora_venezuela.strftime('%Y-%m-%d')
    
    dias_espanol = {0: 'Lunes', 1: 'Martes', 2: 'Miércoles', 3: 'Jueves', 4: 'Viernes', 5: 'Sábado', 6: 'Domingo'}
    dia_hoy_espanol = dias_espanol[hora_venezuela.weekday()]

    # 2. Consultar registros cruzando la información
    # AÑADIMOS e.id_nucleo
    sql_consulta = """
        SELECT r.id_registro, e.id_empleado, e.id_nucleo, e.nombres, e.apellidos, r.fecha_hora, h.hora_entrada 
        FROM registros_asistencia r
        JOIN empleados e ON r.anviz_id = e.anviz_id
        JOIN horarios_empleados h ON e.id_empleado = h.id_empleado
        WHERE DATE(r.fecha_hora) = %s AND r.tipo_marca = 'Entrada' AND h.dia_semana = %s
        GROUP BY r.id_registro
    """
    cursor.execute(sql_consulta, (fecha_hoy_str, dia_hoy_espanol))
    entradas_hoy = cursor.fetchall()

    if not entradas_hoy:
        print(f"No hay registros de entrada para analizar el día de hoy ({dia_hoy_espanol}).")
    else:
        print(f"Analizando {len(entradas_hoy)} registros de entrada del {dia_hoy_espanol}...\n")
        alertas_generadas = 0

        for registro in entradas_hoy:
            hora_real = registro['fecha_hora'].time()
            hora_esperada_td = registro['hora_entrada']
            hora_esperada = (datetime.min + hora_esperada_td).time() 

            nombre_completo = f"{registro['nombres']} {registro['apellidos']}"
            id_emp = registro['id_empleado']
            id_nuc = registro['id_nucleo']

            # ¿Llegó tarde?
            if hora_real > hora_esperada:
                
                # A) PREGUNTAMOS SI ES DÍA FERIADO
                sql_feriado = "SELECT motivo FROM dias_feriados WHERE %s BETWEEN fecha_inicio AND fecha_fin AND (id_nucleo IS NULL OR id_nucleo = %s)"
                cursor.execute(sql_feriado, (fecha_hoy_str, id_nuc))
                feriado = cursor.fetchone()

                if feriado:
                    print(f"🌴 OK: {nombre_completo} marcó entrada tarde, pero aplica DÍA FERIADO por: {feriado['motivo']}.")
                    continue # Lo perdonamos
                
                # B) PREGUNTAMOS SI TIENE JUSTIFICACIÓN INDIVIDUAL
                sql_justificacion = "SELECT motivo FROM justificaciones WHERE id_empleado = %s AND %s BETWEEN fecha_inicio AND fecha_fin"
                cursor.execute(sql_justificacion, (id_emp, fecha_hoy_str))
                justificacion = cursor.fetchone()

                if justificacion:
                    print(f"✅ OK: {nombre_completo} llegó tarde, pero está JUSTIFICADO por: {justificacion['motivo']}.")
                else:
                    observacion = f"Llegada tardía detectada. Turno: {hora_esperada}. Llegó a las: {hora_real}."
                    
                    sql_verificar = "SELECT id_analisis FROM analisis_ia WHERE id_empleado = %s AND fecha_analisis = %s AND tipo_observacion LIKE 'Llegada tardía%'"
                    cursor.execute(sql_verificar, (id_emp, fecha_hoy_str))
                    ya_existe = cursor.fetchone()

                    if not ya_existe:
                        print(f"⚠️ Alerta: {nombre_completo} - {observacion}")
                        sql_insertar_alerta = "INSERT INTO analisis_ia (id_empleado, fecha_analisis, tipo_observacion) VALUES (%s, %s, %s)"
                        cursor.execute(sql_insertar_alerta, (id_emp, fecha_hoy_str, observacion))
                        alertas_generadas += 1
                    else:
                        print(f"✔️ {nombre_completo}: El retardo ya fue reportado hoy. Omitiendo duplicado.")
            else:
                print(f"✅ {nombre_completo}: Llegó a tiempo.")

        if alertas_generadas > 0:
            conexion.commit()
            print(f"\nSe han enviado {alertas_generadas} nuevas alertas de retardo al Dashboard.")
        else:
            print("\nAnálisis completado: No hay retardos injustificados que reportar.")

except mysql.connector.Error as err:
    print(f"❌ Error en la base de datos: {err}")

finally:
    if 'conexion' in locals() and conexion.is_connected():
        cursor.close()
        conexion.close()