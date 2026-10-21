#!/bin/sh

set -eu

php_error_log="tests/_output/php-errors.log"
if [ ! -r "$php_error_log" ]; then
    echo "PHP error log is missing or unreadable: $php_error_log" >&2
    exit 1
fi

if grep -Eq 'PHP (Deprecated|Warning|Notice|Fatal error|Parse error|Recoverable fatal error):' "$php_error_log"; then
    echo "PHP runtime diagnostics were recorded in $php_error_log" >&2
    exit 1
fi
