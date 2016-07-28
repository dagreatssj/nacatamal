CURL:=$(shell which curl)
NACATAMAL_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

all: install

.PHONY: install
install: folders config_file
	$(eval PHP:=$(shell which php))
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

.PHONY: folders
folders:
	mkdir -p .internals/releases
	mkdir -p .internals/repositories
	mkdir -p .internals/logging
	mkdir -p .internals/tmp

.PHONY: config_file
config_file:
	cd $(NACATAMAL_DIR)/config && cp config.yml.dist config.yml
