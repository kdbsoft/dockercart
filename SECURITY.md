# Security Policy

## Supported Versions

| Version | Supported |
|---|---|
| 1.0.x (current) | ✅ Yes |

Only the latest `1.0.x` release line receives security fixes.

---

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

To report a vulnerability, send an email to:

**security@dockercart.net**

Include in your report:
- A description of the vulnerability and its potential impact
- Steps to reproduce or a proof-of-concept
- Affected version(s)
- Any relevant configuration (e.g., deployment mode)

You will receive an acknowledgement within **48 hours**. We aim to release a fix
or mitigation within **14 days** for critical issues and **30 days** for
lower-severity findings.

We appreciate responsible disclosure and will credit researchers in the release
notes unless you prefer to remain anonymous.

---

## Security Considerations for Self-Hosted Deployments

DockerCart is designed to be self-hosted. You are responsible for securing your
own deployment. The following settings are important:

### Change default credentials

Before exposing the store to the internet, update all default passwords in `.env`:

```dotenv
DB_PASSWORD=<strong-random-password>
MYSQL_ROOT_PASSWORD=<strong-random-password>
MYSQL_PASSWORD=<strong-random-password>
```

Never leave the example credentials (`dockercart_password`, `root_password`) in a
production environment.

### Admin panel

- Change the default admin username and password immediately after installation.
- The admin panel (`/admin`) should be restricted by IP allowlist or a VPN if
  possible.
- Admin panel access log is at `./storage/logs/error.log`.

### Storage directory

The `./storage/` directory contains logs, sessions, uploaded files, and cache. It
is mounted at `/var/www/storage` — **outside the webroot** (`/var/www/html`). It
is not publicly accessible by default. Verify this is not exposed through any
reverse proxy configuration changes.

```bash
# Should return 404 or be blocked:
curl -I http://your-store.com/storage/
```

### Image uploads

Uploaded images are stored in `./upload/image/` which is inside the webroot.
Image execution is disabled by default in the Apache configuration. Do not remove
this restriction.

### MySQL port exposure

By default `docker-compose.yml` binds MySQL port `3306` to `0.0.0.0`. In
production, either remove the port mapping or restrict it to `127.0.0.1:3306:3306`
in `docker-compose.yml` (or run behind an external reverse proxy and keep DB
ports unpublished).

### Keep images updated

```bash
docker compose pull
docker compose up -d
```

Regularly pull updated base images (`php:8.4-apache`, `mariadb:11.8`) to receive
upstream security patches.

### HTTPS

Always use HTTPS in production. See [README.md](README.md) and
[DEPLOYMENT.md](DEPLOYMENT.md) for current SSL and deployment options.

---

## Security Features in DockerCart

- Apache `mod_headers` enabled: `X-Frame-Options`, `X-Content-Type-Options`,
  `X-XSS-Protection` headers set by default
- Storage directory is outside the document root
- PHP `open_basedir` restrictions applied via `php.ini`
- File upload type validation in all DockerCart modules
- SQL queries use PDO prepared statements throughout the codebase
- Session files stored outside the webroot (`./storage/session/`)
- OCMOD-free module architecture — no core file patching
