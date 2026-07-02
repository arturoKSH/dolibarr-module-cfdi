--
-- Estructura de tabla para la tabla `llx_FiscalRegimen`
--

CREATE TABLE `llx_FiscalRegimen` (
  `rowid` int(11) NOT NULL,
  `fiscalreg` varchar(10) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL
);

ALTER TABLE `llx_FiscalRegimen`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `rowid_UNIQUE` (`rowid`);

ALTER TABLE `llx_FiscalRegimen`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

TRUNCATE TABLE `llx_FiscalRegimen`;

INSERT INTO `llx_FiscalRegimen` (`rowid`, `fiscalreg`, `description`) VALUES
(1, '601', 'General de Ley Personas Morales'),
(2, '603', 'Personas Morales con Fines no Lucrativos'),
(3, '605', 'Sueldos y Salarios e Ingresos Asimilados a Salarios'),
(4, '606', 'Arrendamiento'),
(5, '607', 'Régimen de Enajenación o Adquisición de Bienes'),
(6, '608', 'Demás ingresos'),
(7, '610', 'Residentes en el Extranjero sin Establecimiento Permanente en México'),
(8, '611', 'Ingresos por Dividendos (socios y accionistas)'),
(9, '612', 'Personas Físicas con Actividades Empresariales y Profesionales'),
(10, '614', 'Ingresos por intereses'),
(11, '615', 'Régimen de los ingresos por obtención de premios'),
(12, '616', 'Sin obligaciones fiscales'),
(13, '620', 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos'),
(14, '621', 'Incorporación Fiscal'),
(15, '622', 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras'),
(16, '623', 'Opcional para Grupos de Sociedades'),
(17, '624', 'Coordinados'),
(18, '625', 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas'),
(19, '626', 'Régimen Simplificado de Confianza');
