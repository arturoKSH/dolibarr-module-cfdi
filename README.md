# Dolibarr módulo CFDI

La versión visible en la administración de módulos de Dolibarr se define en
`core/modules/modCfdi.class.php`.

## Versiones y releases

- Cada pull request a `master` debe subir la versión semántica del módulo y agregar una sección `## [x.y.z] - YYYY-MM-DD` en `ChangeLog.md`.
- Al integrar cambios en `master`, GitHub Actions crea el tag `vX.Y.Z` y un release con las notas de esa sección.
