<?php

require "Octave_lib.php";

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

$c->run("A=[1,2,3;4,5,6;7,8,9]; B=[9,8,7;6,5,4;3,2,1];");
echo "A=\n".$c->query("A")."\n";
echo "B=\n".$c->query("B")."\n";
echo "A*B=\n".$c->query("A*B")."\n";
/*
A=
 1 2 3
 4 5 6
 7 8 9
B=
 9 8 7
 6 5 4
 3 2 1
A*B=
 30 24 18
 84 69 54
 138 114 90
*/

