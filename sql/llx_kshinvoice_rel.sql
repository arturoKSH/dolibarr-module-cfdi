--
-- Estructura de tabla para la tabla `llx_kshinvoice_rel`
--
-- CFDI relacionados capturados a mano en una factura (elcInv/compta/facsrel.php).
-- El XML los manda en cfdi:CfdiRelacionados con su TipoRelacion.
--

CREATE TABLE `llx_kshinvoice_rel` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_facture_parent` int(11) NOT NULL,
  `reltype` varchar(2) NOT NULL,
  `uuid` varchar(40) NOT NULL,
  PRIMARY KEY (`rowid`)
);

ALTER TABLE `llx_kshinvoice_rel`
  ADD KEY `idx_kshinvoice_rel_fk_facture_parent` (`fk_facture_parent`);
