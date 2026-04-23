# SSL/HTTPS Switching - Quick Start

## 🎯 What Changed?

**Before**: You had to manually edit `docker-compose.yml` and uncomment/comment SSL configuration lines.

**Now**: Use simple commands to switch between HTTP and HTTPS modes. No manual file editing needed! ✅

---

## 📝 Four Ways to Run DockerCart

### 1️⃣ HTTP (Default) - Fastest for Local Dev

```bash
make up
# or
./start.sh
```

**Access**: `http://dockercart.local`

---

### 2️⃣ HTTPS with Self-Signed (Local Testing)

```bash
make ssl
# or
./start.sh --ssl
```

**Access**: `https://dockercart.local` (browser warning expected)
- Auto-generates self-signed certificate
- Files: `docker/ssl/certs/` & `docker/ssl/private/`

---

### 3️⃣ HTTPS with Let's Encrypt (Production)

**Setup** (edit `.env`):
```env
DOCKERCART_DOMAIN=example.com
SSL_DOMAIN=example.com
SSL_EMAIL=admin@example.com
```

**Then start**:
```bash
make letsencrypt
# or
./start.sh --letsencrypt
```

**Access**: `https://example.com`
- Real SSL certificate
- Auto-renewal before expiry
- Requires valid domain pointing to your server

---

### 4️⃣ Standalone + Let's Encrypt (Production, no Traefik)

**Setup** (edit `.env`):
```env
DOCKERCART_DOMAIN=example.com
DOCKERCART_URL=https://example.com
DOCKERCART_HTTPS_URL=https://example.com
DOCKERCART_SSL_ENABLED=true
SSL_DOMAIN=example.com
SSL_EMAIL=admin@example.com
DOCKERCART_HTTP_PORT=80
DOCKERCART_HTTPS_PORT=443
```

**Then start**:
```bash
make standalone-letsencrypt
# or compatible alias
STANDALONE=1 make letsencrypt
```

**Access**: `https://example.com`
- No Traefik required
- Initial certificate is obtained automatically via certbot webroot challenge
- Renewal loop runs every 12h in `certbot` container
- Requires public DNS and open ports 80/443

---

## 🔄 Switching Modes

Just stop and start with a different mode:

```bash
make down
make ssl          # Switch to self-signed SSL
```

---

## 📋 How It Works

Three new Docker Compose override files handle the different modes:

- **`docker-compose.no-ssl.yml`** - HTTP labels (used by default)
- **`docker-compose.ssl.yml`** - Self-signed SSL labels
- **`docker-compose.letsencrypt.yml`** - Let's Encrypt labels

The `start.sh` script automatically includes the correct override file based on your mode choice.

**Result**: No commenting/uncommenting needed in `docker-compose.yml`! 🎉

---

## 🚀 Useful Shortcuts

```bash
# Make commands
make up              # Start HTTP (default)
make ssl             # Start with self-signed SSL
make letsencrypt     # Start with Let's Encrypt  
make standalone-letsencrypt # Start standalone + Let's Encrypt (no Traefik)
make down            # Stop all containers
make restart         # Restart without rebuild
make logs            # View container logs
make logs-follow     # Follow logs in real-time
make shell           # Open bash in Apache container

# Direct docker commands
docker compose ps                          # See running containers
docker compose logs -f dockercart_nginx   # Monitor Nginx
docker compose logs -f dockercart_apache  # Monitor Apache
```

---

## ⚙️ Environment Variables

For Let's Encrypt mode, set in `.env`:

```env
DOCKERCART_DOMAIN=example.com     # Your domain for Traefik routing
SSL_DOMAIN=example.com             # Domain for Let's Encrypt cert
SSL_EMAIL=admin@example.com        # Email for cert notifications
```

For standalone + Let's Encrypt mode, also set:

```env
DOCKERCART_URL=https://example.com
DOCKERCART_HTTPS_URL=https://example.com
DOCKERCART_SSL_ENABLED=true
DOCKERCART_HTTP_PORT=80
DOCKERCART_HTTPS_PORT=443
```

Default (in `.env.example`):
```env
DOCKERCART_DOMAIN=dockercart.local
SSL_DOMAIN=example.com
SSL_EMAIL=admin@example.com
```

---

## 📚 Full Documentation

See **`SSL_CONFIGURATION.md`** for detailed information, troubleshooting, and FAQ.

---

## ✅ What You Got

- ✅ **No more manual file editing** for SSL switching
- ✅ **Three clear modes** with one command each
- ✅ **Automatic certificate generation** for self-signed
- ✅ **Auto-renewal** with Let's Encrypt
- ✅ **Clean default** - HTTP mode is default (safest)
- ✅ **Easy mode switching** - just `make down` then restart with new mode

---

**That's it!** You can now switch SSL modes instantly with a single command. 🚀
