#!/bin/bash
travisdir=$(dirname $(readlink /proc/$$/fd/255))
testdir="$travisdir/../tests"
testedComponents=(`cat "$travisdir/tested-components"`)
result=0

mkdir -p "$travisdir/log"
cat "$travisdir/tested-components" | xargs -n 1 -L 1 -P 8 $travisdir/run-single-test.sh

for testedComponent in "${testedComponents[@]}" do
    echo "$testedComponent:"
    component=${testedComponent/\//-}
    cat "$travisdir/log/$component-output"
    componentResult=(`cat "$travisdir/log/$component-exitCode"`)
    result=$(($result || $componentResult))
done

exit $result
