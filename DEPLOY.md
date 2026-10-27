# Despliegue de hasio

Guía paso a paso para poner hasio (landing page de Semántica, Symfony 7.4) en
producción y para actualizarlo después. El servidor objetivo es Ubuntu 24.04
con Apache + PHP-FPM 8.3, igual que talio y Carbono.

> hasio **no tiene base de datos**. Solo necesita PHP, Apache, el archivo
> `.env` con los secretos y acceso por HTTPS a la API de Carbono.

---

## 0. Antes de empezar (checklist)

- [ ] Carbono ya responde en **su propio subdominio** (hoy el dominio principal
      `semantica.com.co` lo atiende Carbono; hasio lo va a ocupar).
- [ ] Token de API para hasio creado en Carbono:
      `php bin/console app:api-token crear hasio` (en el servidor de Carbono).
- [ ] Claves de reCAPTCHA v3 con el dominio de producción agregado en
      <https://www.google.com/recaptcha/admin> (clave del sitio + clave secreta).
- [ ] Certificado SSL disponible en `/etc/ssl/certs/semantica/semantica2026`.
- [ ] Acceso de lectura del servidor al repositorio de GitHub (deploy key o
      token, ver paso 3).

---

## 1. Paquetes del servidor

Si el servidor ya tiene talio o Carbono, este paso ya está hecho.

```bash
sudo apt update
sudo apt install -y apache2 git unzip \
    php8.3-fpm php8.3-cli php8.3-curl php8.3-xml php8.3-mbstring php8.3-intl
sudo a2enmod proxy_fcgi setenvif rewrite ssl headers macro
sudo a2enconf php8.3-fpm
```

Composer (si no está instalado):

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

Verificar extensiones requeridas (`ctype`, `iconv`, `curl`, `xml`, `mbstring`, `intl`):

```bash
php -m | grep -Ei 'ctype|iconv|curl|xml|mbstring|intl'
```

## 2. Límites de PHP para adjuntos

El formulario de casos de soporte acepta hasta **5 archivos de 10 MB**. Ajustar
`/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 55M
max_file_uploads = 5
```

```bash
sudo systemctl restart php8.3-fpm
```

## 3. Acceso al repositorio (deploy key)

Se recomienda una **deploy key de solo lectura** para el usuario `www-data`,
en lugar de credenciales personales:

```bash
sudo mkdir -p /var/www/.ssh && sudo chown www-data:www-data /var/www/.ssh
sudo -u www-data ssh-keygen -t ed25519 -C "hasio-deploy" -f /var/www/.ssh/id_ed25519 -N ""
sudo cat /var/www/.ssh/id_ed25519.pub
```

Copiar la clave pública en GitHub → repositorio → *Settings → Deploy keys*
(sin permiso de escritura). Probar:

```bash
sudo -u www-data ssh -T git@github.com
```

## 4. Clonar el proyecto

```bash
cd /var/www/html
sudo -u www-data git clone git@github.com:sebas957/hasio.git hasio
cd hasio
```

## 5. Configurar el `.env` de producción

`.env` está en `.gitignore`: **nunca** se sube a GitHub, se crea a mano en el
servidor a partir de la plantilla.

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data nano .env
```

Valores de producción:

| Variable | Valor |
|---|---|
| `APP_ENV` | `prod` |
| `APP_SECRET` | Nuevo, generado con `php -r "echo bin2hex(random_bytes(16));"`. **No reutilizar el de desarrollo.** |
| `APP_SHARE_DIR` | `var/share` |
| `DEFAULT_URI` | `https://semantica.com.co` |
| `BASE_CARBONO` | URL pública de Carbono, **terminada en `/`** (p. ej. `https://<subdominio-carbono>/`) |
| `CARBONO_TOKEN` | Token creado en el checklist (paso 0) |
| `RECAPTCHA_SITE_KEY` | Clave del sitio de reCAPTCHA v3 |
| `RECAPTCHA_SECRET` | Clave secreta de reCAPTCHA v3 |

Proteger el archivo:

```bash
sudo chmod 640 .env
sudo chown www-data:www-data .env
```

Solo se usa `.env`: no crear `.env.local`, `.env.prod` ni `.env.local.php`
(no están en `.gitignore`).

## 6. Dependencias y caché

