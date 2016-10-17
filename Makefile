NACATAMAL_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

all: install

.PHONY: install
install: folders config_file
	curl -sS http://getcomposer.org/installer | php
	php composer.phar install

.PHONY: folders
folders:
	mkdir -p internals/releases
	mkdir -p internals/repositories
	mkdir -p internals/tmp
	mkdir -p logging

.PHONY: config_file
config_file:
	cd $(NACATAMAL_DIR)/config && cp config.yml.dist config.yml
