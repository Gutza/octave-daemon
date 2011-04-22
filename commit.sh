fail=/tmp/octave-fail

function ensure_identity
{
	# test whether standard identities have been added to the agent already
	ssh-add -l | grep "The agent has no identities" > /dev/null
	if [ $? -eq 0 ]; then # Nope
		ssh-add && return 0 || return 1
	fi
	return 0
}

phpunit test/ > "$fail" || (
	cat "$fail" &&
	rm "$fail" &&
	false
) &&
rm -f "$fail" &&
git commit -a &&
./gendoc.sh &&
ensure_identity &&
git push
