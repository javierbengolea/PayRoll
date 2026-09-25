-- =====================================================================
--  PayRoll — datos base (configuración y conceptos estándar)
--  Las alícuotas son valores de referencia: ajustalas desde
--  "Conceptos" según tu convenio y la normativa vigente.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO settings (skey, svalue) VALUES
('company_name',     'Mi Empresa S.A.'),
('company_cuit',     '30712345671'),
('company_address',  'Av. Siempre Viva 742, Córdoba'),
('company_activity', 'Servicios empresariales'),
('payment_place',    'Córdoba'),
('hours_divisor',    '200'),
('currency_symbol',  '$');

INSERT INTO concepts (code, name, type, calc_mode, base, value, applies_to_all, scope, sort_order, description) VALUES
('100', 'Sueldo básico',                  'haber_rem',    'basico',     NULL,           0,      1, 'mensual', 10,  'Básico de la escala de la categoría del empleado (o su básico propio).'),
('110', 'Antigüedad',                     'haber_rem',    'antiguedad', NULL,           1,      1, 'mensual', 20,  '1% del básico por cada año de antigüedad.'),
('140', 'Inasistencias injustificadas',   'haber_rem',    'dias',       NULL,        -100,      0, 'mensual', 25,  'Informar los días como novedad. Descuenta básico/30 por día.'),
('120', 'Presentismo',                    'haber_rem',    'formula',    NULL,           8.33,   1, 'mensual', 30,  'VALOR % sobre los haberes remunerativos anteriores. Se pierde con inasistencias injustificadas.'),
('130', 'Horas extras 50%',               'haber_rem',    'horas',      NULL,         150,      0, 'mensual', 40,  'Informar la cantidad de horas como novedad.'),
('131', 'Horas extras 100%',              'haber_rem',    'horas',      NULL,         200,      0, 'mensual', 41,  'Informar la cantidad de horas como novedad.'),
('160', 'Adicional por título',           'haber_rem',    'formula',    NULL,          10,      0, 'mensual', 35,  'VALOR % del básico. Asignalo a quienes tengan título (el % se puede cambiar por empleado).'),
('150', 'Bono por desempeño',             'haber_rem',    'fijo',       NULL,           0,      0, 'mensual', 50,  'Informar el importe como novedad.'),
('200', 'SAC (aguinaldo)',                'haber_rem',    'sac',        NULL,          50,      1, 'sac',     60,  '50% de la mejor remuneración mensual del semestre, proporcional a los días trabajados.'),
('300', 'Suma no remunerativa',           'haber_no_rem', 'fijo',       NULL,           0,      0, 'mensual', 70,  'Acuerdos paritarios no remunerativos.'),
('310', 'Viáticos',                       'haber_no_rem', 'fijo',       NULL,           0,      0, 'mensual', 75,  NULL),
('500', 'Jubilación',                     'descuento',    'porcentaje', 'remunerativo', 11,     1, 'ambos',   500, 'Aporte SIPA.'),
('510', 'Ley 19.032 (INSSJP)',            'descuento',    'porcentaje', 'remunerativo', 3,      1, 'ambos',   510, NULL),
('520', 'Obra social',                    'descuento',    'porcentaje', 'remunerativo', 3,      1, 'ambos',   520, NULL),
('530', 'Cuota sindical',                 'descuento',    'porcentaje', 'remunerativo', 2,      0, 'ambos',   530, 'Asignar a los empleados afiliados.'),
('540', 'Adelanto de sueldo',             'descuento',    'fijo',       NULL,           0,      0, 'mensual', 540, 'Informar el importe como novedad.'),
('800', 'Contribución SIPA',              'contribucion', 'porcentaje', 'remunerativo', 10.77,  1, 'ambos',   800, 'Costo patronal (no se descuenta al empleado).'),
('810', 'Contribución INSSJP',            'contribucion', 'porcentaje', 'remunerativo', 1.59,   1, 'ambos',   810, NULL),
('820', 'Fondo Nacional de Empleo',       'contribucion', 'porcentaje', 'remunerativo', 0.94,   1, 'ambos',   820, NULL),
('830', 'Contribución obra social',       'contribucion', 'porcentaje', 'remunerativo', 6,      1, 'ambos',   830, NULL),
('840', 'ART',                            'contribucion', 'porcentaje', 'remunerativo', 2.5,    1, 'ambos',   840, 'Alícuota de tu aseguradora de riesgos del trabajo.');

UPDATE concepts SET formula = 'SI(C("140") = 0; REMUNERATIVO * VALOR / 100; 0)' WHERE code = '120';
UPDATE concepts SET formula = 'BASICO * VALOR / 100' WHERE code = '160';
