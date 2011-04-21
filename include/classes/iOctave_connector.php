<?php

interface iOctave_connector
{
	public function run($command);
	public function runRead($command);
	public function query($command);
	public function retrieve($filename);
}
