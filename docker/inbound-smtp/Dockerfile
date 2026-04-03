FROM python:3.12-slim

RUN pip install --no-cache-dir aiosmtpd requests

WORKDIR /app

COPY docker/inbound-smtp/server.py /app/server.py

CMD ["python", "/app/server.py"]
