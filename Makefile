.PHONY: check
check: lint cs tests phpstan

.PHONY: lint
lint:
	php vendor/bin/parallel-lint --colors src tests

.PHONY: cs
cs:
	composer install --working-dir build-cs && php build-cs/vendor/bin/phpcs

.PHONY: cs-fix
cs-fix:
	php build-cs/vendor/bin/phpcbf src tests

.PHONY: tests
tests:
	php vendor/bin/phpunit

.PHONY: phpstan
phpstan:
	php vendor/bin/phpstan
