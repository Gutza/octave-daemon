<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

$c=new Octave_controller();
//$c->octave_binary="octave -i";
$c->init();

while(true) {
	$line=readline("> ");
	readline_add_history($line);
	$c->lastError=$payload="";

	if (strpos($line," "))
		list($command,$payload)=explode(" ",$line,2);
	else
		$command=$line;

	if ($command=="quit") {
		echo "Bye!\n";
		exit;
	}

	if (in_array($command,array("run","runExec","query")))
		echo $c->$command($payload)."\n";
	else
		echo "Error: unknown command \"$command\"\n";
}
