<?php

require "Octave_lib.php";

$octave=new Octave(false);

$octave->run("A=eye(5)");
$fname=trim($octave->query("tmpnam()"));
$octave->run("csvwrite('$fname',A)");

echo $octave->retrieve($fname);
/*
1,0,0,0,0
0,1,0,0,0
0,0,1,0,0
0,0,0,1,0
0,0,0,0,1
*/

$octave->run("unlink('$fname')");

