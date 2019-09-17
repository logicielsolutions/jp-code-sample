<?php
namespace JobProgress\Resources\Events;

class ResourcesMoved
{
	public function __construct($resources, $moveToJob, $moveFromJob)
	{
		$this->resources   = $resources;
		$this->moveToJob   = $moveToJob;
		$this->moveFromJob = $moveFromJob;
	}
}