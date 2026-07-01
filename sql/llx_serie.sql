--
-- Estructura de tabla para la tabla `llx_serie`
--

CREATE TABLE `llx_serie` (
  `rowid` int(11) NOT NULL,
  `serie` varchar(16) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `entity` int(11) NOT NULL DEFAULT 1
);

ALTER TABLE `llx_serie`
  ADD PRIMARY KEY (`rowid`);

ALTER TABLE `llx_serie`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

TRUNCATE TABLE `llx_serie`;

INSERT INTO `llx_serie` (`rowid`, `serie`, `status`, `entity`) VALUES
(10, 'C', 1, 1),
(11, 'A', 1, 1),
(12, 'B', 1, 1),
(13, 'D', 1, 1),
(14, 'E', 1, 1);
