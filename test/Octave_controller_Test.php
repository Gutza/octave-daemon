<?php

require_once "common_phpunit_test.php";

class controllerTest extends commonTests
{
	public static $octave=NULL;
	var $partialContent="";
	var $partialCount=0;

	function __construct()
	{
		if (isset(self::$octave))
			return;

		self::$octave=new Octave_controller();
		self::$octave->hangingProcess=true;
		self::$octave->init();
	}

	public function testNoBinary()
	{
		$c=new Octave_controller();
		$nonfile=tempnam("/tmp","octave_");
		unlink($nonfile);
		$c->octave_binary=$nonfile;
		$c->init();
		$this->assertStringStartsWith("Failed starting the Octave process",$c->lastError);
	}

	public function testPartialReturn()
	{
		$size=1000; // Again, matrix size = $size * $size

		self::$octave->allowPartial=true;
		$r=$e=""; // results and errors
		$rc=1; // read count
		$r=self::$octave->query("eye(".$size.")");
		$e=self::$octave->lastError;
		while(self::$octave->partialResult) {
			$more=self::$octave->more();
			$r.=$more;
			$e.=self::$octave->lastError;
			$rc++;
		}

		$this->assertEquals($size,count(explode("\n",rtrim($r))));
		$this->assertEmpty($e);
		$this->assertGreaterThan(1,$rc);
		self::$octave->allowPartial=false;
	}

	public function testPartialProcess()
	{
		$size=1000; // Again, matrix size = $size * $size

		self::$octave->registerPartialHandler(array($this,"partialProcessor"));
		$e=""; // errors
		self::$octave->query("eye(".$size.")");
		$e=self::$octave->lastError;

		$this->assertEquals($size+1,count(explode("\n",$this->partialContent)));
		$this->assertEmpty($e);
		$this->assertGreaterThan(1,$this->partialCount);

		self::$octave->registerPartialHandler();
	}

	public function partialProcessor($payload,$partial)
	{
		$this->partialCount++;
		$this->partialContent.=$payload;
		if ($partial)
			return;

		$this->partialContent=rtrim($this->partialContent)."\nfoobar";
	}
}
