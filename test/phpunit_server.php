<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

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
		$this->assertEquals("10",rtrim($result));
	}

	public function testRunReadWarning()
	{
		$this->octave->quiet=true;
		$this->assertEquals("inf",rtrim($this->octave->runRead("disp(1/0)")));
		$this->octave->quiet=false;
		$this->assertEquals("warning: division by zero",trim($this->octave->lastError));
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
		$this->assertEquals("10",rtrim($this->octave->query("5+5")));
	}

	public function testQueryWarning()
	{
		$this->octave->quiet=true;
		$this->assertEquals("inf",rtrim($this->octave->query("1/0")));
		$this->octave->quiet=false;
		$this->assertEquals("warning: division by zero",trim($this->octave->lastError));
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

		$result=trim($this->octave->query($query));
		$this->assertTrue((bool)$result);

		$lines=explode("\n",$result);
		$this->assertEquals($size,count($lines));

		$this->assertEquals($size,count(explode(" ",trim($lines[$size-1]))));
	}

	public function testSequential()
	{
		$this->octave->quiet=true;

		$this->octave->run("A=eye(3)");
		$this->octave->run("B=eye(4)");
		$this->octave->run("A*B");
		$this->assertStringStartsWith("error: operator *: nonconformant arguments",$this->octave->lastError);

		$this->assertEquals("2",rtrim($this->octave->query("1+1")));
		$this->assertEmpty($this->octave->lastError);

		$this->assertEquals("inf",rtrim($this->octave->query("1/0")));
		$this->assertEquals("warning: division by zero",trim($this->octave->lastError));

		$this->assertEquals("3",rtrim($this->octave->query("10-7")));
		$this->assertEmpty($this->octave->lastError);
	}

	public function testPartial()
	{
		$size=1000; // Again, matrix size = $size * $size

		$this->octave->allowPartial=true;
		$r=$e=""; // results and errors
		$rc=1; // read count
		$r=$this->octave->query("eye(".$size.")");
		$e=$this->octave->lastError;
		while($this->octave->partialResult) {
			$more=$this->octave->more();
			$r.=$more;
			$e.=$this->octave->lastError;
			$rc++;
		}

		$this->assertEquals($size,count(explode("\n",rtrim($r))));
		$this->assertEmpty($e);
		$this->assertGreaterThan(1,$rc);
	}
}
