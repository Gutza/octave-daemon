<?php

class Octave_IP_range extends Octave_IP_processor
{

	protected $initialized=false;
	public $rawRange="";
	public $lastError="";
	protected $baseIP="";
	protected $bitCount=32;

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
		$this->baseIP=$xploded[0];

		if (isset($xploded[1]) && !$this->processMask($xploded[1]))
			return false;

		return $this->initialized=true;
	}

	function processMask($mask)
	{
		if (strstr($this->baseIP,":")) {
			$this->lastError="IPv6 ranges currently not supported.";
			return false;
		}

		if (
			!is_numeric($mask) ||
			$mask<0 ||
			$mask>32
		) {
			$this->lastError="Unknown bit count: ".$mask;
			return false;
		}

		$this->bitCount=$mask;
		return true;
	}

	function is_allowed($ip)
	{
		if ($ip==$this->baseIP)
			return true;

		$mask=bindec(str_repeat("1",$this->bitCount));
		return $this->mask($ip,$mask)==$this->mask($this->baseIP,$mask);
	}

	function mask($ip,$mask)
	{
		$numeric=0;
		$xploded=explode(".",$ip);
		foreach($xploded as $bid=>$bdata)
			$numeric+=$bdata<<(8*(3-$bid));
		return dechex($numeric) & $mask;
	}
}
