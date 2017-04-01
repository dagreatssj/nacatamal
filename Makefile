NACATAMAL_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

all: install

.PHONY: install
install: folders
	curl -sS http://getcomposer.org/installer | php
	php composer.phar install

.PHONY: folders
folders:
	mkdir -p internals/packages
	mkdir -p internals/saved_repositories
	mkdir -p internals/tmp
	mkdir -p internals/logs

