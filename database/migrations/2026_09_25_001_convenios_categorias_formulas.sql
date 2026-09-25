-- =====================================================================
--  Convenios, categorías con escalas salariales y fórmulas en conceptos.
--  Migra el básico que tenían los puestos a un convenio "GENERAL":
--  una categoría por puesto, con vigencia desde 2000-01-01.
-- =====================================================================

CREATE TABLE agreements (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,
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

CREATE TABLE category_salaries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    valid_from  DATE NOT NULL,
    base_salary DECIMAL(14,2) NOT NULL,
    UNIQUE KEY uq_category_salary (category_id, valid_from),
    CONSTRAINT fk_cs_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employees
    ADD COLUMN category_id INT UNSIGNED NULL AFTER position_id,
    ADD CONSTRAINT fk_employees_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

ALTER TABLE concepts
    MODIFY calc_mode ENUM('basico','fijo','porcentaje','cantidad','horas','dias','antiguedad','sac','formula') NOT NULL,
    ADD COLUMN formula TEXT NULL AFTER value;

ALTER TABLE payslips
    ADD COLUMN category_name VARCHAR(160) NULL AFTER position_name;

-- Pasar los básicos de los puestos a categorías
INSERT INTO agreements (code, name, description)
SELECT 'GENERAL', 'Sin convenio', 'Creado al migrar los básicos que tenían los puestos'
  FROM DUAL WHERE EXISTS (SELECT 1 FROM positions);

INSERT INTO categories (agreement_id, code, name, sort_order)
SELECT a.id, CAST(p.id AS CHAR) COLLATE utf8mb4_unicode_ci, p.name, p.id
  FROM positions p JOIN agreements a ON a.code = 'GENERAL';

INSERT INTO category_salaries (category_id, valid_from, base_salary)
SELECT c.id, '2000-01-01', p.base_salary
  FROM positions p
  JOIN agreements a ON a.code = 'GENERAL'
  JOIN categories c ON c.agreement_id = a.id AND c.code = CAST(p.id AS CHAR) COLLATE utf8mb4_unicode_ci;

UPDATE employees e
  JOIN agreements a ON a.code = 'GENERAL'
  JOIN categories c ON c.agreement_id = a.id AND c.code = CAST(e.position_id AS CHAR) COLLATE utf8mb4_unicode_ci
   SET e.category_id = c.id;

ALTER TABLE positions DROP COLUMN base_salary;
