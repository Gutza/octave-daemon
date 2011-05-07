<?php

require "Octave_lib.php";

$o=new Octave("localhost");
$o->init();

$time=microtime(true);
$result=$o->query("eye(700)");
echo "Time: ".number_format(microtime(true)-$time,8)." seconds\n";
echo "Size: ".strlen($result)." bytes\n";
/*
Time: 0.99173093 seconds
Size: 980701 bytes
*/
