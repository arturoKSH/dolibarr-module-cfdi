--
-- Estructura de tabla para la tabla `llx_FiscalRegimen`
--

CREATE TABLE `llx_FiscalRegimen` (
  `rowid` int(11) NOT NULL,
  `fiscalreg` varchar(10) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL
);

--
-- Truncar tablas antes de insertar `llx_FiscalRegimen`
--

TRUNCATE TABLE `llx_FiscalRegimen`;
--
-- Volcado de datos para la tabla `llx_FiscalRegimen`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `llx_kshtyperelsat`
--

CREATE TABLE `llx_kshtyperelsat` (
  `rowid` int(11) NOT NULL,
  `idsat` varchar(240) NOT NULL,
  `description` varchar(240) NOT NULL
);

--
-- Truncar tablas antes de insertar `llx_kshtyperelsat`
--

TRUNCATE TABLE `llx_kshtyperelsat`;
--
-- Volcado de datos para la tabla `llx_kshtyperelsat`
--

INSERT INTO `llx_kshtyperelsat` (`rowid`, `idsat`, `description`) VALUES
(1, '01', '01 Notas de crédito de documentos relacionados'),
(2, '02', '02 Notas de débito de los documentos relacionados'),
(3, '03', '03 Devolución de mercancías sobre facturas o traslados previos'),
(4, '04', '04 Sustitución de los CFDI previos'),
(5, '05', '05 Traslados de mercancías facturados previamente'),
(6, '06', '06 Factura generada por los traslados previos'),
(7, '07', '07 CFDI por aplicación de anticipo'),
(8, '08', '08 Facturas generadas por pagos en parcialidades'),
(9, '09', '09 Factura generada por pagos diferidos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `llx_serie`
--

CREATE TABLE `llx_serie` (
  `rowid` int(11) NOT NULL,
  `serie` varchar(16) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL
);

--
-- Truncar tablas antes de insertar `llx_serie`
--

TRUNCATE TABLE `llx_serie`;
--
-- Volcado de datos para la tabla `llx_serie`
--

INSERT INTO `llx_serie` (`rowid`, `serie`, `status`) VALUES
(10, 'C', 1),
(11, 'A', 1),
(12, 'B', 1),
(13, 'D', 1),
(14, 'E', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `llx_usocfdi`
--

CREATE TABLE `llx_usocfdi` (
  `rowid` int(11) NOT NULL,
  `usocfdi_id` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `Descripcion` varchar(400) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL
);

--
-- Truncar tablas antes de insertar `llx_usocfdi`
--

TRUNCATE TABLE `llx_usocfdi`;
--
-- Volcado de datos para la tabla `llx_usocfdi`
--

INSERT INTO `llx_usocfdi` (`rowid`, `usocfdi_id`, `Descripcion`) VALUES
(23, 'G01', 'Adquisición de mercancias'),
(24, 'G02', 'Devoluciones descuentos o bonificaciones'),
(25, 'G03', 'Gastos en general'),
(26, 'I01', 'Construcciones'),
(27, 'I02', 'Mobilario y equipo de oficina por inversiones'),
(28, 'I03', 'Equipo de transporte'),
(29, 'I04', 'Equipo de computo y accesorios'),
(30, 'I05', 'Dados troqueles moldes matrices y herramental'),
(31, 'I06', 'Comunicaciones telefónicas'),
(32, 'I07', 'Comunicaciones satelitales'),
(33, 'I08', 'Otra maquinaria y equipo'),
(34, 'D01', 'Honorarios médicos dentales y gastos hospitalarios'),
(35, 'D02', 'Gastos médicos por incapacidad o discapacidad'),
(36, 'D03', 'Gastos funerales'),
(37, 'D04', 'Donativos'),
(38, 'D05', 'Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación)'),
(39, 'D06', 'Aportaciones voluntarias al SAR'),
(40, 'D07', 'Primas por seguros de gastos médicos'),
(41, 'D08', 'Gastos de transportación escolar obligatoria'),
(42, 'D09', 'Depósitos en cuentas para el ahorro primas que tengan como base planes de pensiones'),
(43, 'D10', 'Pagos por servicios educativos (colegiaturas)'),
(44, 'P01', 'Por definir'),
(45, 'S01', 'Sin efectos fiscales'),
(46, 'CP01', 'Pagos'),
(47, 'CN01', 'Nómina');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `llx_FiscalRegimen`
--
ALTER TABLE `llx_FiscalRegimen`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `rowid_UNIQUE` (`rowid`);

--
-- Indices de la tabla `llx_kshtyperelsat`
--
ALTER TABLE `llx_kshtyperelsat`
  ADD PRIMARY KEY (`rowid`);

--
-- Indices de la tabla `llx_serie`
--
ALTER TABLE `llx_serie`
  ADD PRIMARY KEY (`rowid`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `llx_FiscalRegimen`
--
ALTER TABLE `llx_FiscalRegimen`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `llx_kshtyperelsat`
--
ALTER TABLE `llx_kshtyperelsat`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `llx_serie`
--
ALTER TABLE `llx_serie`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
