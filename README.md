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
* **Extensiones de PHP del módulo:** `openssl`, `mbstring`, `dom`, `xsl`, `soap` y `bcmath`. Además, las que pide Dolibarr.
* **Dolibarr ERP/CRM:** Probado con la versión 22.0. Las versiones anteriores no están validadas.

### Dependencias adicionales

Las extensiones `bcmath`, `xsl` y `soap` no vienen en la imagen base de PHP.
Sin `xsl` no se genera la cadena original y sin `soap` no se puede timbrar ni
cancelar. Verifica que estén cargadas:

```bash
docker compose exec dolibarr php -m | grep -E '^(bcmath|xsl|soap)$'
```

Si falta alguna, agrégala al `docker/Dockerfile` del repositorio padre
(`xsl` necesita el paquete `libxslt1-dev`) y reconstruye la imagen.

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

## Extrafields

El módulo crea al activarse los extrafields fiscales que usa, por ejemplo el
uso de CFDI, el régimen fiscal y el receptor alterno en terceros, y la serie y
el método de pago en facturas. La lista completa está en `modCfdi::init()`.
Después de actualizar el módulo, desactívalo y vuelve a activarlo.

Si un extrafield ya existe, Dolibarr no cambia su definición. Si se creó antes
a mano o con otra versión del módulo, revísalo en los atributos adicionales de
los módulos **Terceros** y **Facturas**. La pestaña **Test** del módulo CFDI
muestra qué extrafields de facturas existen en la base de datos.

---

Desarrollado por [Kristal Software House](https://github.com/arturoKSH/dolibarr_zakili).
