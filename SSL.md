# SSL / HTTPS Guide

This document is the single source of truth for running DockerCart over HTTP or HTTPS.

You do **not** need to edit `docker-compose.yml` manually to switch modes. Use the provided `make` targets or `start.sh` options instead.

## At a glance

| Goal | Command | Public URL | Traefik | Certificate |
| --- | --- | --- | --- | --- |
| Local dev over HTTP | `make up` | `http://dockercart.local` | Yes | None |
| Simplest local run without Traefik | `make standalone` | `http://localhost:${DOCKERCART_HTTP_PORT:-80}` | No | None |
| Local HTTPS testing | `make ssl` | `https://dockercart.local` | Yes | Self-signed |
| Production HTTPS with Traefik | `make letsencrypt` | `https://your-domain.tld` | Yes | Let's Encrypt |
| Production HTTPS without Traefik | `make standalone-letsencrypt` | `https://your-domain.tld` | No | Let's Encrypt |

If you prefer the shortest path for local validation, use `make standalone`.

## Before you start

- Run `make help` to see the available startup modes.
- If `.env` does not exist, startup scripts create it from `.env.example` automatically.
- For Let's Encrypt modes, replace placeholder values in `.env` before starting:
  - `SSL_DOMAIN` must be your real domain
  - `SSL_EMAIL` must be a valid email address
- For public HTTPS, DNS must already point to the host and ports `80` and `443` must be reachable from the internet.

## Recommended startup modes

### 1. HTTP through Traefik

Use this when you want the default Traefik-based stack without SSL.

```bash
make up
# or
./start.sh
```

What you get:

- URL: `http://dockercart.local`
- Traefik-enabled stack
- No certificate generation
- Fast default mode for general local development

### 2. HTTP without Traefik

Use this when you want the simplest local runtime and direct port binding.

```bash
make standalone
```

What you get:

- URL: `http://localhost:${DOCKERCART_HTTP_PORT:-80}`
- No Traefik dependency
- Nginx and Apache run via `docker-compose.standalone.yml`
- Best choice for quick local validation and debugging

### 3. HTTPS with a self-signed certificate

Use this for local HTTPS testing when browser warnings are acceptable.

```bash
make ssl
# or
./start.sh --ssl
```

What you get:

- URL: `https://dockercart.local`
- Browser warning is expected
- Certificate is generated automatically on first run
- Certificate files are stored in:
  - `docker/ssl/certs/dockercart.crt`
  - `docker/ssl/private/dockercart.key`

Notes:

- The certificate Common Name uses `DOCKERCART_DOMAIN` from `.env` when set.
- Existing self-signed files are reused on subsequent runs.
- This mode is for local testing only, not production.

### 4. HTTPS with Let's Encrypt through Traefik

Use this for production deployments where Traefik should terminate TLS.

Set these values in `.env` first:

```env
DOCKERCART_DOMAIN=your-domain.tld
SSL_DOMAIN=your-domain.tld
SSL_EMAIL=admin@your-domain.tld
```

Then start:

```bash
make letsencrypt
# or
./start.sh --letsencrypt
```

What you get:

- URL: `https://your-domain.tld`
- Traefik serves HTTPS using the `letsencrypt` certificate resolver
- Certificates are stored in Traefik ACME storage at `/etc/traefik/acme/acme.json` inside the Traefik container
- Renewal is handled automatically by Traefik

Requirements:

- `DOCKERCART_DOMAIN` and `SSL_DOMAIN` should match the public domain you are serving
- DNS must resolve before the stack starts
- Ports `80` and `443` must be publicly reachable

### 5. HTTPS with Let's Encrypt in standalone mode

Use this when you want real HTTPS without Traefik.

Set these values in `.env` first:

```env
DOCKERCART_DOMAIN=your-domain.tld
DOCKERCART_URL=https://your-domain.tld
DOCKERCART_HTTPS_URL=https://your-domain.tld
DOCKERCART_SSL_ENABLED=true

SSL_DOMAIN=your-domain.tld
SSL_EMAIL=admin@your-domain.tld

DOCKERCART_HTTP_PORT=80
DOCKERCART_HTTPS_PORT=443
```

Then start:

```bash
make standalone-letsencrypt
# or
STANDALONE=1 make letsencrypt
```

What you get:

- URL: `https://your-domain.tld`
- Nginx handles HTTP and HTTPS directly
- `certbot` obtains the initial certificate using the webroot challenge
- A renewal loop runs every 12 hours in the `certbot` container
- Nginx reloads after successful renewal

Certificates and ACME webroot data are stored under:

- `docker/letsencrypt/`
- `docker/letsencrypt/www/`

## Switching between modes

