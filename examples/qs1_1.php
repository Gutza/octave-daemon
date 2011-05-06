<?php

require "Octave_lib.php";

$octave=new Octave(false);
echo "Result=".$octave->query("1+2");
// Result=3
