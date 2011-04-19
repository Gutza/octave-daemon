<?php

require dirname(dirname(__FILE__))."/include/classes/Octave_controller.php";

$c=new Octave_controller();
$c->allowPartial=true;
$c->init();

$result=$c->query("eye(100)");
echo $result." -- ";
while($c->partialResult) {
	usleep(500);
	$result=$c->more();
	echo $result['stdout']." == ";
}

echo "Octave version: {{".$c->octave_version."}}\n";
