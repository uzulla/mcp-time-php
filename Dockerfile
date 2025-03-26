FROM php:8.3-cli

# Set working directory
WORKDIR /app

# Copy application code
COPY . /app/

# Install required extensions
RUN apt-get update && apt-get install -y \
    tzdata \
    && docker-php-ext-install -j$(nproc) pcntl \
    && docker-php-ext-install -j$(nproc) posix \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set timezone data
ENV DEBCONF_NONINTERACTIVE_SEEN=true
ENV DEBIAN_FRONTEND=noninteractive

# Set entry point
ENTRYPOINT ["php", "src/index.php"]

# Allow command line arguments to be passed
CMD []