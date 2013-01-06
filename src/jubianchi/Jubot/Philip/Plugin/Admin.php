<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class Admin extends AbstractPlugin
{
	public function getName()
	{
		return 'admin';
	}

	public function init()
	{
		$self = $this;

		$this->bot->onPrivateMessage(
			'/^!join (?P<channels>(?:[#&][^\x07\x2C\s]{0,200} ?)*)/',
			function(Event $event) use ($self) {
				$matches = $event->getMatches();

				$user = $event->getRequest()->getSendingUser();
				$rooms = explode(' ', $matches['channels']);

				if ($self->hasCredential($user)) {
					$event->addResponse(Response::join(implode(',', $rooms)));

					foreach ($rooms as $room) {
						$event->addResponse(Response::msg($room, 'Hello ' . ltrim($room, '#') . '!'));
					}
				}
			}
		);

		$this->bot->onPrivateMessage(
			'/^!leave (?P<channels>(?:[#&][^\x07\x2C\s]{0,200} ?)*)/',
			function(Event $event) use ($self) {
				$matches = $event->getMatches();
				$user = $event->getRequest()->getSendingUser();
				$rooms = explode(' ', $matches['channels']);

				if ($self->hasCredential($user)) {
					$event->addResponse(Response::leave(implode(',', $rooms)));
				}
			}
		);

		$this->bot->onPrivateMessage(
			'/^!say (?P<channel>[#&]?[^\x07\x2C\s]{0,200}) (?P<message>.+)/',
			function(Event $event) use ($self) {
				$matches = $event->getMatches();
				$user = $event->getRequest()->getSendingUser();

				if ($self->hasCredential($user)) {
					$event->addResponse(Response::msg($matches['channel'], $matches['message']));
				}
			}
		);

		$this->bot->onPrivateMessage(
			'/^!quit(?: (?P<message>.*)|)/',
			function(Event $event) use ($self) {
				$matches = $event->getMatches();
				$user = $event->getRequest()->getSendingUser();
				$message = isset($matches['message']) ? $matches['message'] : 'Later, kids.';

				if ($self->hasCredential($user)) {
					$event->addResponse(Response::quit($message));
				}
			}
		);
	}

	public function displayHelp(Event $event)
	{
		if ($this->hasCredential($event->getRequest()->getSendingUser())) {
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '* Admin'));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !quit <message>'));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !join <#channel[ #channel]>'));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !leave <#channel[ #channel]>'));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !say <#channel|nick> <message>'));
			$event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
		}
	}

	public function hasCredential($user)
	{
		return ($this->bot->isAdmin($user) && $this->bot->getPlugin('auth')->isLoggedIn($user));
	}
}
