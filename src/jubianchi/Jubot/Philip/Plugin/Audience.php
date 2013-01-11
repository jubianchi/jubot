<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class Audience extends AbstractPlugin
{
	private $nicks = array();
	private $records = array();
	private $dates = array();

	public function getName()
	{
		return 'audience';
	}

	protected function record($channel, Event $event)
	{
		if(false === isset($this->records[$channel])) {
			$this->records[$channel] = 0;
		}

		if(false === isset($this->nicks[$channel])) {
			$this->nicks[$channel] = 0;
		}

		if($this->nicks[$channel] > $this->records[$channel]) {
			$this->records[$channel] = $this->nicks[$channel];
			$this->dates[$channel] = date('d/m/Y H:i:s');

			$event->addResponse(
				Response::msg(
					$channel,
					sprintf('New audience record : %d nick(s)', $this->records[$channel])
				)
			);
		}
	}

	public function increment($channel, Event $event, $increment = 1)
	{
		if(false === isset($this->nicks[$channel])) {
			$this->nicks[$channel] = 0;
		}

		$this->nicks[$channel] += $increment;
		$this->record($channel, $event);
	}

	public function decrement($channel, Event $event, $decrement = 1)
	{
		if(false === isset($this->nicks[$channel])) {
			$this->nicks[$channel] = 0;
		}

		$this->nicks[$channel] -= $decrement;
		$this->nicks[$channel] = $this->nicks[$channel] < 0 ? 0 : $this->nicks[$channel];
		$this->record($channel, $event);
	}

	public function displayRecord($channel, Event $event)
	{
		if (isset($this->records[$channel]) && $this->records[$channel] > 0) {
			$event->addResponse(
				Response::msg(
					$channel,
					sprintf(
						'Audience record : %d nick(s) on %s',
						$this->records[$channel],
						$this->dates[$channel]
					)
				)
			);
		} else {
			$event->addResponse(Response::msg($channel, 'No audience record'));
		}
	}

	public function displayCurrent($channel, Event $event)
	{
		if (isset($this->nicks[$channel]) && $this->nicks[$channel] > 0) {
			$event->addResponse(
				Response::msg(
					$channel,
					sprintf(
						'Current audience : %d nick(s)',
						$this->nicks[$channel]
					)
				)
			);
		}
	}

	public function init()
	{
		$self = $this;

		$this->bot->onJoin(function(Event $event) use($self) {
			$chan = $event->getRequest()->getSource();

			$self->increment($chan, $event);
		});

		$this->bot->onPart(function(Event $event) use($self) {
			$chan = $event->getRequest()->getSource();

			$self->decrement($chan, $event);
		});

		$this->bot->onChannel(
			'/^!audience/',
			function(Event $event) use($self) {
				$channel = $event->getRequest()->getSource();
				$self->displayRecord($channel, $event);
				$self->displayCurrent($channel, $event);
			}
		);

		$this->bot->onPrivateMessage(
			'/^!audience add (?P<channel>([#&][^\x07\x2C\s]{0,200})) (?P<audience>\d+)/',
			function(Event $event) use($self) {
				$matches = $event->getMatches();
				$channel = $matches['channel'];
				$audience = $matches['audience'];

				if ($self->hasCredential($event->getRequest()->getSendingUser())) {
					$self->increment($channel, $event, $audience);
				}
			}
		);
	}

	public function displayHelp(Event $event)
	{
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '* Audience'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !audience'));

		if ($this->hasCredential($event->getRequest()->getSendingUser())) {
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !audience add <#channel> <audience>'));
		}

		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
	}

	public function hasCredential($user)
	{
		return ($this->bot->isAdmin($user) && $this->bot->getPlugin('auth')->isLoggedIn($user));
	}
}
