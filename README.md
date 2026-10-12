# SaludUSM - INF-239 Bases de Datos - Tarea 2 (PHP + MySQL)

**Integrantes:** 
- Sofía Alarcón, 202473523-3
- Cristóbal Uteau, 202473629-9

## Requisitos

- Servidor Apache o servidor incorporado de PHP 8.1 o superior.
- MySQL 8.0+ (InnoDB, CTE no obligatorias) y la extensión `pdo_mysql` de PHP.
- Navegador web moderno. No se utiliza un framework ni se requiere npm.
- Zona horaria de la aplicación: `America/Santiago` (PHP sincroniza la sesión MySQL con el desplazamiento local vigente).

## Instalación desde cero

1. Instalar el servidor web, PHP, PDO MySQL y MySQL. En Ubuntu, por ejemplo:
   `sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql`.
2. Copiar esta carpeta a `/var/www/html/T2-Tarea2-SaludUSM` o usar el servidor de desarrollo de PHP desde la raíz del proyecto: `php -S localhost:8000`.
3. Importar **BD/salud_usm_mysql.sql** usando `mysql -u root -p < BD/salud_usm_mysql.sql`, o la pestaña Importar de phpMyAdmin, escogiendo el archivo SQL completo. **CUIDADO: este script ejecuta DROP DATABASE IF EXISTS salud_usm y borra la base anterior.**
4. Crear un usuario MySQL de la aplicación si todavía no existe (iniciar sesión en MySQL con permisos de administrador y ejecutar):

   ```sql
   CREATE USER IF NOT EXISTS 'salud_app'@'localhost' IDENTIFIED BY 'SaludUSM_Local2026!';
   GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON salud_usm.* TO 'salud_app'@'localhost';
   ```

   Los permisos anteriores permiten trabajar con el esquema y ejecutar procedimientos sin dar permiso para alterar tablas. Si tu instalación usa otro host/usuario/contraseña, editar `PHP/db.php` o usar `SALUD_DB_HOST`, `SALUD_DB_NAME`, `SALUD_DB_USER` y `SALUD_DB_PASS` como variables de entorno.
5. Abrir `http://localhost/T2-Tarea2-SaludUSM/PHP/login.php` con Apache o `http://localhost:8000/PHP/login.php` con el servidor de PHP. Si aparece "No se pudo conectar a MySQL", confirmar que está encendido MySQL, existe la BD y está habilitada la extensión `pdo_mysql`.

## Usuarios de demostración (tras importar SQL)

**Contraseña única solo para estas tres cuentas: `DemoUSM2026!`**

| Rol | RUT | Qué permite probar |
|---|---|---|
| Administrador | `11111111-1` | Centros, médicos, especialidades y estadísticas |
| Médico | `9873251-9` | Agenda, fichas de atención, diagnósticos, recetas e historial. Tiene tres especialidades y atiende en **dos centros** |
| Paciente | `8539253-0` | Agendar, cancelar, reprogramar, consultar historial y modificar perfil |

Las otras cuentas precargadas conservan contraseñas del SQL original y las cuentas nuevas usan el hash generado en PHP (`password_hash`). El script agrega dos citas futuras para poder probar el flujo. Las citas históricas del dataset sirven para el historial y las estadísticas.

## Funcionalidades por rol

- **Paciente:** registro con RUT único con formato `XXXXXXXX-X`, validaciones de campos, correo único y contraseña de al menos 8 caracteres con letras y números; login; búsqueda de médicos por nombre o especialidad; selección de especialidad, centro, médico y fecha; horas de 15 minutos entre 08:00 y 17:45 de lunes a viernes; agendamiento; consulta de citas próximas/históricas; reprogramación y cancelación; búsqueda avanzada de sus citas; historial clínico propio; actualización de perfil, contraseña y eliminación de cuenta.
- **Médico:** login; agenda diaria ordenada por hora; actualización del estado a Confirmada, Atendida o No Asistió; creación, modificación y eliminación de la atención de una cita Atendida; agregar o quitar diagnósticos CIE-10; crear, modificar y eliminar recetas; ver historial clínico de pacientes asociados a su agenda (sin modificar registros de otros médicos); datos de perfil; búsqueda avanzada de sus citas.
- **Administrador:** login; CRUD de centros; CRUD de médicos; asignación de 1 a 3 especialidades y 1 o más centros; panel de gestión por centro con total de citas y porcentaje de inasistencia (función SQL); cinco diagnósticos más frecuentes; búsqueda avanzada global; perfil y cambio de contraseña.
- **General:** roles controlados en PHP por sesión; claves foráneas y reglas de negocio en BD; navegación por enlaces entre módulos; mensajes de validación. Las citas vencidas se actualizan al entrar a las páginas principales de cada rol y a las páginas de citas. La consulta del listado tiene límite de 250 registros para evitar páginas enormes.

