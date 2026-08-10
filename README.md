# Módulo CFDI para Dolibarr

Este módulo permite la integración de la facturación electrónica **CFDI (Comprobante Fiscal Digital por Internet)** de México en Dolibarr ERP/CRM. Desarrollado por **Kristal Software House**, facilita el timbrado, cancelación y administración de los comprobantes fiscales conforme a las regulaciones vigentes del SAT.

## Características Principales

* **Administración de Certificados (CSD):** Carga y gestión segura de los certificados digitales (DER, PEM, llave PKCS8 y llave PEM).
* **Seguridad Avanzada:** Almacenamiento cifrado de contraseñas de certificados en la base de datos y validaciones de identidad en operaciones críticas.
* **Historial de Certificados:** Registro de auditoría completo para rastrear qué usuarios reemplazaron o modificaron los certificados digitales.
* **Conectividad con PACs:** Soporte para timbrado de pruebas y productivo a través del servicio integrado.
* **Catálogos SAT:** Tablas precargadas con códigos oficiales de Uso de CFDI, Regímenes Fiscales, Cancelaciones, Relaciones y Series.
* **Consola de Diagnóstico:** Pestaña de pruebas de conectividad y estado de salud del certificado digital para detectar problemas de forma proactiva.

## Requisitos del Sistema

* **PHP:** Versión 7.2 o superior.
* **Extensiones de PHP:** `openssl`, `bcmath`, `mysqli`, `pdo`, `pdo_mysql`, `gd`, `intl`, `xml`, `xsl`, `soap`, `mbstring`, `zip`, `imap` y `calendar`.
* **Dolibarr ERP/CRM:** Versión 11.0 o superior.

### Dependencias adicionales

Cuando el módulo se ejecuta con el entorno Docker del repositorio padre, el
`docker/Dockerfile` instala las extensiones `bcmath`, `xsl` y `soap`. Si se
ejecuta Dolibarr fuera de ese entorno, deben habilitarse manualmente.

El cancelador SAT requiere las dependencias Composer declaradas en
`elcInv/CancelSat/composer/composer.json`. Desde la raíz de este subrepo,
instálalas con:

```bash
cd elcInv/CancelSat/composer
composer install --no-dev --no-interaction --prefer-dist
```

Esto instala `phpcfdi/xml-cancelacion` y sus dependencias en `vendor/`. La
carpeta `vendor/` no se versiona, por lo que debe instalarse en cada entorno
que ejecute cancelaciones CFDI.

## Guía de Configuración

1. **Activación del Módulo:**
   * Dirígete a **Configuración > Módulos** en Dolibarr.
   * Busca el módulo **CFDI** en la sección de "Otros" y actívalo.

2. **Carga del Certificado de Sello Digital (CSD):**
   * Ve a **CFDI > Configuración**.
   * Sube los 4 archivos requeridos para el sellado digital:
     1. **Certificado DER (.cer)**
     2. **Certificado PEM (.cer.pem)**
     3. **Llave PKCS8 (.key)**
     4. **Llave PEM (.key.pem)**
   * Proporciona la **Contraseña del certificado**.
   * Haz clic en **Guardar** e introduce tu contraseña de acceso a Dolibarr para autorizar y almacenar el certificado.

   Los cuatro archivos deben cargarse manualmente; el módulo no genera automáticamente los archivos PEM a partir del `.cer`, `.key` y la contraseña.

3. **Verificación de Conectividad:**
   * Accede a la pestaña **Test** dentro de la configuración del módulo.
   * Verifica que la conectividad con el PAC sea exitosa (Accesible) y que el estado del certificado digital aparezca como **Vigente**.

## Nota de desarrollo

La rama correcta para consultar el trabajo histórico de extrafields es `feature/AddExtrafields`.

---

Desarrollado por [Kristal Software House](https://github.com/arturoKSH/dolibarr_zakili).
