FROM python:3.12-slim

# Install pymysql — the only runtime dependency
RUN pip install --no-cache-dir pymysql==1.1.*

WORKDIR /app

# Build context is migrate/opencart/, so paths are relative to that directory
COPY migrate.py /app/migrate.py
COPY entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh

ENTRYPOINT ["/app/entrypoint.sh"]
