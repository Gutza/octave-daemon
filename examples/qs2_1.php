<?php

require "Octave_lib.php";

$octave=new Octave(false);
$octave->run("A=1");
$octave->run("B=2");
echo "Result=".$octave->query("A+B");
// Result=3

