# PayRoll

Sistema web de **liquidación de sueldos** hecho con **PHP 8, Bootstrap 5 y MySQL**.
Pensado para empresas argentinas: CUIL, legajo, aportes y contribuciones, SAC, recibo según la Ley 20.744 y archivo de transferencias bancarias.

## Funcionalidades

| Módulo | Qué hace |
|---|---|
| **Tablero** | Indicadores de dotación, neto, costo laboral, evolución de 12 períodos, cumpleaños y aniversarios |
| **Empleados** | Alta, edición, búsqueda, filtros, orden, exportación a CSV. Valida el CUIL y el CBU con su dígito verificador |
| **Organización** | Departamentos y puestos con sueldo básico. **Aumento general** por porcentaje (paritarias) |
| **Conceptos** | Haberes remunerativos y no remunerativos, descuentos y contribuciones patronales, configurables sin tocar código |
| **Liquidaciones** | Mensual y SAC. Novedades (horas extras, inasistencias, bonos, adelantos), cálculo, cierre y reapertura |
| **Recibos** | Recibo imprimible (original y duplicado), con el neto en letras. Impresión masiva y PDF desde el navegador |
| **Exportaciones** | Libro de sueldos (CSV) y archivo de transferencias bancarias |
| **Reportes** | Acumulados anuales por mes, por departamento, por concepto y el top 10 de costo |
| **Seguridad** | Roles (admin / RRHH / consulta), CSRF, bloqueo por intentos fallidos y auditoría de cada acción |

También tiene modo oscuro y diseño adaptable a celulares, y funciona **sin internet**: Bootstrap, los íconos y Chart.js están incluidos en el proyecto.

## Motor de cálculo

Cada concepto tiene un **modo de cálculo**:

| Modo | Fórmula |
|---|---|
| `basico` | Sueldo básico del empleado (o del puesto), proporcional si ingresó o egresó en el mes |
| `fijo` | Importe fijo. Con valor 0 se informa como novedad |
| `porcentaje` | % sobre el básico, el total remunerativo, el no remunerativo o el bruto |
| `cantidad` | Cantidad × valor unitario |
| `horas` | Horas × (básico ÷ divisor) × % (ej.: 150 para horas al 50%) |
| `dias` | Días × (básico ÷ 30) × % (usá -100 para inasistencias) |
| `antiguedad` | % del básico por año de antigüedad |
| `sac` | % de la mejor remuneración del semestre, proporcional a los días trabajados |

Los haberes se evalúan en el **orden** configurado, así un porcentaje "sobre el remunerativo" (por ejemplo, el presentismo) toma lo acumulado hasta ese momento. Después se calculan los descuentos y las contribuciones sobre los totales.

Cada concepto puede tomar su valor de tres lugares, en este orden de prioridad: **la novedad del período** > **el valor propio asignado al empleado** > **el valor del concepto**.

Al liquidar, cada recibo guarda una "foto" de los datos del empleado. Así, un cambio posterior de puesto o de sueldo no altera recibos anteriores.

> Las alícuotas que trae la instalación (jubilación 11%, INSSJP 3%, obra social 3%, contribuciones, etc.) son valores de referencia. Revisalas según tu convenio y la normativa vigente.

## Requisitos

- PHP 8.1 o superior con `pdo_mysql` y `mbstring`
- MySQL 8 o MariaDB 10.5 o superior
- Apache, Nginx o el servidor integrado de PHP. Funciona en XAMPP/Laragon.

## Instalación

```bash
# 1. Configuración
cp config/config.example.php config/config.local.php
#    editá los datos de conexión (o usá las variables DB_HOST, DB_NAME, DB_USER, DB_PASSWORD)

# 2. Crear la base, las tablas y el usuario administrador
php bin/install.php --admin-email=admin@miempresa.com --admin-password=UnaClaveSegura

#    Agregá --demo para cargar 24 empleados y 9 meses de liquidaciones de ejemplo.
#    --force recrea las tablas (borra los datos).

# 3. Levantar el servidor
php -S localhost:8000 -t public
```

Después entrá a <http://localhost:8000>.

En producción, el *document root* del servidor web tiene que apuntar a la carpeta `public/`. Así `config/`, `src/` y `database/` no quedan expuestos.

## Tests

```bash
composer install
composer test
```

Los tests cubren el motor de cálculo (prorrateo, antigüedad, presentismo, horas extras, inasistencias, SAC proporcional, orden de conceptos), las validaciones de CUIL y CBU y la conversión de números a letras.

## Estructura

```
public/            Punto de entrada (index.php) y assets (CSS/JS, librerías incluidas)
src/Core/          Base de datos, sesión, autenticación, CSRF, validación, vistas
src/Controllers/   Un controlador por módulo
src/Services/      PayrollCalculator (cálculo puro), PayrollService, NumberToWords, CSV
src/routes.php     Tabla de rutas con el rol mínimo requerido
views/             Plantillas PHP con Bootstrap 5
database/          schema.sql y seed.sql
bin/install.php    Instalador
tests/             Tests con PHPUnit
```

## Ideas para seguir

- Exportación en el formato de Libro de Sueldos Digital (AFIP/ARCA)
- Vacaciones, licencias y liquidación final
- Ganancias (4ª categoría)
- Envío del recibo por email y firma digital
- Portal del empleado para descargar sus recibos
