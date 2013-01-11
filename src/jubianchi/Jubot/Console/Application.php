<?php
namespace jubianchi\Jubot\Console;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    protected function getDefaultCommands()
    {
        return array_merge(
            parent::getDefaultCommands(),
            array(
                new Command\Bot()
            )
        );
    }
}
