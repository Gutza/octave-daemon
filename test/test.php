<?php

require "controller.php";

$c=new Octave_controller();
$c->init();
/*
$pid=pcntl_fork();
if ($pid==-1) {
	die("Could not fork!");
} elseif ($pid) {
	// wait for child
	pcntl_wait($status);
	echo "Child is done\n";
}
*/

echo $c->runRead("5+5");

echo $c->runRead("asd");

echo "------HERE--------\n";

echo $c->query("100-33");
/*
echo "----LATER---\n";

$c->run("
function answer = lg_factorial6( n )

    answer = 1;
    
    if( n == 0 )
        return;
    else
        for i = 2:n
            answer = answer * i;
        endfor
    endif

endfunction
");

echo $c->runRead("tic(); for i=1:10000 lg_factorial6( 10 ); end; toc()");
*/
echo count(explode("\n",$c->query("rand(3,1)")))."\n";
