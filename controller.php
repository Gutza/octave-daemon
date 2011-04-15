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

	}

	function send($stuff)
	{
		fputs($this->stdin,$stuff);
	}

	function retrieve($socket,$mandatory=false)
	{
		if ($mandatory) {
			stream_set_blocking($socket,true);
			$result=fgets($socket);
			stream_set_blocking($socket,false);
		} else
			$result="";

		$read=array($socket);
		$write=NULL;
		$except=NULL;

		while(stream_select($read,$write,$except,0,0))
			$result.=fgets($read[0]);

		return $result;
	}

	function read()
	{
		$payload=$this->retrieve($this->stdout,true);
		$this->errors=$this->retrieve($this->stderr);
		return $payload;
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
