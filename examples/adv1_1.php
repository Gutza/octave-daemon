<?php

require "Octave_lib.php";

$o=new Octave(false);
$o->init();

$time=microtime(true);
$result=$o->query("eye(700)");
echo "Time: ".number_format(microtime(true)-$time,8)." seconds\n";
echo "Size: ".strlen($result)." bytes\n";
/*
Time: 1.01925111 seconds
Size: 980701 bytes
*/
