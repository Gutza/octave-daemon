<?php

require dirname(dirname(__FILE__))."/include/Octave_client_lib.php";

$client=new Octave_client();
if (!$client->init()) {
	echo "Failed initializing client!\nThe error message was \"".$client->lastError."\"\n";
	exit;
}

$start=microtime(true);
$result=trim($client->query("eye(1000)"));
echo count(explode("\n",$result));

//echo $client->query("sum(sum(rand(1000)))");

$end=microtime(true);

echo "Total query time: ".number_format($end-$start,3)." seconds.\n";
