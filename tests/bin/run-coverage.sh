#!/bin/sh

set -u

output_directory="tests/_output"
chunk_directory="$output_directory/coverage-chunks"
c3_header="X-Codeception-CodeCoverage: remote-access"
project_directory=$(pwd)

mkdir -p "$chunk_directory"

reset_coverage() {
    c3_url="$1/c3/report"

    curl --fail --silent --show-error \
        --header "$c3_header" \
        "$c3_url/clear" \
        >/dev/null
}

fetch_coverage() {
    chunk_name="$1"
    c3_url="$2/c3/report"

    curl --fail --silent --show-error \
        --header "$c3_header" \
        "$c3_url/serialized" \
        --output "$chunk_directory/${chunk_name}.cov"
}

run_suite() {
    suite="$1"
    chunk_name="$2"
    base_url="$3"

    rm -f "$chunk_directory/${chunk_name}.cov"
    reset_coverage "$base_url" || return 1

    E2E_BASE_URL="$base_url" vendor/bin/codecept run \
        -c codeception.yml \
        "$suite" \
        --coverage \
        --coverage-html "${chunk_name}.coverage" \
        --coverage-xml "${chunk_name}.coverage.xml" \
        --html "${chunk_name}.report.html" \
        --xml "${chunk_name}.junit.xml" \
        --no-colors
    suite_status=$?

    fetch_coverage "$chunk_name" "$base_url" || return 1

    return "$suite_status"
}

status=0

run_suite Browser browser "$E2E_BASE_URL" || status=1
run_suite Http http "$E2E_BASE_URL" || status=1
run_suite Installer installer "http://installer:8000" || status=1

php tests/bin/reset-database.php || exit 1
php tests/bin/prepare.php || exit 1

run_suite SmartBuild smart-build "http://smart-builder:8000" || status=1

rm -f "$chunk_directory"/cli-*.cov "$chunk_directory"/cli-*.error.log
vendor/bin/codecept run -c codeception.yml Cli --no-colors \
    --html "cli.report.html" \
    --xml "cli.junit.xml" \
    || status=1

set -- "$chunk_directory"/cli-*.cov
if [ -e "$1" ]; then
    php tests/bin/combine-coverage.php "$chunk_directory/cli.cov" "$@" || status=1
else
    echo "CLI coverage did not produce any chunks." >&2
    status=1
fi

php tests/bin/reset-database.php || exit 1
php tests/bin/prepare.php || exit 1

vendor/bin/phpunit -c phpunit.xml \
    --coverage-php "$project_directory/$chunk_directory/integration.cov" \
    --log-junit "$project_directory/$output_directory/integration.junit.xml" \
    --display-warnings \
    --display-deprecations \
    || status=1

php tests/bin/merge-coverage.php \
    "$chunk_directory/browser.cov" \
    "$chunk_directory/http.cov" \
    "$chunk_directory/installer.cov" \
    "$chunk_directory/smart-build.cov" \
    "$chunk_directory/cli.cov" \
    "$chunk_directory/integration.cov" \
    || status=1

exit "$status"
