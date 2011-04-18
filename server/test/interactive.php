<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

$c=new Octave_controller();
$c->init();

echo "\n";
echo "Interactive Octave controller test.\n";
echo "\n";
echo "Type 'quit' to quit.\n";
echo "Type 'run <command>' to run Octave commands without output.\n";
echo "Type 'runRead <command>' to run an Octave command with output.\n";
echo "Type 'query <command>' to run one Octave command and receive the result.\n";
echo "\n";
echo "Example session:\n";
echo "run A=eye(3)\n";
echo "run B=[1,2,3;4,5,6;7,8,9]\n";
echo "query A+2*B\n";
echo "quit\n";
echo "\n";

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
