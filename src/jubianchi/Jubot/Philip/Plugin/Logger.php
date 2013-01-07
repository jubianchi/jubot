<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

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

		$this->bot->onChannel('/.*/', $this->getLoggerCallback());

		$this->bot->onChannel(
			'/^!log (?:(?P<since>[a-zA-Z0-9][a-zA-Z0-9 +\-]*)|)(?:\.\.(?P<until>[a-zA-Z0-9][a-zA-Z0-9 +\-]*?))?(?: -(?:f |-from=)(?P<from>[a-zA-Z][a-zA-Z0-9\-\[\]\\`\^{}]*)|$)/',
			function(Event $event) use($self) {
				$matches = $event->getMatches();
				$file = $self->getConfig('path') . DIRECTORY_SEPARATOR . trim($event->getRequest()->getSource(), '#');

				var_dump($matches);

				try {
					$since = new \DateTime(isset($matches['since']) ? $matches['since'] : '1 hour ago');
					$until = new \DateTime(isset($matches['until']) ? $matches['until'] : 'now');
					$from = isset($matches['from']) ? trim($matches['from']) : null;

					if(file_exists($file)) {
						foreach(file($file) as $line) {
							$parts = explode("\t", $line);

							try {
								$date = new \DateTime($parts[0]);

								if(
									$date >= $since && $date < $until &&
									(null === $from || $event->getRequest()->getSendingUser() === $from)
								) {
									$event->addResponse(
										Response::msg(
											$event->getRequest()->getSendingUser(),
											sprintf(
												'[%s]  <%s>  %s',
												$date->format('d/m/Y H:i:s'),
												trim($parts[1]),
												trim($parts[2])
											)
										)
									);
								}
							} catch(\Exception $exception) {}
						}
					}
				} catch(\Exception $exception) {}

			}
		);
	}

	protected function getLoggerCallback()
	{
		$self = $this;

		return function(Event $event) use($self) {
			if(in_array($event->getRequest()->getSource(), $self->getConfig('channels'))) {
				$log = sprintf(
					"%s\t%-20s\t%s" . PHP_EOL,
					date('m/d/Y H:i:s'),
					$event->getRequest()->getSendingUser(),
					$event->getRequest()->getMessage()
				);
				$file = $self->getConfig('path') . DIRECTORY_SEPARATOR . trim($event->getRequest()->getSource(), '#');

				$handle = fopen($file, 'a+');
				fwrite($handle, $log);
				fclose($handle);
			}
		};
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

	public function displayHelp(Event $event)
	{
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '* Logger'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !log [[<since>=1 hour ago]..[<until>=now]] [-f|--from=<from>]'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
	}
}
