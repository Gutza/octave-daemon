<?php

require "controller.php";

class unitTest extends PHPUnit_Framework_TestCase
{
	function __construct()
	{
		$this->octave=new Octave_controller();
		$this->octave->init();
	}
	/**
	* @expectedException RuntimeException
	*/
	public function testException()
	{
		$c=new Octave_controller();
		$c->octave_binary='foo';
		$c->init();
	}

	public function testSimple()
	{
		$result=$this->octave->execRead("5+5");
		$this->assertEquals(trim($result),"ans =  10");
	}

	public function testWarning()
	{
		$this->octave->quiet=true;
		$this->octave->execRead("1/0");
		$this->octave->quiet=false;
		$this->assertEquals(trim($this->octave->errors),"warning: division by zero");
	}
}
