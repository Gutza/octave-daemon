<?php

class Octave_configuration
{

	public $globals=array();
	public $servers=array();
	public $lastError="";

	protected $values=array(
		"globals"=>array(
			"max_instances"=>array(
				"default"=>3,
				"constraints"=>array(
					"numeric",
					"positive",
					"non-zero",
				),
			),
			"pid_file"=>array(
				"constraints"=>array("mandatory"),
			),
			"home_directory"=>array(
				"constraints"=>array("mandatory"),
			),
			"run_as"=>array(
				"constraints"=>array("mandatory"),
			),
		),
		"server"=>array(
			"server_address"=>array(
				"default"=>"127.0.0.1",
				"constraints"=>array(
					"ip_address",
				),
			),
			"server_port"=>array(
				"default"=>43210,
				"constraints"=>array(
					"numeric",
					"positive",
					"non-zero",
				),
			),
			"allowed_ip"=>array(
				// too atypical, this is tested separately
				"constraints"=>array(),
			),
		),
	);

	public function __construct($filename=NULL)
	{
		if ($filename===NULL)
			$filename=dirname(dirname(dirname(__FILE__)))."/octave-daemon.conf";

		$this->lastError="Failed reading configuration file $filename";
		if (
			$this->readConf($filename) &&
			$this->processConf()
		)
			$this->lastError="";
	}

	private function resetConfiguration()
	{
		$this->globals=array();
		$this->servers=array();
	}

	protected function readConf($filename)
	{
		$this->resetConfiguration();

		$fp=@fopen($filename,"r");
		if (!$fp)
			return false;

		$section=NULL;

		while(!feof($fp)) {
			$line=trim(fgets($fp));
			if (!$line || substr($line,0,1)=="#")
				continue;

			if (preg_match("/^\\[([^\\]]+)\\]$/",$line,$sec_name)) {
				unset($section);
				$section=array();
				switch($sec_name[1]) {
					case 'global':
						$this->globals=&$section;
						continue 2;
					case 'server':
						$this->servers[]=&$section;
						continue 2;
					default:
						$this->lastError="Unknown section: [".$sec_name[1]."]";
						return false;
				}
			}
			list($var,$val)=explode("=",$line,2);
			$var=trim($var);
			$val=trim($val);

			$section[$var]=$val;
		}
		return true;
	}

	protected function processConf()
	{
		if (!$this->processSection($this->globals,$this->values['globals']))
			return false;

		foreach($this->servers as $idx=>$server)
			if (!$this->processSection($this->servers[$idx],$this->values['server']))
				return false;

		return true;
	}

	protected function processSection(&$data,$map)
	{
		foreach($data as $var=>$val) {
			if (!isset($map[$var])) {
				$this->lastError="Unknown variable: ".$var;
				return false;
			}
			foreach($map[$var]['constraints'] as $constraint)
				if (!$this->processConstraint($val,$constraint)) {
					$this->lastError="Variable $var breaks constraint \"$constraint\"";
					return false;
				}
		}
		foreach($map as $var=>$info) {
			if (isset($data[$var]))
				continue;
			if (in_array("mandatory",$info['constraints'])) {
				$this->lastError="Variable ".$var." is mandatory!";
				return false;
			}
			if (isset($info['default']))
				$data[$var]=$info['default'];
		}
		return true;
	}

	function processConstraint($val,$constraint)
	{
		switch($constraint) {
			case "mandatory":
				return true;
			case "numeric":
				return is_numeric($val);
			case "positive":
				return $val>0;
			case "non-zero":
				return $val!=0;
			case "ip_address":
				return
					preg_match("/^([0-9]{1,3}\\.){3}[0-9]{1,3}$/",$val) ||
					preg_match("/^([0-9a-f]{4}:){1,7}[0-9a-f]{4}$/",$val);
			default:
				throw new RuntimeException("Unknown constraint type: ".$constraint);
		}
	}
}

