<?php

class Octave_IP_range extends Octave_IP_processor
{

	protected $initialized=false;
	public $rawRange="";
	public $lastError="";

	function __construct($range)
	{
		$this->rawRange=$range;
	}

	function init()
	{
		if ($this->initialized)
			return NULL;

		$xploded=explode("/",$this->rawRange);
		if (count($xploded)>2) {
			$this->lastError("Unknown IP range: ".$ip);
			return false;
		}
		if (!$this->testIP($xploded[0])) {
			$this->lastError="Unknown IP format: ".$xploded[0];
			return false;
		}
		if (
			isset($xploded[1]) && (
				!is_numeric($xploded[1]) ||
				$xploded[1]<0 ||
				$xploded[1]>32
			)
		) {
			$this->lastError="Unknown bit count: ".$xploded[1];
			return false;
		}
		$this->baseIP=$xploded[0];
		if (isset($xploded[1]))
			$this->bitCount=$xploded[1];
		return $this->initialized=true;
	}
}
