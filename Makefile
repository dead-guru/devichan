COMPOSE := docker compose -p devichan-e2e -f compose.test.yml
E2E_SERVICES := cmysql cmysql-installer credis sftp cphp installer smart-builder caddy selenium

.PHONY: e2e e2e-reset e2e-build e2e-up e2e-prepare e2e-run e2e-suite e2e-down

e2e:
	@set -eu; \
		cleanup() { $(COMPOSE) down -v --remove-orphans; }; \
		trap cleanup EXIT HUP INT TERM; \
		$(MAKE) --no-print-directory e2e-reset; \
		$(MAKE) --no-print-directory e2e-build; \
		$(MAKE) --no-print-directory e2e-up; \
		$(MAKE) --no-print-directory e2e-prepare; \
		$(MAKE) --no-print-directory e2e-run

e2e-reset:
	$(COMPOSE) down -v --remove-orphans

e2e-build:
	$(COMPOSE) build cphp

e2e-up:
	$(COMPOSE) up -d $(E2E_SERVICES)

e2e-prepare:
	$(COMPOSE) run --rm runner vendor/bin/codecept build
	$(COMPOSE) run --rm prepare

e2e-run:
	$(COMPOSE) run --rm runner tests/bin/run-coverage.sh

e2e-suite: e2e-reset e2e-build e2e-up e2e-prepare
	@test -n "$(SUITE)" || (echo "Set SUITE. Example: make e2e-suite SUITE=Http" >&2; exit 2)
	$(COMPOSE) run --rm runner vendor/bin/codecept run $(SUITE) --no-colors

e2e-down:
	$(COMPOSE) down -v --remove-orphans
