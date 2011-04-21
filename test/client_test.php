<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

$client=new Octave_client();
if (!$client->init()) {
	echo "Failed initializing client!\nThe error message was \"".$client->lastError."\"\n";
	exit;
}

$fp=fopen("delme.file",'w');
$client->registerPartialHandler("ph");
$client->retrieve("/home/bogdan/delme/octave.mat");
fclose($fp);

function ph($content,$partial)
{
	global $fp;
	fputs($fp,$content);
}

echo "Errors: [".$client->lastError."]\n";

return;

$lines=10000;
$columns=100;

$start=microtime(true);
$result=trim($client->query("rand($lines,$columns)"));

$xploded=explode("\n",$result);
$count=count($xploded);
echo "Received ".number_format(strlen($result)/1024/1024,2)." MiB\n";

for($lid=0;$lid<$lines;$lid++) {
	if (!isset($xploded[$lid])) {
		echo "Line ".($lid+1)." is missing altogether!\n";
		continue;
	}
	$line=explode(" ",trim($xploded[$lid]));
	if (count($line)!=$columns) {
		for($i=$lid-1;$i<$lid+2;$i++)
			echo "Line ".($i+1).": ".$xploded[$i]."\n";
	}
}
if ($count==$lines)
	echo "Line count ok.\n";

for($lid=$lines;$lid<$count;$lid++)
	echo "Extra line ".($lid+1).": ".$xploded[$lid]."\n";

//echo $result;

//echo $client->query("sum(sum(rand($lines)))");

$end=microtime(true);

echo "Total query time: ".number_format($end-$start,3)." seconds.\n";
