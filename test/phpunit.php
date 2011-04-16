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
	public function testNoBinary()
	{
		$c=new Octave_controller();
		$nonfile=tempnam("/tmp","octave_");
		unlink($nonfile);
		$c->octave_binary=$nonfile;
		$c->init();
	}

	public function testRunReadArithmetic()
	{
		$result=$this->octave->runRead("5+5");
		$this->assertEquals(trim($result),"ans =  10");
	}

	public function testRunReadWarning()
	{
		$this->octave->quiet=true;
		$this->assertEquals(trim($this->octave->runRead("1/0")),"ans = Inf");
		$this->octave->quiet=false;
		$this->assertEquals(trim($this->octave->errors),"warning: division by zero");
	}

	public function testRunReadError()
	{
		$this->octave->quiet=true;
		$this->assertEmpty(trim($this->octave->runRead("qwerty")));
		$this->octave->quiet=false;
		$this->assertEquals(trim($this->octave->errors),"error: `qwerty' undefined near line 1 column 1");
	}

	public function testQueryArithmetic()
	{
		$this->assertEquals($this->octave->query("5+5"),"10");
	}

	public function testQueryWarning()
	{
		$this->octave->quiet=true;
		$this->assertEquals($this->octave->query("1/0"),"Inf");
		$this->octave->quiet=false;
		$this->assertEquals(trim($this->octave->errors),"warning: division by zero");
	}

	public function testQueryError()
	{
		$this->octave->quiet=true;
		$this->assertEmpty(trim($this->octave->query("qwerty")));
		$this->octave->quiet=false;
		$this->assertEquals(trim($this->octave->errors),"error: `qwerty' undefined near line 1 column 1");
	}

	public function testSlow()
	{
		$this->octave->run("
			function answer = lg_factorial6(n)
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

		$tictoc=$this->octave->runRead("tic(); for i=1:10000 lg_factorial6( 10 ); end; toc()");
		$this->assertStringStartsWith("Elapsed time is",$tictoc);
		$this->assertEmpty($this->octave->errors);
	}
}
