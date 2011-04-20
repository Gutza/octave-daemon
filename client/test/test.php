<?php

require dirname(dirname(__FILE__))."/include/Octave_client_lib.php";

$client=new Octave_client();
if (!$client->init()) {
	echo "Failed initializing client!\nThe error message was \"".$client->lastError."\"\n";
	exit;
}

echo $client->query("eye(3)");
