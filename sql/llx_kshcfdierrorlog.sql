--
-- Estructura de tabla para la tabla `llx_kshcfdierrorlog`
--
-- Registro de intentos fallidos de timbrado/cancelacion CFDI, para poder
-- diagnosticar problemas con el PAC sin depender de que el cliente mande
-- capturas de pantalla.
--

CREATE TABLE `llx_kshcfdierrorlog` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `entity` int(11) NOT NULL DEFAULT 1,
  `datec` datetime NOT NULL,
  `action` varchar(20) NOT NULL,
  `invoice_ref` varchar(64) DEFAULT NULL,
  `fk_facture` int(11) DEFAULT NULL,
  `fk_user` int(11) DEFAULT NULL,
  `errmsg` text,
  PRIMARY KEY (`rowid`)
);

ALTER TABLE `llx_kshcfdierrorlog`
  ADD KEY `idx_kshcfdierrorlog_datec` (`datec`),
  ADD KEY `idx_kshcfdierrorlog_fk_facture` (`fk_facture`);
