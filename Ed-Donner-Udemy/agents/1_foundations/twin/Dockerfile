FROM python:3.12-slim

WORKDIR /app

ENV PYTHONUNBUFFERED=1 \
    GRADIO_ANALYTICS_ENABLED=False

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

CMD ["python", "app.py"]
