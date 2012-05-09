#!/bin/bash
travisDir=$(dirname $(readlink /proc/$$/fd/255))
testDir="$travisDir/../tests"
logFilePrefix=${1//\//-}
outputFile="$travisDir/log/$logFilePrefix-output"
exitCodeFile="$travisDir/log/$logFilePrefix-exitCode"

echo "$1:" > $outputFile
phpunit -c $testDir/phpunit.xml $testDir/$1 >> $outputFile
echo $? > $exitCodeFile
