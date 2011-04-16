<?php

require "controller.php";

$c=new Octave_controller();
$c->init();

$pid=pcntl_fork();
if ($pid==-1) {
	die("Could not fork!");
} elseif ($pid) {
	// wait for child
	pcntl_wait($status);
	echo "Child is done\n";
}

echo $c->runRead("5+5");

echo $c->runRead("asd");