```bash
sudo -u www-data composer install --no-dev --optimize-autoloader --classmap-authoritative
sudo -u www-data APP_ENV=prod php bin/console cache:clear
sudo -u www-data APP_ENV=prod php bin/console cache:warmup
```

Permisos de `var/` (caché, logs y contadores del límite por IP):

```bash
sudo chown -R www-data:www-data var
```

## 7. Apache

El vhost usa la macro `SitioPHP` de `semantica-sitios.conf` (configuración
común en `despliegue/apache/conf-available` del repositorio de talio). Debe
estar habilitada antes:

```bash
sudo a2enconf semantica-sitios     # si aún no lo está
```

Antes de activar hasio, **desactivar el sitio de Carbono que hoy atiende
`semantica.com.co`** (o moverlo a su subdominio), para que no haya dos
VirtualHost con el mismo `ServerName`.

```bash
sudo cp despliegue/apache/sites-available/hasio.conf /etc/apache2/sites-available/
sudo apache2ctl configtest
sudo a2ensite hasio
sudo systemctl reload apache2
```

El `DocumentRoot` es `public/`: nada fuera de esa carpeta (`.env`, `var/`,
`vendor/`, `config/`) queda expuesto por web.

## 8. Verificación

```bash
# Páginas estáticas
curl -sI https://semantica.com.co/            | head -1   # 200
curl -sI https://semantica.com.co/nosotros    | head -1   # 200
curl -sI https://semantica.com.co/contacto    | head -1   # 200

# El modo debug NO debe estar activo: una ruta inexistente debe dar 404
# con la página de error genérica, sin la traza de Symfony.
curl -s https://semantica.com.co/no-existe | grep -c "Symfony Exception"   # 0

# Archivos sensibles NO accesibles
curl -sI https://semantica.com.co/.env        | head -1   # 404
curl -sI https://semantica.com.co/../.env     | head -1   # 400/404

# Conectividad con Carbono desde el servidor
curl -sI "$(grep ^BASE_CARBONO .env | cut -d= -f2- | tr -d '"')" | head -1
```

Pruebas manuales:

- [ ] Enviar el formulario de **Contacto** y confirmar que llega a Carbono.
- [ ] Abrir un **evento** (`/evento/{id}`) e inscribirse.
- [ ] Crear un **caso de soporte** con un adjunto y consultarlo.
- [ ] Ver en la consola del navegador que reCAPTCHA carga sin errores de dominio.
- [ ] Revisar `var/log/prod.log` y `/var/log/apache2/error.log`.

---

## Actualizaciones (cada despliegue)

Con los cambios ya en `origin/main`:

```bash
sudo /var/www/html/hasio/despliegue/actualizar.sh
```

El script hace `git pull`, `composer install --no-dev` y `cache:clear`.

Si el cambio agregó una variable nueva a `.env.example`, agregarla a mano al
`.env` del servidor **antes** de correr el script.

### Volver a la versión anterior

```bash
cd /var/www/html/hasio
sudo -u www-data git log --oneline -5           # elegir el commit bueno
sudo -u www-data git checkout <commit>
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/console cache:clear
```

Para regresar a la rama después: `sudo -u www-data git checkout main`.

---

## Seguridad de secretos (antes de subir a GitHub)

Archivos que **no** deben versionarse y ya están en `.gitignore`:
`.env`, `/var/`, `/vendor/`, `/.idea/`, `/.phpunit.cache/`,
`/config/secrets/prod/prod.decrypt.private.php`.

Archivos que **sí** se versionan y no tienen secretos reales:

- `.env.example`: plantilla con los valores vacíos.
- `.env.test`: `APP_SECRET` ficticio solo para PHPUnit (es lo estándar en Symfony).

Comprobación rápida antes de cada `git push`:

```bash
# Ningún .env real en el índice
git ls-files | grep -E '(^|/)\.env($|\.local|\.prod)'          # sin salida

# Ningún valor del .env local aparece en los archivos versionados
grep -E '^(APP_SECRET|CARBONO_TOKEN|RECAPTCHA_SECRET)=' .env | cut -d= -f2- | tr -d '"' \
  | while read -r v; do [ -n "$v" ] && git grep -l --cached -F -- "$v"; done   # sin salida
```

Si un secreto llegara a subirse por error: **rotarlo de inmediato** (nuevo
token en Carbono, nuevas claves en reCAPTCHA, nuevo `APP_SECRET`). Borrarlo del
historial no basta, porque GitHub y los clones ya lo tienen.
