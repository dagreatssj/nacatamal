CURL:=$(shell which curl)
PHP:=$(shell which php)
NACATAMAL_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
INTERNALS_BIN=$(NACATAMAL_DIR)/.internals/bin
PHP_EXTERNAL=php-5.6.14

all: install

install: folders
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

folders:
	mkdir -p .internals/releases
	mkdir -p .internals/repositories
	mkdir -p .internals/logging

php: php-setup
	cd $(NACATAMAL_DIR)/external/$(PHP_EXTERNAL) && ./configure --prefix=$(INTERNALS_BIN) \
		&& make && make install

php-setup:
	cd $(NACATAMAL_DIR)/external && ./php_deps_install.sh
	cd $(NACATAMAL_DIR)/external && tar -xvf $(PHP_EXTERNAL)* -C $(NACATAMAL_DIR)/external
	mkdir -p .internals/bin