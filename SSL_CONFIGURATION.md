# SSL/HTTPS Configuration Guide

This guide explains how to switch between HTTP (default) and HTTPS modes in DockerCart using Traefik.

## Overview

DockerCart supports three SSL/HTTPS modes:

1. **HTTP (default)** - No SSL, suitable for local development
2. **Self-signed SSL** - Local testing with HTTPS
3. **Let's Encrypt SSL** - Production with real domain and auto-renewal

You **no longer need to manually edit `docker-compose.yml`** to switch modes. Instead, use the command-line interface or make targets.

---

## Mode 1: HTTP (Default - No SSL)

### Using Make

```bash
make up
```

### Using start.sh Directly

```bash
./start.sh
```

**Result:**
- Access store: `http://dockercart.local`
- Traefik entrypoint: `web` (port 80)
- No SSL/certificates required
- Fastest startup, suitable for local development

---

## Mode 2: Self-Signed SSL (HTTPS with Self-Signed Certificate)

Use this for local testing with HTTPS. A self-signed certificate is automatically generated.

### Using Make

```bash
make ssl
```

### Using start.sh Directly

```bash
./start.sh --ssl
```

**Result:**
- Access store: `https://dockercart.local` ⚠️ (browser warning expected)
- Traefik entrypoint: `websecure` (port 443)
- Self-signed certificate auto-generated in `docker/ssl/`
- Certificate resolver: `selfsigned`
- **Browser will show security warning** (expected for self-signed certs)

### Notes

- First run generates `docker/ssl/certs/dockercart.crt` and `docker/ssl/private/dockercart.key`
- Subsequent runs reuse the existing certificate
- Valid for 365 days
- Suitable for local development and testing only

---

## Mode 3: Let's Encrypt SSL (Production HTTPS)

Use this for production with a real domain and automatic certificate renewal.

### Prerequisites

1. **Own a real domain** (e.g., `example.com`)
2. **Point DNS to your server** where Traefik/DockerCart is running
3. **Configure `.env` variables**

### Setup in .env

Edit `.env` and set:

```env
# Use your actual domain
DOCKERCART_DOMAIN=example.com

# SSL/Let's Encrypt Configuration
SSL_DOMAIN=example.com
SSL_EMAIL=admin@example.com
```

### Using Make

```bash
make letsencrypt
```

### Using start.sh Directly

```bash
./start.sh --letsencrypt
```

**Result:**
- Access store: `https://example.com`
- Traefik entrypoint: `websecure` (port 443)
- Real SSL certificate from Let's Encrypt
- **Automatic renewal** (runs automatically before expiry)
- Certificate stored in `docker/acme/acme.json`

### Important Notes

- ⚠️ **DNS must resolve** before starting Traefik, or ACME challenge will fail
- ⚠️ **Port 80 and 443 must be accessible** from the internet
- ⚠️ **`DOCKERCART_DOMAIN` and `SSL_DOMAIN` must match** your real domain
- Certificate renewal is automatic, no additional action required
- Certificates expire after 90 days and are renewed automatically

### Troubleshooting Let's Encrypt

If the ACME challenge fails:

1. Check DNS resolution:
   ```bash
   nslookup example.com
   ```

2. Verify ports are open:
   ```bash
   curl -I http://example.com
   ```

3. Check Traefik logs:
   ```bash
   docker compose logs -f traefik
   ```

4. If stuck, reset and try again:
   ```bash
   make down
   rm -rf docker/acme/acme.json
   make letsencrypt
   ```

---

## Switching Between Modes

To switch from one mode to another:

```bash
# Stop containers
make down

# Start in new mode
make up              # HTTP
# OR
make ssl             # Self-signed SSL
# OR
make letsencrypt     # Let's Encrypt SSL
```

The docker-compose override files ensure the correct Traefik labels are applied automatically. **No manual editing of `docker-compose.yml` needed.**

---

## How It Works (Technical Details)

The system uses **Docker Compose override files**:

- `docker-compose.yml` - Base configuration (default labels for HTTP)
- `docker-compose.no-ssl.yml` - HTTP labels override (default)
- `docker-compose.ssl.yml` - Self-signed SSL labels override
- `docker-compose.letsencrypt.yml` - Let's Encrypt SSL labels override

The `start.sh` script automatically includes the correct override file based on the chosen mode:

```bash
./start.sh              # Includes docker-compose.no-ssl.yml
./start.sh --ssl        # Includes docker-compose.ssl.yml
./start.sh --letsencrypt # Includes docker-compose.letsencrypt.yml
```

No comments are needed; no manual file editing required. ✅

---

## Environment Variables Reference

Key variables for SSL mode:

| Variable | Default | Purpose |
|----------|---------|---------|
| `DOCKERCART_DOMAIN` | `dockercart.local` | Domain name for Traefik routing |
| `SSL_DOMAIN` | `example.com` | Domain for Let's Encrypt certificate |
| `SSL_EMAIL` | `admin@example.com` | Email for Let's Encrypt notifications |
| `DOCKERCART_URL` | `http://dockercart.local` | Store URL (see note below) |
| `DOCKERCART_HTTPS_URL` | `http://dockercart.local` | Store HTTPS URL (see note below) |
| `DOCKERCART_SSL_ENABLED` | `false` | PHP app SSL flag (keep as-is) |

### Note on URLs

- `DOCKERCART_URL` and `DOCKERCART_HTTPS_URL` are read by the PHP app for internal links
- For HTTP mode: set both to `http://dockercart.local`
- For SSL modes: update the PHP app configuration separately or via admin panel
- Traefik routing is controlled independently via the override files

---

## Useful Commands

```bash
# Check running containers and their status
docker compose ps

# View Traefik logs (useful for debugging SSL issues)
docker compose logs -f traefik

# View Nginx logs
docker compose logs -f nginx

# View Apache logs
docker compose logs -f apache

# Restart all services
make restart

# Full stop and cleanup
make down
```

---

## Summary Table

| Goal | Command | Mode | SSL | Files Included |
|------|---------|------|-----|-----------------|
| Local dev (HTTP) | `make up` | HTTP | ❌ No | `docker-compose.no-ssl.yml` |
| Local test (HTTPS) | `make ssl` | Self-signed | ✅ Yes | `docker-compose.ssl.yml` |
| Production | `make letsencrypt` | Real cert | ✅ Yes | `docker-compose.letsencrypt.yml` |

---

## Frequently Asked Questions

**Q: Can I use a different port?**
A: Yes, modify the ports in the Traefik command or docker-compose override file.

**Q: What if Let's Encrypt fails?**
A: Check DNS resolution and port accessibility. See Troubleshooting section above.

**Q: Can I switch back to HTTP from SSL?**
A: Yes, `make down` then `make up` restarts without SSL.

**Q: Where are certificates stored?**
A: 
- Self-signed: `docker/ssl/`
- Let's Encrypt: `docker/acme/acme.json`

**Q: How long are certificates valid?**
A:
- Self-signed: 365 days
- Let's Encrypt: 90 days (auto-renewed)

---

For more information, see `README.md` and `.env.example`.
