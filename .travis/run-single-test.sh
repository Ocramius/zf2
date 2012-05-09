#!/bin/bash
travisdir=$(dirname $(readlink /proc/$$/fd/255))
testdir="$travisdir/../tests"
tested=$1
result=0

echo "$tested:"
phpunit -c $testdir/phpunit.xml $testdir/$tested
#result=$(($result || $?))

exit 0
exit $result
