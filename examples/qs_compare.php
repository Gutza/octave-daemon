<?php

require "Octave_lib.php";

$instances=array(
	'Ad hoc'=>new Octave(false),
	'Network'=>new Octave("localhost"),
);

$tests=array(
	'Initialization ($octave->init() x 1)'=>'test_init',
	'Single command ($octave->run("A=1") x 1)'=>'test_single',
	'Multiple commands ($octave->run("A=1") x 50)'=>'test_multiple',
	'Single batch of multiple commands ($octave->run("A=1" x 50))'=>'test_batch',
	'Long input ($octave->run("C=[...]"), C=eye(500))'=>'test_long_in',
	'Long output ($octave->query("C"))'=>'test_long_out',
);

foreach($tests as $testName=>$testFunction) {
	echo "\nTest: ".$testName."\n";
	foreach($instances as $instanceName=>$instance)
		runTest($testFunction,$instanceName,$instance);
}

function runTest($func,$name,$octave)
{
	$func($octave);
	echo $name.str_repeat(" ",10-strlen($name)).toc()."\n";
}

function test_init($octave)
{
	tic();
	$octave->init();
}

function test_single($octave)
{
	tic();
	$octave->run("A=1");
}

function test_multiple($octave)
{
	tic();
	for($i=0;$i<50;$i++)
		$octave->run("A=1");
}

function test_batch($octave)
{
	$command=str_repeat("A=1; ",50);
	tic();
	$octave->run($command);
}

function test_long_in($octave)
{
	$data="";
	for($i=0;$i<500;$i++) {
		for($j=0;$j<500;$j++) {
			$data.=(int) ($i==$j).",";
		}
		$data=substr($data,0,-1).";";
	}
	$data="[".substr($data,0,-1)."]";

	tic();
	$octave->run("C=".$data);
}

function test_long_out($octave)
{
	tic();
	$octave->query("C");
}

function tic()
{
	global $ticTime;
	$ticTime=microtime(true);
}

function toc()
{
	global $ticTime;
	return number_format(microtime(true)-$ticTime,8)." seconds";
}

/*
Test: Initialization ($octave->init() x 1)
Ad hoc    1.04864597 seconds
Network   0.00218010 seconds

Test: Single command ($octave->run("A=1") x 1)
Ad hoc    0.00361109 seconds
Network   0.04600000 seconds

Test: Multiple commands ($octave->run("A=1") x 50)
Ad hoc    0.14993691 seconds
Network   2.20994687 seconds

Test: Single batch of multiple commands ($octave->run("A=1" x 50))
Ad hoc    0.00485492 seconds
Network   0.04709888 seconds

Test: Long input ($octave->run("C=[...]"), C=eye(500))
Ad hoc    0.88836408 seconds
Network   0.85259986 seconds

Test: Long output ($octave->query("C"))
Ad hoc    0.60597301 seconds
Network   0.50299597 seconds
*/
