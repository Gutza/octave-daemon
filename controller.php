<?php

class Octave_controller
{

	var $octave_path="";
	var $octave_binary="octave";
	var $stdin;
	var $stdout;
	var $stderr;
	var $process;

	function __construct()
	{
	}

	function init()
	{
		$this->process=proc_open(
			$this->octave_path.$this->octave_binary,
			array(
				0 => array("pipe", "r"),
				1 => array("pipe", "w"),
				2 => array("pipe", "r")
			),
			$pipes
		);
		list($this->stdin,$this->stdout,$this->stderr)=$pipes;
		stream_set_blocking($this->stdout,false);

	}

	function send($stuff)
	{
		fputs($this->stdin,$stuff);
	}

	function retrieve($socket)
	{
		$read=array($socket);
		$write=NULL;
		$except=NULL;

		$result="";
		while(stream_select($read,$write,$except,0,100000))
			$result.=fgets($read[0]);

		return $result;
	}

	function read()
	{
		$this->errors=$this->retrieve($this->stderr);
		return $this->retrieve($this->stdout);
	}

}

$c=new Octave_controller();
$c->init();
echo $c->read();

$pid=pcntl_fork();
if ($pid==-1) {
	die("Could not fork!");
} elseif ($pid) {
	// wait for child
	pcntl_wait($status);
	echo "Child is done\n";
}


$c->send("5+5\n");

echo $c->read();

echo "--clean exit--\n";
