<?php

require dirname(dirname(__FILE__))."/include/Octave_lib.php";

abstract class commonTests extends PHPUnit_Framework_TestCase
{

	public function init()
	{
		$r=new ReflectionClass($this);
		$o=$r->getStaticPropertyValue("octave");
		if ($o->init()===false) {
			$this->assertEquals("",$o->lastError);
			return false;
		}

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
		$result=$octave->runRead("disp(1/0)");
		$lastError=$octave->lastError;
		$octave->quiet=false;

		$this->assertEquals("inf",rtrim($result));
		$this->assertEquals("warning: division by zero",trim($lastError));
	}

	public function testRunReadError()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$result=$octave->runRead("qwerty");
		$lastError=$octave->lastError;
		$octave->quiet=false;

		$this->assertEmpty($result);
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",$lastError);
	}

	public function testQueryArithmetic()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$result=$octave->query("5+5");
		$this->assertEquals("10",rtrim($result));
	}

	public function testQueryWarning()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$result=$octave->query("1/0");
		$lastError=$octave->lastError;
		$octave->quiet=false;
		$this->assertEquals("inf",rtrim($result));
		$this->assertEquals("warning: division by zero",trim($lastError));
	}

	public function testQueryError()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$octave->quiet=true;
		$result=$octave->query("qwerty");
		$lastError=$octave->lastError;
		$octave->quiet=false;
		$this->assertEmpty($result);
		$this->assertStringStartsWith("error: `qwerty' undefined near line ",$lastError);
	}

	public function testSlow()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$funcdef=str_replace("\t"," ",str_replace("\n","","
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
		"));

		$r1=$octave->run($funcdef);
		$err1=$octave->lastError;

		$r2=$octave->runRead("who -functions");

		// This is not a proper query, since it doesn't provide a regular answer
		$r3=$octave->runRead("tic(); for i=1:10000 lg_factorial6( 10 ); end; toc()");
		$err3=$octave->lastError;

		$this->assertTrue((bool) $r1,"Failed defining function ($r1):\n".$funcdef."\n");
		$this->assertEquals("",$err1);

		$this->assertRegExp("/lg_factorial6/",$r2,"Function lg_factorial6 not defined: ".$r2);

		$this->assertEquals("",$err3);
		$this->assertStringStartsWith("Elapsed time is",$r3);
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

		$this->assertTrue((bool)$result,$octave->lastError);

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

		$r1=$octave->run("A=eye(3); B=eye(4);").$octave->run("A*B");
		$err1=$octave->lastError;

		$r2=$octave->query("1+1");
		$err2=$octave->lastError;

		$r3=$octave->query("1/0");
		$err3=$octave->lastError;

		$r4=$octave->query("10-7");
		$err4=$octave->lastError;

		$octave->quiet=false;

		$this->assertStringStartsWith("error: operator *: nonconformant arguments",$err1,"Actual error:\n".$err1);

		$this->assertEquals("2",rtrim($r2));
		$this->assertEmpty($err2);

		$this->assertEquals("inf",rtrim($r3));
		$this->assertEquals("warning: division by zero",trim($err3));

		$this->assertEquals("3",rtrim($r4));
		$this->assertEmpty($err4);
	}

}
