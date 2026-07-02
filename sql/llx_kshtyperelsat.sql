--
-- Estructura de tabla para la tabla `llx_kshtyperelsat`
--

CREATE TABLE `llx_kshtyperelsat` (
  `rowid` int(11) NOT NULL,
  `idsat` varchar(240) NOT NULL,
  `description` varchar(240) NOT NULL
);

ALTER TABLE `llx_kshtyperelsat`
  ADD PRIMARY KEY (`rowid`);

ALTER TABLE `llx_kshtyperelsat`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

TRUNCATE TABLE `llx_kshtyperelsat`;

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
