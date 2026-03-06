#!/bin/bash

# Create necessary directories for Manticore
mkdir -p /var/lib/manticore/data
mkdir -p /var/run/manticore
mkdir -p /var/log/manticore

# Set permissions
chmod 755 /var/lib/manticore
chmod 755 /var/lib/manticore/data
chmod 755 /var/run/manticore
chmod 755 /var/log/manticore

# Start Manticore
exec /usr/bin/searchd --nodetach --config /etc/manticoresearch/manticore.conf
