<?php

require dirname(dirname(__FILE__))."/include/classes/Octave_controller.php";

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
		$result=$this->octave->runRead("disp(5+5)");
		$this->assertEquals($result,"10");
	}

	public function testRunReadWarning()
	{
		$this->octave->quiet=true;
		$this->assertEquals($this->octave->runRead("disp(1/0)"),"inf");
		$this->octave->quiet=false;
		$this->assertEquals($this->octave->lastError,"warning: division by zero");
	}

	public function testRunReadError()
	{
		$this->octave->quiet=true;
		$this->assertEmpty($this->octave->runRead("qwerty"));
		$this->octave->quiet=false;
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",$this->octave->lastError);
	}

	public function testQueryArithmetic()
	{
		$this->assertEquals($this->octave->query("5+5"),"10");
	}

	public function testQueryWarning()
	{
		$this->octave->quiet=true;
		$this->assertEquals($this->octave->query("1/0"),"inf");
		$this->octave->quiet=false;
		$this->assertEquals($this->octave->lastError,"warning: division by zero");
	}

	public function testQueryError()
	{
		$this->octave->quiet=true;
		$this->assertEmpty($this->octave->query("qwerty"));
		$this->octave->quiet=false;
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",$this->octave->lastError);
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

		// This is not a proper query, since it doesn't provide a regular answer
		$tictoc=$this->octave->runRead("tic(); for i=1:10000 lg_factorial6( 10 ); end; toc()");

		$this->assertStringStartsWith("Elapsed time is",$tictoc);
		$this->assertEmpty($this->octave->lastError);
	}

	public function testLong()
	{
		$rowCount=10000;

		$this->assertEquals(
			$rowCount,
			count(explode(
				"\n",
				$this->octave->query("rand(".$rowCount.",1)")
			))
		);
	}
}