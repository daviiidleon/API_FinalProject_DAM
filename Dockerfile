# --- Parte 1: Imagen Base ---
# Usamos Ubuntu 24.04 como base. Si tu API es PHP, Node.js, Python,
# es mejor usar una imagen más específica (ej. php:8.4-fpm, node:22-alpine, python:3.12-slim)
FROM ubuntu:22.04

# --- Parte 2: Metadatos y Variables (Opcional) ---
LABEL maintainer="Tu Nombre <tu.email@ejemplo.com>"

# --- Parte 3: Configuración del Entorno Básico del Contenedor ---
# Establece el directorio de trabajo dentro del contenedor. Aquí irá tu código.
WORKDIR /app

# Configura la zona horaria y evita que apt pida interacción
ENV DEBIAN_FRONTEND=noninteractive
# ¡Ajusta tu zona horaria!
ENV TZ=Europe/Madrid
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# --- Parte 4: Instalación de Dependencias del Sistema Operativo ---
# Aquí instalas las herramientas del sistema que tu API necesita.
# ADAPTA ESTA SECCIÓN a tu API.
# Por ejemplo, si es una API de Laravel/PHP:
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    nano \
    procps \
    gnupg \
    gosu \
    ca-certificates \
    zip \
    unzip \
    supervisor \
    sqlite3 \
    libcap2-bin \
    libpng-dev \
    python3 \
    dnsutils \
    librsvg2-bin \
    fswatch \
    ffmpeg \
    # Esto viene de tus logs de Sail. Si no usas todo, puedes quitarlo.
    # Por ejemplo, si no usas supervisord o sqlite3, quita 'supervisor' y 'sqlite3'.
    # Es mejor solo lo esencial.
    && rm -rf /var/lib/apt/lists/*

# --- Parte 5: Copiar el Código de tu API ---
# Copia todo el contenido de tu carpeta actual (tu proyecto API)
# al directorio de trabajo /app dentro del contenedor.
COPY . /app

# --- Parte 6: Instalación de Dependencias Específicas del Lenguaje/Framework ---
# ESTO ES CRÍTICO. ¡ADAPTA ESTA SECCIÓN A CÓMO INSTALAS LAS DEPENDENCIAS DE TU API!

# Ejemplo para una API PHP/Laravel (similar a Sail):
# Primero, añades el PPA de Ondrej para PHP
# Ejemplo para una API PHP/Laravel (similar a Sail):
# Primero, añades el PPA de Ondrej para PHP
RUN curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null && \
    echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu jammy main" > /etc/apt/sources.list.d/ppa_ondrej_php.list && \
    # Deshabilita temporalmente el repositorio de seguridad si causa 400 Bad Request
    sed -i '/security.ubuntu.com/d' /etc/apt/sources.list && \
    apt-get update && \
    apt-get install -y php8.4-cli php8.4-dev \
    php8.4-pgsql php8.4-sqlite3 php8.4-gd \
    php8.4-curl php8.4-mongodb \
    php8.4-imap php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap \
    php8.4-intl php8.4-readline \
    php8.4-ldap \
    php8.4-msgpack php8.4-igbinary php8.4-redis php8.4-swoole \
    php8.4-memcached php8.4-pcov php8.4-imagick php8.4-xdebug && \
    apt-get clean && rm -rf /var/lib/apt/lists/* && \
    # Instala Composer
    curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer && \
    # Instala dependencias de Composer (si tu API usa Composer)
    composer install --no-dev --optimize-autoloader
# Si usas Node.js:
# RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
#     echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" > /etc/apt/sources.list.d/nodesource.list && \
#     apt-get update && \
#     apt-get install -y nodejs && \
#     npm install -g npm && npm install -g pnpm && npm install -g bun && \
#     npm install --production # O pnpm install, yarn install

# --- Parte 7: Exponer Puertos ---
# Declara el puerto en el que tu API escuchará DENTRO del contenedor.
# Por ejemplo, si tu API usa el puerto 3000 (Node.js) o el 80 (servidor web), cámbialo aquí.
EXPOSE 8000

# --- Parte 8: Comando para Iniciar tu API ---
# Este comando se ejecutará cuando el contenedor se inicie.
# ¡ADAPTA ESTO COMPLETAMENTE A CÓMO SE INICIA TU API!

# Ejemplo para una API de Laravel (servidor de desarrollo Artisan):
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
# Ejemplo para una API Node.js (con un archivo index.js en la raíz):
# CMD ["node", "index.js"]
# Ejemplo para una API Python (con Gunicorn):
# CMD ["gunicorn", "--bind", "0.0.0.0:8000", "your_app_module:app"]
