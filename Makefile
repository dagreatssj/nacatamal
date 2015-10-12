CURL=`which curl`
PHP=`which php`
NACATAMAL_DIR=`pwd`
INTERNALS_BIN=$(NACATAMAL)/.internals/bin

all: install

install: folders
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

folders:
	mkdir -p .internals/releases
	mkdir -p .internals/repositories
	mkdir -p .internals/logging

php: php-setup
	cd $(NACATAMAL_DIR)/external && ./configure --prefix=$(INTERNALS_BIN) \
		&& make && make install

php-setup:
	PHP_EXTERNAL=$(NACATAMAL_DIR)/external/php-5.6.14.tar.gz
	tar -xvf $(PHP_EXTERNAL)* -C $(NACATAMAL_DIR)/external
	mkdir -p .internals/bin