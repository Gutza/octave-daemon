<?php

require "Octave_lib.php";

$octave=new Octave(false);
$octave->run("tic()");
usleep(500);

echo "Raw output: ".$octave->query("toc()");
// Raw output: 0.00391603

echo "Octave output: ".$octave->runRead("toc()");
// Octave output: Elapsed time is 0.00703096 seconds.
