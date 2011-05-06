TARGET="generated-documentation"
if [ ! -d $TARGET ]; then mkdir $TARGET || {
	echo Failed creating directory for documentation [$TARGET]
	exit 1
}; fi
rm -rf $TARGET/* &&
phpdoc -o HTML:Smarty:PHP -q -i /examples/*,/test/*,/install/install-* -d . -t $TARGET -ti "Octave Daemon"
