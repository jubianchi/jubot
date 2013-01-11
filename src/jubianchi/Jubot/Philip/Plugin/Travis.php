<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;
use jubianchi\Jubot\Travis\Client;

class Travis extends AbstractPlugin
{
	private $client;

	const EXP_PREFIX = '^!travis ';
	const EXP_REPO   = '(?P<username>[^ ]+)\/(?P<repository>[^ ]+)';

	public function getName()
	{
		return 'travis';
	}

	public function init()
	{
		$self = $this;

		$pattern = '/' . static::EXP_PREFIX . 'status ' . static::EXP_REPO . '/';
		$callback = function(Event $event) use($self) {
			$matches = $event->getMatches();
			$username = $matches['username'];
			$repository = $matches['repository'];

			$repo = $self->getClient()->api('builds')->all($username, $repository);
			$build = current($repo);
			$build = $self->getClient()->api('builds')->show($username, $repository, $build['id']);

			$event->addResponse(Response::msg($event->getRequest()->getSource(), '#' . $build['number'] . ' ' . $username . '/' . $repository));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), $build['started_at'] . ' / ' . $build['finished_at']));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), $build['state'] . (isset($build['status']) ? ' - ' . ((int) $build['status'] === 0 ? 'stable' : 'unstable') : '')));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), sprintf('https://travis-ci.org/%s/%s/builds/%d', $username, $repository, $build['id'])));

		};

		$this->bot->onChannel($pattern, $callback);
		$this->bot->onPrivateMessage($pattern, $callback);
	}

	public function getClient() {
		if(null === $this->client) {
			$this->client = new Client();
		}

		return $this->client;
	}

	public function displayHelp(Event $event)
	{
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '* Travis'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !travis status <username>/<repository>'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
	}
}
