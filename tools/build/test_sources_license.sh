#!/bin/sh

find elastic_prod/native/ -type f \
	\( -name "*.cpp" -o -name "*.h" \) | xargs -L1 tools/license/check_license.sh
STATUS=$?
find elastic_prod/php/ -type f -not -path "elastic_prod/php/vendor_*" -name *.php | xargs -L1 tools/license/check_license.sh
STATUS=$(expr ${STATUS} + $?)

if [ $STATUS -ne 0 ]; then
	exit 1
fi
