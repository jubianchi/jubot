<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class Auth extends AbstractPlugin
{
	private $logged = array();

	public function getName()
	{
		return 'auth';
	}

	public function init()
	{
		$self = $this;

		$this->bot->onPrivateMessage(
			'/^!auth status/',
			function(Event $event) use($self) {
				$user = $event->getRequest()->getSendingUser();

				$event->addResponse(Response::msg($user, sprintf('You are %slogged in', $self->isLoggedIn($user) ? '' : 'not ')));
				$event->stopPropagation();
			}
		);

		$this->bot->onPrivateMessage(
			'/^!(?:auth )?logout/',
			function(Event $event) use($self) {
				$user = $event->getRequest()->getSendingUser();

				if(false === $self->logOut($user)) {
					$event->addResponse(Response::msg($user, 'You are not logged in'));
				} else {
					$event->addResponse(Response::msg($user, 'Successfully logged out'));
				}

				$event->stopPropagation();
			}
		);

		$this->bot->onPrivateMessage(
			'/^!(?:auth )?login (.*)/',
			function(Event $event) use($self) {
				$matches = $event->getMatches();
				$user = $event->getRequest()->getSendingUser();
				$password = trim($matches[0]);

				if(false === $self->logIn($user, $password)) {
					$event->addResponse(Response::msg($user, 'Invalid credentials'));
				} else {
					$event->addResponse(Response::msg($user, 'Successfully logged in'));
				}
			}
		);

		$this->bot->onPart(function(Event $event) use($self) {
			$user = $event->getRequest()->getSendingUser();

			if($self->isLoggedIn($user)) {
				$self->logOut($user);
			}
		});
	}

	public function logIn($user, $password)
	{
		$hash = md5($password);

		if(false === array_key_exists($user, $this->config) || $this->config[$user] !== $hash) {
			return false;
		}

		$this->logged[] = $user;

		return true;
	}

	public function logOut($user)
	{
		if(false == $this->isLoggedIn($user)) {
			return false;
		}

		$key = array_search($user, $this->logged);
		unset($this->logged[$key]);
		$this->logged = array_values($this->logged);

		return true;
	}

	public function isLoggedIn($user)
	{
		return in_array($user, $this->logged);
	}

	public function displayHelp(Event $event)
	{
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '* Authentication'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !auth status'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !login <password>'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !logout'));
		$event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
	}
}
