<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;
use jubianchi\jubot\Travis\Client;

class Logger extends AbstractPlugin
{
	private $config;

	public function getName()
	{
		return 'logger';
	}

	public function init()
	{
		$self = $this;

		$this->bot->onChannel(
			'/.*/',
			function(Event $event) use($self) {
				if(in_array($event->getRequest()->getSource(), $self->getConfig('channels'))) {
					$log = sprintf(
						"%s\t%-20s\t%s" . PHP_EOL,
						date('d/m/Y H:i:s'),
						$event->getRequest()->getSendingUser(),
						$event->getRequest()->getMessage()
					);
					$file = $self->getConfig('path') . DIRECTORY_SEPARATOR . trim($event->getRequest()->getSource(), '#');

					$handle = fopen($file, 'a+');
					fwrite($handle, $log);
					fclose($handle);
				}
			}
		);
	}

	public function getConfig($name) {
		if(null === $this->config) {
			$config = $this->bot->getConfig();
			$this->config = $config['logger'];

			if(false === is_dir($this->getConfig('path'))) {
				mkdir($this->getConfig('path'), 0777, true);
			}
		}

		return $this->config[$name];
	}
}
