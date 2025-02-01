#!/bin/sh

find prod/native/ -type f -not -path "prod/native/_build/*" -name *.cpp -o -name *.h | xargs -L1 tools/license/check_license.sh
STATUS=$?
find prod/php/ -type f -not -path "prod/php/vendor_*" -name *.php | xargs -L1 tools/license/check_license.sh
STATUS=`expr ${STATUS} + $?`
find tests/ -type f -name *.php | xargs -L1 tools/license/check_license.sh
STATUS=`expr ${STATUS} + $?`

if [ $STATUS -ne 0 ]; then
	exit 1
fi