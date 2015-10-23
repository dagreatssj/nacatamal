CURL:=$(shell which curl)
NACATAMAL_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
INTERNALS_BIN=$(NACATAMAL_DIR)/.internals/deps
PHP_EXTERNAL=php-5.6.14

all: install

install: folders
	$(eval PHP:=$(shell which php))
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

folders:
	mkdir -p .internals/releases
	mkdir -p .internals/repositories
	mkdir -p .internals/logging

install_with_php: php-setup folders
	cd $(NACATAMAL_DIR)/external/$(PHP_EXTERNAL) && ./configure --prefix=$(INTERNALS_BIN) \
		&& make && make install
	$(eval PHP:=$(NACATAMAL_DIR)/.internals/deps/bin/php)
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

php-setup:
	cd $(NACATAMAL_DIR)/external && ./php_deps_install.sh
	cd $(NACATAMAL_DIR)/external && tar -xvf $(PHP_EXTERNAL)* -C $(NACATAMAL_DIR)/external
	mkdir -p .internals/deps
