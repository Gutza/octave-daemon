<?php

require_once "common_phpunit_test.php";

abstract class wrapperTests extends commonTests
{

	public function testMatrix()
	{
		if (!($octave=$this->init())) {
			self::markTestSkipped();
			return;
		}
		$size=37;
		$this->lock();
		$octave->run("A=eye($size)");
		$matrix=$octave->getMatrix('A');
		$this->unlock();
		$this->assertEquals($size,count($matrix));
		$this->assertEquals($size,count($matrix[0]));
	}

}

