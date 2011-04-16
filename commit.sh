fail=/tmp/octave-fail

function ensure_identity
{
	# test whether standard identities have been added to the agent already
	ssh-add -l | grep "The agent has no identities" > /dev/null
	if [ $? -eq 0 ]; then # Nope
		echo "From ensure_identity"
		ssh-add && return 0 || return 1
	fi
	return 0
}

phpunit phpunit.php > "$fail" || (
	cat "$fail" &&
	rm "$fail" &&
	false
) &&
rm -f "$fail" &&
git commit -a &&
ensure_identity &&
git push &&
phpdoc -q -i test -dn octave-controller -d . -t /var/www/html/projects.moongate.ro/octave-daemon -ti "Octave-daemon"
