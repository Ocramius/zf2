#!/bin/bash
travisdir=$(dirname $(readlink /proc/$$/fd/255))
testdir="$travisdir/../tests"
testedcomponents=(`cat "$travisdir/tested-components"`)
result=0

# #cat "$travisdir/tested-components" | xargs -0 -n 1 -P 8 sh -c "phpunit -c $testdir/phpunit.xml $testdir/$1"



cat "$travisdir/tested-components" | xargs -n 1 -L 1 -P 8 ./run-single-test.sh 



#find /path -print0 | xargs -0 -n 1 -P <nr_procs> sh -c 'pngcrush $1 temp.$$ && mv temp.$$ $1' sh
#for tested in "${testedcomponents[@]}"
#    do
#        echo "$tested:"
#        phpunit -c $testdir/phpunit.xml $testdir/$tested
#        result=$(($result || $?))
#done

exit $result
