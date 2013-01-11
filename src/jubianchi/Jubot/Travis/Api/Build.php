<?php
namespace jubianchi\Jubot\Travis\Api;

use Github\Api\AbstractApi;

class Build extends AbstractApi
{
    public function all($username, $repository)
    {
        return $this->get(sprintf('repositories/%s/%s/builds.json', $username, $repository));
    }

    public function show($username, $repository, $id)
    {
        return $this->get(sprintf('repositories/%s/%s/builds/%d.json', $username, $repository, $id));
    }
}
