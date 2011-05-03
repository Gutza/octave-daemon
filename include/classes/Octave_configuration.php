<?php

/**
* Octave-daemon -- a network daemon for Octave, written in PHP
*
* Copyright (C) 2011 Bogdan Stancescu <bogdan@moongate.ro>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see {@link http://www.gnu.org/licenses/}.
*
* @package octave-daemon
* @subpackage server
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* This class extracts data from the configuration file.
*
* @package octave-daemon
* @subpackage server
*/
class Octave_configuration extends Octave_IP_processor
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
				"constraints"=>array(),
			),
			"home_directory"=>array(
				"constraints"=>array("mandatory"),
			),
			"run_as"=>array(
				"constraints"=>array(),
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
				"default"=>"127.0.0.1",
				// too atypical; this is tested separately
				"constraints"=>array(),
			),
		),
	);

	public function __construct($filename)
	{
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
			if (!$this->processIPs($this->servers[$idx]['allowed_ip']))
				return false;

		return true;
	}

	protected function processIPs(&$ranges)
	{
		$list=explode(",",$ranges);
		$final=array();
		foreach($list as $ip)  {
			$range=new Octave_IP_range(trim($ip));
			if (!$range->init()) {
				$this->lastError=$range->lastError;
				return false;
			}
			$final[]=$range;
		}
		$ranges=$final;
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
				return $this->testIP($val);
			default:
				throw new RuntimeException("Unknown constraint type: ".$constraint);
		}
	}
}

