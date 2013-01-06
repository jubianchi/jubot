<?php
namespace jubianchi\Jubot\Console\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Yaml\Yaml;
use Philip\Philip;

class Bot extends BaseCommand
{
	public function __construct($name = null)
	{
		parent::__construct($name ?: 'bot');
	}

	protected function configure()
	{
		parent::configure();

		$this
			->addArgument('config', InputArgument::OPTIONAL, 'Configuration file', 'jubot.yml')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$config = Yaml::parse(file_get_contents($input->getArgument('config')));

		$bot = new Philip($config);
		$bot->loadPlugin('\\jubianchi\\Jubot\\Philip\\Plugin\\Auth');
		$bot->loadPlugin('\\jubianchi\\Jubot\\Philip\\Plugin\\Admin');
		$bot->loadPlugin('\\jubianchi\\Jubot\\Philip\\Plugin\\Github');
		$bot->loadPlugin('\\jubianchi\\Jubot\\Philip\\Plugin\\Travis');

		$bot->run();
	}
}
