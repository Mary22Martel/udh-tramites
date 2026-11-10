# Sistema de Gestión de Trámites – Universidad de Huánuco

Demo funcional hecha con **Laravel 12** (backend / API) y **React 19** (frontend), con modo claro y oscuro y los colores institucionales de la UDH.

Hay cuatro tipos de usuario:

| Rol | Qué hace |
|---|---|
| **Estudiante** | Ve el catálogo de trámites de su carrera, adjunta requisitos, registra su pago, sigue cada paso y subsana observaciones. Descarga su constancia final con un QR. |
| **Tesorería / Matrícula / otras áreas** | Atienden su **bandeja**: aprueban, observan o deniegan. Matrícula trabaja por **ventanillas**: cada operador ve solo sus carreras. |
| **Director de programa / Decanato** | Emiten oficios y resoluciones (con número sugerido y PDF automático). Solo ven las solicitudes de su carrera o facultad. |
| **Administrador** | Panel con indicadores y gráficos, reporte de trámites por carrera, constructor de trámites (requisitos + flujo de pasos), organización (facultades, carreras, áreas, ventanillas), usuarios y **carga de datos desde Excel**. |

---

## 1. Lo que necesitas instalar (una sola vez)

La forma más fácil en Windows es **Laravel Herd**. Trae PHP, Composer y Node.js juntos.

1. Descarga e instala Herd desde **https://herd.laravel.com/windows**.
2. Abre una terminal nueva (PowerShell o CMD) y comprueba que funciona:
   ```
   php -v
   composer -V
   node -v
   ```
   Si los tres muestran una versión, ya está todo listo. Necesitas PHP 8.2 o superior.

> No necesitas instalar MySQL: la demo usa **SQLite**, que es un solo archivo dentro del proyecto.

## 2. Instalar el proyecto

1. Descomprime el ZIP, por ejemplo en `C:\proyectos\udh-tramites`.
2. Entra a la carpeta y haz **doble clic en `instalar.bat`**.
   Este archivo hace todo por ti: descarga las librerías, crea la base de datos y carga los datos de demostración.
   Tarda unos minutos la primera vez.

<details>
<summary>Prefiero hacerlo a mano (los mismos pasos, uno por uno)</summary>

Abre una terminal **dentro de la carpeta del proyecto** y ejecuta:

```bash
composer install                     # descarga Laravel y sus librerías (carpeta vendor)
copy .env.example .env               # en Mac/Linux: cp .env.example .env
php artisan key:generate             # crea la llave de seguridad de la app
type nul > database\database.sqlite  # en Mac/Linux: touch database/database.sqlite
php artisan migrate:fresh --seed     # crea las tablas y carga los datos de demo
npm install                          # descarga React y las librerías del frontend
npm run build                        # compila el frontend
```
</details>

## 3. Abrir el sistema

Haz **doble clic en `iniciar.bat`**. Se abre el navegador en **http://localhost:8000**.
Deja la ventana negra abierta mientras usas el sistema; para detenerlo presiona `Ctrl + C`.

En la pantalla de inicio de sesión aparece **«Acceso rápido para la demostración»**: con un clic entras como cualquier usuario.
Todas las cuentas tienen la contraseña **`udh2026`**.

| Usuario | Correo |
|---|---|
| Administrador | `admin@udh.edu.pe` |
| Estudiante – Ing. Civil (Juan Pérez) | `2021104829@udh.edu.pe` |
| Estudiante – Derecho (Ana Gómez) | `2020201944@udh.edu.pe` |
| Estudiante – Ing. Ambiental (Renzo Huamán) | `2020210319@udh.edu.pe` |
| Estudiante – Contabilidad (Luciana Morales) | `2021102941@udh.edu.pe` |
| Tesorería (jefe) | `tesoreria@udh.edu.pe` |
| Matrícula (jefa, ve todas las ventanillas) | `matricula@udh.edu.pe` |
| Matrícula – Ventanilla 3 (Civil, Sistemas, Arquitectura) | `marcos.vega@udh.edu.pe` |
| Matrícula – Ventanilla 1 (Ambiental, Psicología, MKT, Turismo) | `uzmila.campos@udh.edu.pe` |
| Director de Ing. Civil | `civil@udh.edu.pe` |
| Decanato de Ingeniería | `decanato.ingenieria@udh.edu.pe` |

## 4. Guion sugerido para la presentación (unos 10 minutos)

1. **Administrador → Panel de control**: indicadores, evolución mensual y solicitudes por carrera. Cambia a modo oscuro con el botón de la luna.
2. **Trámites por carrera**: cuántos trámites tiene disponible cada carrera y cuántas solicitudes registró. Exporta a Excel.
3. **Estudiante Juan** → *Nuevo trámite* → elige un trámite → adjunta archivos, escribe la justificación, registra el pago → **Enviar**.
   Juan tiene además un trámite **observado**: muestra cómo lo subsana.
4. **Tesorería** → *Bandeja* → abre la solicitud → **Validar pago y continuar**.
5. **Matrícula, Ventanilla 3** (Marcos Vega) → la solicitud aparece solo en su ventanilla → emite la constancia con número sugerido.
   Si el trámite pasa por Programa o Decanato, entra como `civil@…` o `decanato.ingenieria@…`.
6. **Juan** recibe la notificación y descarga su **constancia con código QR**. Muestra la página **Verificar documento**.
7. **Administrador → Trámites (TUPA)**: edita el flujo de un trámite o crea una **versión propia para una carrera**.
8. **Administrador → Carga de datos**: descarga una plantilla, llénala en Excel e impórtala en vivo.

## 5. Cargar información: seeders y Excel

Todos los datos iniciales están en la carpeta **`database/data/`**. Los `.csv` se abren y se editan con Excel (separador `;`):

