# Use a lightweight Python image
FROM python:3.13-slim

# Install PHP CLI
RUN apt-get update && \
    apt-get install -y php-cli && \
    rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy all project files
COPY . /app

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Expose Flask port
EXPOSE 5000

# Start Flask app using Gunicorn
CMD ["gunicorn", "app:app", "--bind", "0.0.0.0:5000"]
