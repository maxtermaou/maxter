import mysql.connector
import pandas as pd
from datetime import datetime, timedelta

# Esta línea silencia la advertencia amarilla de pandas para que la consola se vea más limpia
import warnings
warnings.filterwarnings('ignore') 

print("Iniciando Motor de IA: Reconocimiento de Patrones de Asistencia...\n")

try:
    # 1. Conexión a la base de datos
    conexion = mysql.connector.connect(
        host="localhost", user="root", password="", database="unefa_rrhh"
    )
    
    # 2. Extraemos todo el historial
    query = """
        SELECT r.anviz_id, e.id_empleado, e.nombres, e.apellidos, r.fecha_hora, t.hora_entrada, d.nombre as departamento
        FROM registros_asistencia r
        JOIN empleados e ON r.anviz_id = e.anviz_id
        JOIN turnos t ON e.id_turno = t.id_turno
        JOIN departamentos d ON e.id_departamento = d.id_departamento
        WHERE r.tipo_marca = 'Entrada' AND r.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    """
    
    df = pd.read_sql(query, conexion)
    
    if df.empty:
        print("No hay suficientes datos históricos para analizar patrones.")
    else:
        # 3. PROCESAMIENTO
        df['fecha_hora'] = pd.to_datetime(df['fecha_hora'])
        df['hora_llegada'] = df['fecha_hora'].dt.time
        df['hora_esperada'] = pd.to_timedelta(df['hora_entrada']).dt.components.apply(
            lambda x: datetime.min.time().replace(hour=x.hours, minute=x.minutes, second=x.seconds), axis=1
        )

        # 4. BUSCANDO PATRONES
        df_tardanzas = df[df['hora_llegada'] > df['hora_esperada']]
        conteo_tardanzas = df_tardanzas.groupby(['id_empleado', 'nombres', 'apellidos']).size().reset_index(name='veces_tarde')
        
        cursor = conexion.cursor()
        fecha_hoy = datetime.now().strftime('%Y-%m-%d')
        alertas_generadas = 0
        
        # 5. GENERACIÓN DE ALERTAS INTELIGENTES (AHORA CON MEMORIA)
        for index, fila in conteo_tardanzas.iterrows():
            if fila['veces_tarde'] >= 3:
                nombre_completo = f"{fila['nombres']} {fila['apellidos']}"
                observacion = f"Patrón detectado: Retardo crónico. Ha llegado tarde {fila['veces_tarde']} veces en los últimos 30 días."
                
                # --- LA MAGIA ANTI-SPAM ---
                # Preguntamos si YA reportamos un patrón de este empleado el día de hoy
                sql_verificar = "SELECT id_analisis FROM analisis_ia WHERE id_empleado = %s AND fecha_analisis = %s AND tipo_observacion LIKE 'Patrón detectado%'"
                cursor.execute(sql_verificar, (fila['id_empleado'], fecha_hoy))
                ya_existe = cursor.fetchone()

                if not ya_existe:
                    # Si no existe, lo insertamos
                    print(f"⚠️ PATRÓN ENCONTRADO: {nombre_completo} -> Generando alerta...")
                    sql_insert = "INSERT INTO analisis_ia (id_empleado, fecha_analisis, tipo_observacion) VALUES (%s, %s, %s)"
                    cursor.execute(sql_insert, (fila['id_empleado'], fecha_hoy, observacion))
                    alertas_generadas += 1
                else:
                    # Si ya existe, avisamos en consola pero no ensuciamos la base de datos
                    print(f"✔️ {nombre_completo}: El patrón ya fue reportado hoy. Omitiendo duplicado.")
                
        if alertas_generadas > 0:
            conexion.commit()
            print(f"\nSe han enviado {alertas_generadas} nuevas alertas al Dashboard.")
        else:
            print("\nAnálisis completado: No hay patrones nuevos que reportar (ya estaban notificados).")

except mysql.connector.Error as err:
    print(f"❌ Error de base de datos: {err}")
except Exception as e:
    print(f"❌ Error en el análisis de Pandas: {e}")
finally:
    if 'conexion' in locals() and conexion.is_connected():
        conexion.close()