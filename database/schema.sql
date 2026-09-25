-- =====================================================================
--  PayRoll — esquema de base de datos (MySQL 8 / MariaDB 10.5+)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS payslip_items;
DROP TABLE IF EXISTS payslips;
DROP TABLE IF EXISTS novelties;
DROP TABLE IF EXISTS payroll_periods;
DROP TABLE IF EXISTS employee_concepts;
DROP TABLE IF EXISTS concepts;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS category_salaries;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS agreements;
DROP TABLE IF EXISTS positions;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS migrations;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuarios del sistema
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','rrhh','consulta') NOT NULL DEFAULT 'consulta',
    active          TINYINT(1) NOT NULL DEFAULT 1,
    failed_logins   INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configuración clave/valor (datos de la empresa, parámetros de cálculo)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    skey    VARCHAR(64) PRIMARY KEY,
    svalue  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Estructura organizacional
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Puestos: función que cumple el empleado (el básico lo define la categoría)
CREATE TABLE positions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NULL,
    name          VARCHAR(120) NOT NULL,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_positions_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Convenios colectivos, categorías y escalas salariales con vigencia
-- ---------------------------------------------------------------------
CREATE TABLE agreements (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,          -- ej: CCT 130/75
    name        VARCHAR(160) NOT NULL,
    description VARCHAR(255) NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agreement_id INT UNSIGNED NOT NULL,
    code         VARCHAR(20)  NOT NULL,
    name         VARCHAR(120) NOT NULL,
    sort_order   INT NOT NULL DEFAULT 100,
    active       TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_category (agreement_id, code),
    CONSTRAINT fk_categories_agreement FOREIGN KEY (agreement_id) REFERENCES agreements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada fila es el básico de una categoría a partir de una fecha (paritarias).
-- Se usa el valor con la mayor fecha de vigencia <= fin del período liquidado.
CREATE TABLE category_salaries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    valid_from  DATE NOT NULL,
    base_salary DECIMAL(14,2) NOT NULL,
    UNIQUE KEY uq_category_salary (category_id, valid_from),
    CONSTRAINT fk_cs_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Empleados
-- ---------------------------------------------------------------------
CREATE TABLE employees (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_number      VARCHAR(20)  NOT NULL UNIQUE,          -- legajo
    first_name       VARCHAR(100) NOT NULL,
    last_name        VARCHAR(100) NOT NULL,
    cuil             CHAR(11)     NOT NULL UNIQUE,
    birth_date       DATE NULL,
    gender           ENUM('F','M','X') NULL,
    nationality      VARCHAR(60) NULL,
    email            VARCHAR(190) NULL,
    phone            VARCHAR(40)  NULL,
    address          VARCHAR(255) NULL,
    hire_date        DATE NOT NULL,
    termination_date DATE NULL,
    department_id    INT UNSIGNED NULL,
    position_id      INT UNSIGNED NULL,
    category_id      INT UNSIGNED NULL,
    contract_type    ENUM('permanente','plazo_fijo','eventual','pasantia') NOT NULL DEFAULT 'permanente',
    base_salary      DECIMAL(14,2) NULL,                    -- NULL = usa el básico de la categoría
    bank_name        VARCHAR(80) NULL,
    cbu              CHAR(22) NULL,
    status           ENUM('activo','licencia','baja') NOT NULL DEFAULT 'activo',
    notes            TEXT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_position   FOREIGN KEY (position_id)   REFERENCES positions(id)   ON DELETE SET NULL,
    CONSTRAINT fk_employees_category   FOREIGN KEY (category_id)   REFERENCES categories(id)  ON DELETE SET NULL,
    INDEX idx_employees_status (status),
    INDEX idx_employees_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Conceptos de liquidación
--   type:      haber_rem | haber_no_rem | descuento | contribucion (costo patronal)
--   calc_mode: basico      -> sueldo básico del empleado
--              fijo        -> value
--              porcentaje  -> base * value / 100
--              cantidad    -> cantidad * value
--              horas       -> cantidad * (básico / divisor_horas) * value / 100
--              dias        -> cantidad * (básico / 30) * value / 100
--              antiguedad  -> básico * value / 100 * años de antigüedad
--              sac         -> mejor remuneración del semestre * value / 100 * proporción
--              formula     -> expresión en la columna `formula` (ver src/Services/Formula)
--   base (solo porcentaje): basico | remunerativo | no_remunerativo | bruto
--   scope: en qué tipo de liquidación se aplica automáticamente
-- ---------------------------------------------------------------------
CREATE TABLE concepts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(10)  NOT NULL UNIQUE,
    name           VARCHAR(120) NOT NULL,
    type           ENUM('haber_rem','haber_no_rem','descuento','contribucion') NOT NULL,
    calc_mode      ENUM('basico','fijo','porcentaje','cantidad','horas','dias','antiguedad','sac','formula') NOT NULL,
    base           ENUM('basico','remunerativo','no_remunerativo','bruto') NULL,
    value          DECIMAL(14,4) NOT NULL DEFAULT 0,
    formula        TEXT NULL,
    applies_to_all TINYINT(1) NOT NULL DEFAULT 0,
    scope          ENUM('mensual','sac','ambos') NOT NULL DEFAULT 'mensual',
    sort_order     INT NOT NULL DEFAULT 100,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    description    VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conceptos fijos asignados a un empleado (se aplican en cada liquidación)
CREATE TABLE employee_concepts (
    employee_id    INT UNSIGNED NOT NULL,
    concept_id     INT UNSIGNED NOT NULL,
    value_override DECIMAL(14,4) NULL,
    quantity       DECIMAL(10,2) NULL,
    PRIMARY KEY (employee_id, concept_id),
    CONSTRAINT fk_ec_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_ec_concept  FOREIGN KEY (concept_id)  REFERENCES concepts(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Liquidaciones
-- ---------------------------------------------------------------------
CREATE TABLE payroll_periods (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year          SMALLINT UNSIGNED NOT NULL,
    month         TINYINT UNSIGNED NOT NULL,
    type          ENUM('mensual','sac') NOT NULL DEFAULT 'mensual',
    description   VARCHAR(160) NOT NULL,
    payment_date  DATE NULL,
    status        ENUM('borrador','calculada','cerrada') NOT NULL DEFAULT 'borrador',
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    calculated_at DATETIME NULL,
    closed_at     DATETIME NULL,
    UNIQUE KEY uq_period (year, month, type),
    CONSTRAINT fk_periods_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Novedades del período (horas extras, inasistencias, bonos puntuales...)
CREATE TABLE novelties (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id   INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    concept_id  INT UNSIGNED NOT NULL,
    quantity    DECIMAL(10,2) NULL,
    amount      DECIMAL(14,2) NULL,        -- si se informa, reemplaza al cálculo
    note        VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_nov_period   FOREIGN KEY (period_id)   REFERENCES payroll_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_nov_employee FOREIGN KEY (employee_id) REFERENCES employees(id)       ON DELETE CASCADE,
    CONSTRAINT fk_nov_concept  FOREIGN KEY (concept_id)  REFERENCES concepts(id)        ON DELETE CASCADE,
    INDEX idx_nov_period (period_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recibos: guardan una "foto" de los datos del empleado al momento de liquidar
CREATE TABLE payslips (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id         INT UNSIGNED NOT NULL,
    employee_id       INT UNSIGNED NOT NULL,
    file_number       VARCHAR(20)  NOT NULL,
    employee_name     VARCHAR(210) NOT NULL,
    cuil              CHAR(11)     NOT NULL,
    department_name   VARCHAR(120) NULL,
    position_name     VARCHAR(120) NULL,
    category_name     VARCHAR(160) NULL,
    hire_date         DATE NOT NULL,
    base_salary       DECIMAL(14,2) NOT NULL,
    seniority_years   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bank_name         VARCHAR(80) NULL,
    cbu               CHAR(22) NULL,
    gross_rem         DECIMAL(14,2) NOT NULL DEFAULT 0,
    gross_no_rem      DECIMAL(14,2) NOT NULL DEFAULT 0,
    deductions        DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_pay           DECIMAL(14,2) NOT NULL DEFAULT 0,
    employer_contrib  DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payslip (period_id, employee_id),
    CONSTRAINT fk_payslip_period   FOREIGN KEY (period_id)   REFERENCES payroll_periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_payslip_employee FOREIGN KEY (employee_id) REFERENCES employees(id)       ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payslip_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payslip_id  INT UNSIGNED NOT NULL,
    concept_id  INT UNSIGNED NULL,
    code        VARCHAR(10)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    type        ENUM('haber_rem','haber_no_rem','descuento','contribucion') NOT NULL,
    quantity    DECIMAL(10,2) NULL,
    rate        DECIMAL(14,4) NULL,
    amount      DECIMAL(14,2) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 100,
    CONSTRAINT fk_items_payslip FOREIGN KEY (payslip_id) REFERENCES payslips(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_concept FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE SET NULL,
    INDEX idx_items_concept (concept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Migraciones aplicadas (bin/migrate.php)
-- ---------------------------------------------------------------------
CREATE TABLE migrations (
    name       VARCHAR(190) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Auditoría
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(40)  NOT NULL,
    entity     VARCHAR(40)  NOT NULL,
    entity_id  INT UNSIGNED NULL,
    details    VARCHAR(500) NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
