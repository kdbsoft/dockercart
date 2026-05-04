FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY migrate.py /app/migrate.py
COPY entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh

# config.yaml and images/ are mounted at runtime

ENTRYPOINT ["/app/entrypoint.sh"]
