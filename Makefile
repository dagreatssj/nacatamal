CURL=`which curl`
PHP=`which php`

all: install

install:
	$(CURL) -sS http://getcomposer.org/installer | $(PHP)
	$(PHP) composer.phar install
