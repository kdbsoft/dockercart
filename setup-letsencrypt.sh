#!/bin/bash

# DockerCart Let's Encrypt SSL Certificate Generator
# Usage: ./setup-letsencrypt.sh

set -e

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║     DockerCart Let's Encrypt SSL Setup                    ║"
echo "╚═══════════════════════════════════════════════════════════╝"

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found!"
    echo ""
    echo "Please copy .env.example to .env and configure it:"
    echo "  cp .env.example .env"
    echo ""
    echo "Required variables:"
    echo "  SSL_DOMAIN=your-domain.com"
    echo "  SSL_EMAIL=admin@your-domain.com"
    exit 1
fi

# Load environment variables
export $(cat .env | grep -E "SSL_DOMAIN|SSL_EMAIL" | xargs)

if [ -z "$SSL_DOMAIN" ] || [ -z "$SSL_EMAIL" ]; then
    echo "❌ Error: SSL_DOMAIN or SSL_EMAIL not set in .env"
    echo ""
    echo "Add to .env:"
    echo "  SSL_DOMAIN=your-domain.com"
    echo "  SSL_EMAIL=admin@your-domain.com"
    exit 1
fi

echo "📋 Configuration:"
echo "  Domain: $SSL_DOMAIN"
echo "  Email:  $SSL_EMAIL"
echo ""

# Create necessary directories
mkdir -p docker/letsencrypt/{certs,keys,www}
echo "✅ Created directories"

# Check if certificate already exists
if [ -f "docker/letsencrypt/certs/cert.pem" ] && [ -f "docker/letsencrypt/keys/key.pem" ]; then
    echo ""
    echo "⚠️  Certificates already exist!"
    echo "   Path: docker/letsencrypt/certs/cert.pem"
    echo ""
    read -p "Do you want to renew them? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Skipping renewal."
        exit 0
    fi
fi

echo ""
echo "⏳ Starting docker-compose with Let's Encrypt mode..."
echo ""

# Start MySQL and other services first
docker-compose -f docker-compose-letsencrypt.yml up -d mysql

# Wait for MySQL to be healthy
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Start Certbot to obtain initial certificate
echo "⏳ Obtaining SSL certificate from Let's Encrypt..."
echo ""

docker-compose -f docker-compose-letsencrypt.yml run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email "$SSL_EMAIL" \
    --agree-tos \
    --no-eff-email \
    --keep-until-expiring \
    --non-interactive \
    -d "$SSL_DOMAIN" \
    -d "www.$SSL_DOMAIN" 2>&1 || {
        echo ""
        echo "❌ Failed to obtain certificate!"
        echo ""
        echo "Troubleshooting:"
        echo "1. Make sure domain points to this server"
        echo "2. Firewall allows port 80 (HTTP)"
        echo "3. Check DNS: nslookup $SSL_DOMAIN"
        echo "4. Check logs: docker-compose -f docker-compose-letsencrypt.yml logs certbot"
        exit 1
    }

echo ""
echo "✅ Certificate obtained successfully!"
echo ""

# Copy certificates to expected paths
echo "📝 Configuring certificate paths..."

CERT_DIR="docker/letsencrypt/certs/live/$SSL_DOMAIN"

if [ -d "$CERT_DIR" ]; then
    cp "$CERT_DIR/fullchain.pem" docker/letsencrypt/certs/cert.pem
    cp "$CERT_DIR/privkey.pem" docker/letsencrypt/keys/key.pem
    chmod 644 docker/letsencrypt/certs/cert.pem
    chmod 644 docker/letsencrypt/keys/key.pem
    echo "✅ Certificates configured"
else
    echo "❌ Certificate directory not found: $CERT_DIR"
    exit 1
fi

echo ""
echo "⏳ Starting DockerCart..."
docker-compose -f docker-compose-letsencrypt.yml up -d

echo ""
echo "✅ Let's Encrypt setup complete!"
echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║              Setup Complete                              ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
echo "📌 Next steps:"
echo ""
echo "1. Access DockerCart:"
echo "   https://$SSL_DOMAIN"
echo "   https://www.$SSL_DOMAIN"
echo ""
echo "2. Admin panel:"
echo "   https://$SSL_DOMAIN/admin"
echo ""
echo "3. phpMyAdmin:"
echo "   ${PMA_URL:-http://pma.${SSL_DOMAIN}}"
echo ""
echo "4. Certificate will auto-renew in 30 days"
echo ""
echo "📁 Certificates are stored in:"
echo "   docker/letsencrypt/certs/"
echo ""
echo "🔄 Manual renewal:"
echo "   docker-compose -f docker-compose-letsencrypt.yml run --rm certbot renew"
echo ""
