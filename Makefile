CURL:=$(shell which curl)
NACATAMAL_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
INTERNALS_BIN=$(NACATAMAL_DIR)/.internals/deps
PHP_EXTERNAL=php-5.6.14

all: install

.PHONY: install
install: folders config_file
	$(eval PHP:=$(shell which php))
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

.PHONY: install_with_php
install_with_php: needed-php php-setup php run-composer config_file

.PHONY: folders
folders:
	mkdir -p .internals/releases
	mkdir -p .internals/repositories
	mkdir -p .internals/logging
	mkdir -p .internals/tmp

.PHONY: php
php:
	cd $(NACATAMAL_DIR)/external/$(PHP_EXTERNAL) && ./configure --prefix=$(INTERNALS_BIN) \
		--with-curl \
		--with-openssl \
		--enable-zip \
		&& make && make install

.PHONY: run-composer
run-composer:
	$(eval PHP:=$(NACATAMAL_DIR)/.internals/deps/bin/php)
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

.PHONY: needed-php
needed-php:
	cd $(NACATAMAL_DIR)/external && ./php_deps_install.sh

.PHONY: php-setup
php-setup:
	cd $(NACATAMAL_DIR)/external && tar -xvf $(PHP_EXTERNAL)* -C $(NACATAMAL_DIR)/external
	mkdir -p .internals/deps

.PHONY: config_file
config_file:
	cd $(NACATAMAL_DIR)/config && cp config.yml.dist config.yml
