<?php

require dirname(dirname(__FILE__))."/include/classes/Octave_controller.php";

$c=new Octave_controller();
$c->init();

$pid=pcntl_fork();
if ($pid==-1) {
	die("Could not fork!");
} elseif ($pid) {
	// wait for child
	pcntl_wait($status);
	echo "Child is done\n";
} else {
	// The child
	$c->hangingProcess=true;
}

echo "5+5=".$c->query("5+5")."\n";

//echo "asd=".$c->query("asd");

echo "\n------HERE--------\n";

flush();
exit;
echo $c->query("100-33");

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

echo "Finally...\n";
echo count(explode("\n",$c->query("rand(3,1)")))."\n";

echo "\n--END--\n";
