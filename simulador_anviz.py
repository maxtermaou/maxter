import mysql.connector
from datetime import datetime
import random
import time

print("Iniciando conexión con el Reloj Biométrico (Simulador)...")

try:
    # 1. Nos conectamos a la base de datos de XAMPP
    conexion = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="unefa_rrhh"
    )
    cursor = conexion.cursor()
    print("¡Conexión exitosa a la base de datos de Talento Humano!\n")

    # 2. Simulamos que alguien puso la huella
    # Elegimos al azar uno de los anviz_id que registramos en nuestra base de datos (101 a 105)
    empleados_id = [101, 102, 103]
    anviz_id_leido = random.choice(empleados_id)
    
    # Capturamos la fecha y hora exacta de este momento
    fecha_hora_actual = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    # Simulamos si está entrando o saliendo
    tipo_marca = random.choice(['Entrada', 'Salida'])

    # 3. Preparamos la orden SQL para insertar el registro
    sql = "INSERT INTO registros_asistencia (anviz_id, fecha_hora, tipo_marca) VALUES (%s, %s, %s)"
    valores = (anviz_id_leido, fecha_hora_actual, tipo_marca)

    # 4. Ejecutamos y guardamos los cambios
    cursor.execute(sql, valores)
    conexion.commit()

    print("✅ ¡BEEP! Huella aceptada.")
    print(f"Registro guardado -> Anviz ID: {anviz_id_leido} | Acción: {tipo_marca} | Hora: {fecha_hora_actual}")

except mysql.connector.Error as err:
    print(f"❌ Error al conectar con MySQL: {err}")

finally:
    # 5. Cerramos la conexión por seguridad
    if 'conexion' in locals() and conexion.is_connected():
        cursor.close()
        conexion.close()
        print("\nConexión cerrada.")