Switching modes is simply a stop-and-start operation:

```bash
make down
make ssl
```

Common examples:

- From HTTP to self-signed HTTPS: `make down` then `make ssl`
- From Traefik Let's Encrypt to standalone Let's Encrypt: `make down` then `make standalone-letsencrypt`
- Back to plain HTTP: `make down` then `make up` or `make standalone`

## How DockerCart selects the right configuration

### Traefik-based startup

`start.sh` always starts from the base file:

- `docker-compose.yml`

Then it appends one mode-specific override:

- `docker-compose.no-ssl.yml` for `./start.sh` or `make up`
- `docker-compose.ssl.yml` for `./start.sh --ssl` or `make ssl`
- `docker-compose.letsencrypt.yml` for `./start.sh --letsencrypt` or `make letsencrypt`

If `MARIADB_EXTERNAL_PORT` is set, `docker-compose.mariadb-port.yml` is also appended.

### Standalone startup

Standalone HTTP uses:

- `docker-compose.standalone.yml`

Standalone Let's Encrypt uses:

- `docker-compose.standalone.yml`
- `docker-compose.standalone.letsencrypt.yml`

In standalone Let's Encrypt mode, the `Makefile` first boots the HTTP stack, runs `certbot certonly`, then brings the HTTPS overlay up and reloads Nginx.

## Environment variables reference

| Variable | Used in | Required | Purpose |
| --- | --- | --- | --- |
| `DOCKERCART_DOMAIN` | Traefik HTTP/HTTPS, self-signed | Recommended | Hostname for routing and certificate CN |
| `SSL_DOMAIN` | Let's Encrypt modes | Yes | Public domain used for certificate issuance |
| `SSL_EMAIL` | Let's Encrypt modes | Yes | Email for ACME registration and expiry notices |
| `DOCKERCART_URL` | App runtime | Standalone HTTPS: Yes | Base store URL used by OpenCart |
| `DOCKERCART_HTTPS_URL` | App runtime | Standalone HTTPS: Yes | HTTPS store URL used by OpenCart |
| `DOCKERCART_SSL_ENABLED` | App runtime | Standalone HTTPS: Yes | Enables SSL-aware application behavior |
| `DOCKERCART_HTTP_PORT` | Standalone modes | Optional | Host port for HTTP in standalone mode |
| `DOCKERCART_HTTPS_PORT` | Standalone Let's Encrypt | Optional | Host port for HTTPS in standalone mode |

Practical defaults:

- Local Traefik HTTP: keep `.env.example` defaults and use `make up`
- Local standalone HTTP: set `DOCKERCART_HTTP_PORT` only if port `80` is busy
- Standalone HTTPS: explicitly set `DOCKERCART_URL`, `DOCKERCART_HTTPS_URL`, and `DOCKERCART_SSL_ENABLED=true`

## Troubleshooting

### Browser warns about the certificate

That is expected in self-signed mode. Use `make letsencrypt` or `make standalone-letsencrypt` only with a real public domain if you need a trusted certificate.

### Let's Encrypt fails in Traefik mode

Check the basics:

```bash
nslookup your-domain.tld
curl -I http://your-domain.tld
docker compose logs -f traefik
```

What to verify:

- The domain resolves to this server
- Ports `80` and `443` are open
- `SSL_DOMAIN` is not left as `example.com`

### Let's Encrypt fails in standalone mode

Inspect the standalone stack and certbot logs:

```bash
docker compose -f docker-compose.standalone.yml -f docker-compose.standalone.letsencrypt.yml logs -f nginx certbot
```

What to verify:

- The domain resolves to this server
- The ACME challenge path is reachable over HTTP
- `DOCKERCART_HTTP_PORT=80` and `DOCKERCART_HTTPS_PORT=443` are mapped as expected

### I changed mode but URLs still look wrong

Recheck the application-facing variables in `.env`:

- `DOCKERCART_URL`
- `DOCKERCART_HTTPS_URL`
- `DOCKERCART_SSL_ENABLED`

This matters most in standalone HTTPS mode, where the app must generate HTTPS links explicitly.

### Useful commands

```bash
make help
make down
make logs
make logs-follow
docker compose ps
docker compose logs -f nginx
docker compose logs -f apache
```

## Summary

- Use `make up` for default Traefik HTTP
- Use `make standalone` for the simplest local non-Traefik run
- Use `make ssl` for local HTTPS with a self-signed certificate
- Use `make letsencrypt` for production HTTPS with Traefik
- Use `make standalone-letsencrypt` for production HTTPS without Traefik

If you only need one rule of thumb: **local work starts with `make standalone`, public HTTPS starts with Let's Encrypt**.
