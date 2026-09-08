FROM ubuntu:22.04
ARG DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Argentina/San_Juan

# Configurar locales
RUN apt-get update && \
    apt-get install -y locales && \
    locale-gen es_ES.UTF-8 && \
    update-locale LANG=es_ES.UTF-8

# Establecer variables de entorno de locale
ENV LANG=es_ES.UTF-8
ENV LC_ALL=es_ES.UTF-8

# Instala la versión de PHP 8.5 o superior más reciente disponible en el PPA,
# junto con extensiones comunes para Laravel, incluyendo SQLite
RUN apt-get update && \
    apt-get install -y software-properties-common tzdata ca-certificates gnupg && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update && \
    PHP_VERSION=$(apt-cache search --names-only '^php8\.[5-9]$' | awk '{print $1}' | sed 's/^php//' | sort -V | tail -n1) && \
    echo "Instalando PHP ${PHP_VERSION}" && \
    apt-get install -y \
        php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
        php${PHP_VERSION}-bcmath php${PHP_VERSION}-curl php${PHP_VERSION}-zip \
        php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-pdo php${PHP_VERSION}-pdo-mysql \
        php${PHP_VERSION}-gd php${PHP_VERSION}-intl php${PHP_VERSION}-readline \
        openssl curl git unzip composer \
        # Herramientas de red y diagnóstico
        iputils-ping \
        net-tools \
        dnsutils \
        traceroute \
        telnet \
        netcat-openbsd \
        wget \
        iproute2 \
        tcpdump \
        nmap \
        htop \
        vim \
        nano

# Instalar Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Verificar instalación de Node.js y npm
RUN node --version && npm --version

# Instalar Mailpit (servidor SMTP local para desarrollo)
RUN curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | bash

# Limpieza
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala Composer (por si acaso)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
