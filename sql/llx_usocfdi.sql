--
-- Estructura de tabla para la tabla `llx_usocfdi`
--

CREATE TABLE `llx_usocfdi` (
  `rowid` int(11) NOT NULL,
  `usocfdi_id` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `Descripcion` varchar(400) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL
);

TRUNCATE TABLE `llx_usocfdi`;

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
