<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

abstract class commonTests extends PHPUnit_Framework_TestCase
{

	public function init()
	{
		$r=new ReflectionClass($this);
		$o=$r->getStaticPropertyValue("octave");
		if ($o->init()===false)
			return false;

		return $o;
	}

	public function testRunReadArithmetic()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$result=$octave->runRead("disp(5+5)");
		$this->assertEquals("10",rtrim($result));
	}

	public function testRunReadWarning()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$this->assertEquals("inf",rtrim($octave->runRead("disp(1/0)")));
		$octave->quiet=false;
		$this->assertEquals("warning: division by zero",trim($octave->lastError));
	}

	public function testRunReadError()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$this->assertEmpty($octave->runRead("qwerty"));
		$octave->quiet=false;
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",$octave->lastError);
	}

	public function testQueryArithmetic()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$this->assertEquals("10",rtrim($octave->query("5+5")));
	}

	public function testQueryWarning()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$this->assertEquals("inf",rtrim($octave->query("1/0")));
		$octave->quiet=false;
		$this->assertEquals("warning: division by zero",trim($octave->lastError));
	}

	public function testQueryError()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$this->assertEmpty($octave->query("qwerty"));
		$octave->quiet=false;
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",$octave->lastError);
	}

	public function testSlow()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->run("
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
		$tictoc=$octave->runRead("tic(); for i=1:10000 lg_factorial6( 10 ); end; toc()");

		$this->assertStringStartsWith("Elapsed time is",$tictoc);
		$this->assertEmpty($octave->lastError);
	}

	public function testLargeReadWrite()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
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

		$result=trim($octave->query($query));
		$this->assertTrue((bool)$result);

		$lines=explode("\n",$result);
		$this->assertEquals($size,count($lines));

		$this->assertEquals($size,count(explode(" ",trim($lines[$size-1]))));
	}

	public function testSequential()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;

		$octave->run("A=eye(3)");
		$octave->run("B=eye(4)");
		$octave->run("A*B");
		$this->assertStringStartsWith("error: operator *: nonconformant arguments",$octave->lastError);

		$this->assertEquals("2",rtrim($octave->query("1+1")));
		$this->assertEmpty($octave->lastError);

		$this->assertEquals("inf",rtrim($octave->query("1/0")));
		$this->assertEquals("warning: division by zero",trim($octave->lastError));

		$this->assertEquals("3",rtrim($octave->query("10-7")));
		$this->assertEmpty($octave->lastError);
	}

}
