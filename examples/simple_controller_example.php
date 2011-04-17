<?php

/*
* A simple example for using the controller
*/

require "Octave_controller.php";
$c=new Octave_controller();
$c->init();

echo "eye(3):\n";
echo $c->query("eye(3)")."\n";
/*
eye(3):
 1 0 0
 0 1 0
 0 0 1
*/
