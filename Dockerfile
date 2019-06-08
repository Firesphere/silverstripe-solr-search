FROM brettt89/silverstripe-web:7.1-platform

LABEL maintainer="Marco Hermo"

ENV DEBIAN_FRONTEND=noninteractive

# Debian Jessie Archive sources
RUN echo "deb http://deb.debian.org/debian/ jessie main contrib non-free" > /etc/apt/sources.list && \
    echo "deb-src http://deb.debian.org/debian/ jessie main contrib non-free" >> /etc/apt/sources.list && \
    echo "deb http://security.debian.org/ jessie/updates main contrib non-free" >> /etc/apt/sources.list && \
    echo "deb-src http://security.debian.org/ jessie/updates main contrib non-free" >> /etc/apt/sources.list

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bzip2 ca-certificates libffi-dev libgdbm3 \
        libssl-dev libyaml-dev procps zlib1g-dev \
        autoconf libtool nasm software-properties-common \
        ssmtp locales

# Custom PHP Configurations
RUN echo "sendmail_path = /usr/sbin/ssmtp -t" > /usr/local/etc/php/conf.d/sendmail.ini && \
    echo "memory_limit = 256M;" > /usr/local/etc/php/conf.d/memory.ini && \
    echo "log_errors = On;\nerror_log = /dev/stderr" > /usr/local/etc/php/conf.d/errors.ini

# Set encoding for SASS
RUN echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen \
    && update-locale

ENV LC_ALL en_US.UTF-8
ENV LANG en_US.UTF-8

# Install PHP Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# NodeJS and common global NPM modules
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash - && \
    apt-get install -qqy nodejs

# Yarn
RUN curl -sL https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    apt-get update && apt-get install -qqy yarn

# Install Java 11 Runtime
RUN echo debconf shared/accepted-oracle-license-v1-2 select true | \
    debconf-set-selections && \
    echo debconf shared/accepted-oracle-license-v1-2 seen true | \
    debconf-set-selections && \
    echo "deb http://ppa.launchpad.net/linuxuprising/java/ubuntu bionic main" | tee /etc/apt/sources.list.d/linuxuprising-java.list && \
    apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 73C3DB2A && \
    apt-get update && apt-get install -qqy lsof oracle-java11-installer && java -version

# Install Solr 8.1.1
RUN cd /opt && \
    wget https://www-us.apache.org/dist/lucene/solr/8.1.1/solr-8.1.1.tgz && \
    tar xvf solr-8.1.1.tgz solr-8.1.1/bin/install_solr_service.sh --strip-components=2 && \
    bash ./install_solr_service.sh solr-8.1.1.tgz

RUN apt-get autoremove -y && rm -r /var/lib/apt/lists/*