| Archivo | Contenido | Seeder |
|---|---|---|
| `carreras.csv` | Facultades y 41 programas: 15 de pregrado, 6 segundas especializaciones, 17 maestrías y 3 doctorados (con nivel, modalidades y los nombres con que aparecen en el reporte) | `CarrerasSeeder` |
| `areas.json` | Tesorería, Matrícula (con sus 5 ventanillas) y otras áreas | `AreaSeeder` |
| `personal.csv` | Administrador, jefes, operadores de ventanilla, decanos y directores | `PersonalSeeder` |
| `estudiantes.csv` | Estudiantes | `EstudiantesSeeder` |
| `procedures.json` | Los 149 trámites del reporte académico, en 7 categorías, con requisitos, costo referencial, flujo y las carreras que los tienen | `ProcedureSeeder` |
| `reporte_tramites.csv` | El reporte original (`destiptra`; `desesc`): vincula cada trámite con sus carreras | `TramitesPorCarreraSeeder` |
| `solicitudes_demo.csv` | **Cuántas solicitudes de prueba generar por carrera** | `SolicitudesDemoSeeder` |

Comandos útiles (en la terminal, dentro del proyecto):

```bash
php artisan migrate:fresh --seed                        # BORRA todo y carga todos los datos de nuevo
php artisan db:seed --class=PersonalSeeder              # vuelve a cargar solo el personal
php artisan db:seed --class=SolicitudesDemoSeeder       # agrega más solicitudes de prueba
php artisan udh:importar personal C:\ruta\personal.csv  # importa cualquier CSV
```

Tipos que acepta `udh:importar`: `carreras`, `personal`, `estudiantes`, `tramites`, `solicitudes_demo`.

**Importar el reporte de trámites del sistema académico:** en Excel guarda el reporte (columnas `destiptra` y `desesc`) como CSV e impórtalo como tipo `tramites`.
Las carreras se reconocen por código, nombre o alias, aunque traigan la modalidad ("PSICOLOGIA - SEMIPRESENCIAL").
Los trámites nuevos se crean con un flujo estándar según su categoría; los que ya existen solo se vinculan a las carreras que les falten.

Las 7 categorías de trámites son: Constancias y Certificados · Matrícula y Régimen de Estudios · Evaluaciones y Notas · Prácticas Pre Profesionales · Investigación y Tesis · Grados y Títulos · Servicios y Extensión.

También puedes hacerlo sin terminal: entra como **Administrador → Carga de datos**, descarga la plantilla, llénala y súbela.
Si un registro ya existe (mismo correo o código) **se actualiza**, no se duplica.
Los usuarios nuevos reciben la contraseña `udh2026`.

**Columnas de `personal.csv`:**
- `rol`: `ADMINISTRADOR`, `TESORERIA`, `MATRICULA`, `PROGRAMA_ACADEMICO`, `FACULTAD` o `AREA_CENTRAL`.
- `area_codigo`: código del área (`TESORERIA`, `MATRICULA`, `BIENESTAR`…).
- `es_jefe`: escribe `SI` para que esa persona sea la responsable del área.
- `ventanilla`: el nombre exacto de la ventanilla que atiende esa persona.

## 6. Para programar y hacer cambios

Ejecuta `composer run dev`. Levanta Laravel y Vite juntos, y los cambios en React se ven al instante en el navegador.

¿Dónde está cada cosa?

```
app/
  Services/WorkflowService.php      ← motor del flujo: a quién le toca, aprobar, observar, rechazar
  Services/ImportService.php        ← importación de CSV/Excel
  Services/DemoDataGenerator.php    ← genera solicitudes de prueba realistas
  Services/DocumentRenderer.php     ← PDF de oficios, resoluciones y constancias
  Http/Controllers/Api/             ← endpoints de la API (/api/...)
  Models/                           ← tablas: Procedure, Submission, Area, Program, User…
database/
  migrations/                       ← estructura de la base de datos
  seeders/  y  data/                ← datos iniciales (editables)
routes/api.php                      ← lista de todas las rutas de la API
resources/js/                       ← FRONTEND REACT
  app.jsx                           ← rutas de páginas
  components/Layout.jsx             ← menú lateral y barra superior
  components/SubmissionDetail.jsx   ← ventana de detalle de una solicitud (aprobar/observar…)
  pages/student/  pages/staff/  pages/admin/
resources/css/app.css               ← colores del tema claro/oscuro (variables --bg, --surface…)
public/img/                         ← escudo y logo
```

Los colores institucionales están en `resources/css/app.css`: verde `#2EB8A0`, dorado `#F5B400`, arena `#F2D68A` y azul marino `#0F2440`.

## 7. Usar MySQL en lugar de SQLite (opcional)

En el archivo `.env` cambia estas líneas:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=udh_tramites
DB_USERNAME=root
DB_PASSWORD=
```

Crea la base `udh_tramites` en MySQL y ejecuta `php artisan migrate:fresh --seed`.

## 8. Problemas frecuentes

- **«could not find driver»**: falta activar la extensión `pdo_sqlite` en `php.ini`. Herd ya la trae activa.
- **No cargan los estilos o la pantalla sale en blanco**: ejecuta `npm run build`. Si usas `composer run dev`, deja esa ventana abierta.
- **Error 419 o la sesión expira**: recarga la página (F5).
- **Archivos grandes no suben**: inicia con `iniciar.bat`, que ya sube el límite a 20 MB.
- **Quiero empezar de cero**: doble clic en `reiniciar-datos.bat`, o *Administrador → Carga de datos → Reiniciar*.
- **Antes de usarlo de verdad (producción)**: en `.env` pon `DEMO_MODE=false`. Así se oculta el acceso rápido y se bloquea el botón de reiniciar datos.
