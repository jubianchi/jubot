<?php
namespace jubianchi\Jubot\Travis\Api;

use Github\Api\AbstractApi;

class Repository extends AbstractApi
{
	public function search($query, $username = null)
	{
		return $this->get(sprintf(
			'repositories.json?search=%s%s',
			$query,
			$username ? '&owner_name=' . $username : ''
		));
	}

	public function show($username, $repository)
	{
		return $this->get(sprintf('%s/%s.json', $username, $repository));
	}
}
