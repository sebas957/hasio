# hasio

Landing page pública de Semántica (Symfony 7.4, PHP >= 8.2). Antes vivía dentro
de Carbono (`src/Controller/Pagina/`). No tiene base de datos propia: los
formularios se envían a la API de Carbono de servidor a servidor, con un token.

| Página | Ruta | Habla con |
|---|---|---|
| Inicio, Nosotros, Producto ERP, Sectores, Servicios, Políticas de privacidad | `/`, `/nosotros`, `/producto/erp`, `/sector/{sector}`, `/servicio/{servicio}`, `/soporte/politicasprivacidad` | Nadie (contenido estático) |
| Contacto | `/contacto` | `POST api/contacto/nuevo` de Carbono |
| Eventos | `/evento/{id}` | `GET api/evento/{id}` y `POST api/evento/{id}/inscripcion` de Carbono |
| Soporte: consultar el estado de un caso | `/soporte` | `GET api/soporte/caso/{id}?correo=...` de Carbono |
| Soporte: enviar un caso | `/soporte/nuevo/{tipo}` (`SOP`, `ERR`, `CES`, `DES`, `SOL`, `SER`) | `GET api/soporte/modulo/lista` y `POST api/soporte/caso/nuevo` de Carbono |
| Soporte: caso creado, descargas | `/soporte/caso/{id}`, `/soporte/descarga` | Nadie (contenido estático) |

Las rutas son las mismas que tenía Carbono, para no romper enlaces ni lo que ya
está indexado. Carbono ya no se enlaza desde el sitio: solo se usa su API, en la
URL de `BASE_CARBONO`.

### API de casos de soporte

Los dos métodos están en `src/Utilidades/Carbono.php`. Como el resto de la API,
llevan `Authorization: Bearer <CARBONO_TOKEN>`.

**Enviar un caso**: `POST api/soporte/caso/nuevo`, en `multipart/form-data`.

- Campos: `tipo`, `contacto`, `correo`, `telefono`, `cliente`, `modulo` y
  `descripcion` (máximo 5000 caracteres).
- Adjuntos: van en `files[]`, máximo 5 archivos de 10 MB cada uno
  (`src/Form/CasoSoporteType.php`).
- El `modulo` sale de `GET api/soporte/modulo/lista`, que devuelve
  `datos.modulos` (`id` y `nombre`). Si esa lista falla, la página no muestra
  el formulario y ofrece los otros canales de contacto.
- Respuesta: `datos.id` es el número del caso y `datos.adjuntosFallidos`, cuántos
  adjuntos no se guardaron. Si alguno falló, se avisa al usuario y se redirige a
  `/soporte/caso/{id}`.
- Filtros antes de llamar a Carbono: límite por IP (`formulario_publico`) y
  reCAPTCHA v3 con la acción `soporte`.

**Consultar el estado de un caso**: `GET api/soporte/caso/{id}?correo=<correo>`.

- Devuelve los datos del caso con sus respuestas.
- Responde `404` si el número y el correo no coinciden. Así nadie puede ver un
  caso sin conocer el correo con que se creó.
- Filtro: límite por IP `consulta_caso`, de 20 consultas cada 15 minutos, para
  frenar a quien intente adivinar números de caso. No lleva reCAPTCHA.

## Requisitos

- PHP **8.2 o superior** (producción usa el 8.3 de Ubuntu 24.04). Composer está
  fijado a la plataforma PHP 8.3.0 (`config.platform.php`).
- Extensiones: `ctype`, `iconv`, `curl`, `xml`, `mbstring`, `intl`.

## Puesta en marcha (desarrollo)

```bash
composer install
cp .env.example .env   # y rellenar los valores
php -S localhost:8000 -t public
```

El token de Carbono se crea en Carbono: `php bin/console app:api-token crear hasio`.

## Pruebas

```bash
php bin/phpunit
```

Son pruebas unitarias de `src/Utilidades/` (cliente de Carbono y reCAPTCHA).
Usan `MockHttpClient`, así que no tocan ningún servicio real.

## Arquitectura

- `src/Controller/PaginaController.php`: páginas de contenido.
- `src/Controller/ContactoController.php`, `EventoController.php` y
  `SoporteController.php`: formularios. Cada envío (contacto, inscripción y
  caso nuevo) pasa por tres filtros antes de llegar a Carbono:
  1. **CSRF** sin estado (receta de Symfony 7.4): valida el `Origin` del navegador.
  2. **Límite por IP**: 5 envíos cada 15 minutos (`config/packages/rate_limiter.yaml`).
  3. **reCAPTCHA v3** validado en el servidor (`src/Utilidades/Recaptcha.php`),
     con la acción de cada formulario (`templates/_recaptcha.html.twig`).

  La consulta del estado de un caso solo pasa por CSRF y por su propio límite
  por IP (`consulta_caso`).
- `src/Utilidades/ClienteApi.php`: la misma base de clientes de API de talio
  (tiempo límite, nunca lanza excepción, respuesta `['error', 'status', ...]`).
- `src/Utilidades/Carbono.php`: cliente de Carbono, manda `Authorization: Bearer`.
- `templates/base.html.twig`: layout con menú oscuro; `base_claro.html.twig`
  para las páginas con cabecera clara.
- `public/landingPage/`: CSS, JS e imágenes de la plantilla, copiados de Carbono.

## Despliegue

Los pasos completos están en [DEPLOY.md](DEPLOY.md): preparación del servidor,
`.env` de producción, Apache, verificación, actualizaciones, cómo volver a una
versión anterior y la revisión de secretos antes de subir a GitHub.

En cada despliegue: `despliegue/actualizar.sh` (como root).
