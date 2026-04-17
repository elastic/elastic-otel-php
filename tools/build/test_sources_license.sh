#!/bin/sh

find elastic/native/ -type f \
	-not -path "elastic/native/_build/*" \
	-not -path "elastic/native/libsemconv/include/*" \
	\( -name "*.cpp" -o -name "*.h" \) | xargs -L1 tools/license/check_license.sh
STATUS=$?
find elastic/php/ -type f -not -path "elastic/php/vendor_*" -name *.php | xargs -L1 tools/license/check_license.sh
STATUS=$(expr ${STATUS} + $?)
find tests/ -type f -name *.php | xargs -L1 tools/license/check_license.sh
STATUS=$(expr ${STATUS} + $?)

if [ $STATUS -ne 0 ]; then
	exit 1
fi
