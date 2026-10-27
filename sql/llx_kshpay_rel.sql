--
-- Estructura de tabla para la tabla `llx_kshpay_rel`
--
-- CFDI relacionados de un complemento de pago. El XML del pago (crtPymXml.php) y su PDF
-- los leen por fk_pay_parent.
--

CREATE TABLE `llx_kshpay_rel` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_pay_parent` int(11) NOT NULL,
  `reltype` varchar(2) NOT NULL,
  `uuid` varchar(40) NOT NULL,
  PRIMARY KEY (`rowid`)
);

ALTER TABLE `llx_kshpay_rel`
  ADD KEY `idx_kshpay_rel_fk_pay_parent` (`fk_pay_parent`);