## Objetos SQL evaluables

- **Funciones:** `fn_validar_horario` (valida disponibilidad y reglas antes de agendar) y `fn_pct_inasistencia`.
- **Procedimientos almacenados:** `sp_agendar_cita`, `sp_reprogramar_cita`, `sp_cancelar_cita`, `sp_cancelar_citas_vencidas`. Usados desde PHP con `CALL`.
- **Triggers:** límites de especialidades, bloqueo de quitar centros o especialidades con citas futuras, integridad de atenciones y estados.
- **Vistas:** `v_citas_detalle` (citas, agenda, filtros), `v_medicos_detalle` (búsqueda) y `v_panel_centros` (estadísticas).
- **Modelo:** 17 tablas con claves primarias y foráneas, con documentación de 1FN, 2FN y 3FN en `PDF/normalizacion_3FN_SaludUSM.pdf`.

## Supuestos adoptados para puntos no explícitos

1. Existe **una cuenta y un rol por RUT**. Una persona que sea simultáneamente paciente y médico no puede tener dos perfiles independientes con el mismo RUT en el esquema actual; se requeriría ampliar el modelo a roles múltiples. Esta es una limitación declarada.
2. Cada médico debe conservar al menos **una especialidad** y **un centro**, y puede registrar un máximo de **tres especialidades**. Los triggers impiden quitar una asociación que tenga citas futuras reservadas/confirmadas.
3. Una cita pertenece a un único centro, especialidad, médico, paciente y estado. Su duración se interpreta como **15 minutos**. El reprogramar deja el estado en Reservada y exige que la fecha no haya pasado.
4. Las citas que estén en estado Reservada o Confirmada con fecha anterior a hoy pasan a Cancelada al cargar páginas autenticadas; **no se usa cron ni evento programado**. Esto responde al requisito de automatizar el vencimiento sin depender del programador de tareas del servidor.
5. Para registrar una atención, la cita debe ser Atendida. La ficha es única por cita, y puede contener varios diagnósticos y recetas. Una receta no puede existir sin su atención.
6. El médico visualiza el historial de personas que tengan al menos una cita asociada a su RUT. Las atenciones ajenas son de solo lectura. La ficha editable siempre pertenece al médico actual.
7. **Eliminar una cuenta paciente borra sus citas, atenciones, diagnósticos asociados y recetas** mediante FK ON DELETE CASCADE. La eliminación de cuenta propia está habilitada solo para pacientes. Médicos y administradores no pueden eliminar su cuenta desde la web. El administrador controla el CRUD de médicos y la BD impide borrar médicos con citas asociadas.
8. Se permite editar datos personales, correo y contraseña; **no** se modifica el RUT desde la interfaz por ser el identificador principal. El administrador no posee atributos personales en el modelo original, por lo que su perfil permite cambiar la contraseña.
9. No se implementa validación por dígito verificador matemático del RUT, sino el **formato** y la unicidad solicitados. No se realizan envíos de correos ni pagos.
10. Las credenciales de prueba tienen propósito **exclusivamente académico**. No cargar datos médicos reales ni publicar el SQL con usuarios de producción.

## Código y organización

```text
T2-Tarea2-SaludUSM/
├── BD/salud_usm_mysql.sql
├── CSS/estilo.css
├── JS/
├── PDF/normalizacion_3FN_SaludUSM.pdf
├── PHP/
│   ├── db.php, comun.php, login.php, procesar_login.php, registro.php, logout.php
│   ├── dashboard_paciente.php, dashboard_medico.php, dashboard_admin.php
│   ├── agendar.php, mis_citas.php, buscar_medicos.php, busqueda_avanzada.php
│   ├── agenda.php, atenciones.php, historial.php
│   ├── medicos.php, centros.php, panel_gestion.php, perfil.php
├── index.html
└── README.md
```