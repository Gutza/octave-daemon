<?php

require "Octave_lib.php";

$octave=new Octave(false);
$eye_3x3=$octave->getMatrix("eye(3)");
echo "eye(3,3)=";
print_r($eye_3x3);
/*
eye(3,3)=Array
(
    [0] => Array
        (
            [0] => 1
            [1] => 0
            [2] => 0
        )

    [1] => Array
        (
            [0] => 0
            [1] => 1
            [2] => 0
        )

    [2] => Array
        (
            [0] => 0
            [1] => 0
            [2] => 1
        )

)
*/

$eye_1x3=$octave->getMatrix("eye(1,3)");
echo "eye(1,3)=";
print_r($eye_1x3);
/*
eye(1,3)=Array
(
    [0] => Array
        (
            [0] => 1
            [1] => 0
            [2] => 0
        )

)
*/
