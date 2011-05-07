<?php

require "Octave_lib.php";

$o=new Octave("localhost");
$o->init();

$time=microtime(true);
$o->registerPartialHandler("process_partial");
$result="";
$count=0;

$o->query("eye(700)");

echo "Time: ".number_format(microtime(true)-$time,8)." seconds\n";
echo "Size: ".strlen($result)." bytes\n";
echo "Number of partial results: ".$count."\n";

function process_partial($payload,$partial)
{
	global $result,$count;
	$count++;
	$result.=$payload;
}

/*
Time: 0.99880290 seconds
Size: 980701 bytes
Number of partial results: 45
*/

