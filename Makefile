check: psalm tests

psalm:
	php vendor/bin/psalm --config=psalm.xml --no-cache

tests:
	php vendor/bin/phpunit --configuration=phpunit.xml --colors=always

tests-coverage:
	php vendor/bin/phpunit --configuration=phpunit.xml --colors=always --coverage-text
