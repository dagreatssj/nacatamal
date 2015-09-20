CURL=`which curl`
PHP=`which php`

all: install

install:
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install

folders:
	mkdir -p .internals/releases
	mkdir -p .internals/repositories
	mkdir -p .internals/logging