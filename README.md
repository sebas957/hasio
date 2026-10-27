# hasio

Landing page pública de Semántica (Symfony 7.4, PHP >= 8.2). Antes vivía dentro
de Carbono (`src/Controller/Pagina/`). No tiene base de datos propia: los
formularios se envían a la API de Carbono de servidor a servidor, con un token.

| Página | Ruta | Habla con |
|---|---|---|
| Inicio, Nosotros, Producto ERP, Sectores, Servicios, Políticas de privacidad | `/`, `/nosotros`, `/producto/erp`, `/sector/{sector}`, `/servicio/{servicio}`, `/soporte/politicasprivacidad` | Nadie (contenido estático) |
| Contacto | `/contacto` | `POST api/contacto/nuevo` de Carbono |
| Eventos | `/evento/{id}` | `GET api/evento/{id}` y `POST api/evento/{id}/inscripcion` de Carbono |

Las rutas son las mismas que tenía Carbono, para no romper enlaces ni lo que ya
está indexado. **Log in** y **Soporte** del menú llevan a Carbono (mesa de ayuda),
a la URL de `BASE_CARBONO`.

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
- `src/Controller/ContactoController.php`, `EventoController.php`: formularios.
  Cada envío pasa por tres filtros antes de llegar a Carbono:
  1. **CSRF** sin estado (receta de Symfony 7.4): valida el `Origin` del navegador.
  2. **Límite por IP**: 5 envíos cada 15 minutos (`config/packages/rate_limiter.yaml`).
  3. **reCAPTCHA v3** validado en el servidor (`src/Utilidades/Recaptcha.php`),
     con la acción de cada formulario (`templates/_recaptcha.html.twig`).
- `src/Utilidades/ClienteApi.php`: la misma base de clientes de API de talio
  (tiempo límite, nunca lanza excepción, respuesta `['error', 'status', ...]`).
- `src/Utilidades/Carbono.php`: cliente de Carbono, manda `Authorization: Bearer`.
- `templates/base.html.twig`: layout con menú oscuro; `base_claro.html.twig`
  para las páginas con cabecera clara.
- `public/landingPage/`: CSS, JS e imágenes de la plantilla, copiados de Carbono.

## Despliegue

El servidor se prepara igual que talio (ver su README: paquetes, PHP-FPM y la
configuración común de Apache en `despliegue/apache/conf-available`). Lo propio
de hasio:

```bash
cd /var/www/html
sudo -u www-data git clone <repo> hasio
cd hasio
sudo -u www-data cp .env.example .env   # APP_ENV=prod y valores reales
sudo -u www-data composer install --no-dev --optimize-autoloader
cp despliegue/apache/sites-available/hasio.conf /etc/apache2/sites-available/
a2ensite hasio && systemctl reload apache2
```

En cada despliegue: `despliegue/actualizar.sh` (como root).
