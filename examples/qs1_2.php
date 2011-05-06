<?php

require "Octave_lib.php";

$octave=new Octave("127.0.0.1");
echo "Result=".$octave->query("1+2");
// Result=3
