FROM python:3.9-slim

# Install PHP and cURL extension
RUN apt-get update && apt-get install -y \
    php \
    php-curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy application files
COPY app.py checkout_handler.php requirements.txt ./

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Expose port
EXPOSE 5000

# Run the application
CMD ["gunicorn", "--bind", "0.0.0.0:5000", "app:app"]