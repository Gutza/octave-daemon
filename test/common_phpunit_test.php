<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

class commonTests extends PHPUnit_Framework_TestCase
{
	static $octave=NULL;

	function __construct()
	{
		return self::$octave===NULL;
	}

	public function testRunReadArithmetic()
	{
		$result=self::$octave->runRead("disp(5+5)");
		$this->assertEquals("10",rtrim($result));
	}

	public function testRunReadWarning()
	{
		self::$octave->quiet=true;
		$this->assertEquals("inf",rtrim(self::$octave->runRead("disp(1/0)")));
		self::$octave->quiet=false;
		$this->assertEquals("warning: division by zero",trim(self::$octave->lastError));
	}

	public function testRunReadError()
	{
		self::$octave->quiet=true;
		$this->assertEmpty(self::$octave->runRead("qwerty"));
		self::$octave->quiet=false;
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",self::$octave->lastError);
	}

	public function testQueryArithmetic()
	{
		$this->assertEquals("10",rtrim(self::$octave->query("5+5")));
	}

	public function testQueryWarning()
	{
		self::$octave->quiet=true;
		$this->assertEquals("inf",rtrim(self::$octave->query("1/0")));
		self::$octave->quiet=false;
		$this->assertEquals("warning: division by zero",trim(self::$octave->lastError));
	}

	public function testQueryError()
	{
		self::$octave->quiet=true;
		$this->assertEmpty(self::$octave->query("qwerty"));
		self::$octave->quiet=false;
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",self::$octave->lastError);
	}

	public function testSlow()
	{
		self::$octave->run("
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
		$tictoc=self::$octave->runRead("tic(); for i=1:10000 lg_factorial6( 10 ); end; toc()");

		$this->assertStringStartsWith("Elapsed time is",$tictoc);
		$this->assertEmpty(self::$octave->lastError);
	}

	public function testLargeReadWrite()
	{
		$size=1000; // Mind you, this is $size * $size cells (1M cells, ~2M bytes for this setup)

		$query="[".
			substr(
				str_repeat(
					substr(
						str_repeat("1,",$size),
						0,-1
					).";",
					$size
				),
				0,-1
			)."]";

		$result=trim(self::$octave->query($query));
		$this->assertTrue((bool)$result);

		$lines=explode("\n",$result);
		$this->assertEquals($size,count($lines));

		$this->assertEquals($size,count(explode(" ",trim($lines[$size-1]))));
	}

	public function testSequential()
	{
		self::$octave->quiet=true;

		self::$octave->run("A=eye(3)");
		self::$octave->run("B=eye(4)");
		self::$octave->run("A*B");
		$this->assertStringStartsWith("error: operator *: nonconformant arguments",self::$octave->lastError);

		$this->assertEquals("2",rtrim(self::$octave->query("1+1")));
		$this->assertEmpty(self::$octave->lastError);

		$this->assertEquals("inf",rtrim(self::$octave->query("1/0")));
		$this->assertEquals("warning: division by zero",trim(self::$octave->lastError));

		$this->assertEquals("3",rtrim(self::$octave->query("10-7")));
		$this->assertEmpty(self::$octave->lastError);
	}

}
