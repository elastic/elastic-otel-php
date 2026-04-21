#!/bin/sh

find elastic/native/ -type f \
	\( -name "*.cpp" -o -name "*.h" \) | xargs -L1 tools/license/check_license.sh
STATUS=$?
find elastic/php/ -type f -not -path "elastic/php/vendor_*" -name *.php | xargs -L1 tools/license/check_license.sh
STATUS=$(expr ${STATUS} + $?)

if [ $STATUS -ne 0 ]; then
	exit 1
fi
