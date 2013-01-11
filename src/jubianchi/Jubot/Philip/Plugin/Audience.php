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
        if (false === isset($this->records[$channel])) {
            $this->records[$channel] = 0;
        }

        if (false === isset($this->nicks[$channel])) {
            $this->nicks[$channel] = 0;
        }

        if ($this->nicks[$channel] > $this->records[$channel]) {
            $this->records[$channel] = $this->nicks[$channel];
            $this->dates[$channel] = date('d/m/Y H:i:s');

            $event->addResponse(
                Response::msg(
                    $channel,
                    sprintf('New audience record: %d nick(s)', $this->records[$channel])
                )
            );
        }
    }

    public function initialize($channel, Event $event, array $nicks)
    {
        $patterns = $this->getPatterns($channel);

        $botcfg = $this->bot->getConfig();
        $patterns[] = $botcfg['nick'];

        $nicks = array_filter(
            $nicks,
            function($nick) use ($patterns, $channel) {
                $nick = ltrim($nick, '@+');

                foreach ($patterns as $pattern) {
                    if (@preg_match($pattern, $nick) || $nick === $pattern) {
                        return false;
                    }
                }

                return true;
            }
        );

        $this->nicks[$channel] = count($nicks);
        $this->record($channel, $event);
    }

    public function increment($channel, Event $event, $increment = 1)
    {
        if (false === isset($this->nicks[$channel])) {
            $this->nicks[$channel] = 0;
        }

        if ($this->shouldCount($channel, $event)) {
            $this->nicks[$channel] += $increment;
            $this->record($channel, $event);
        }
    }

    public function decrement($channel, Event $event, $decrement = 1)
    {
        if (false === isset($this->nicks[$channel])) {
            $this->nicks[$channel] = 0;
        }

        if ($this->shouldCount($channel, $event)) {
            $this->nicks[$channel] -= $decrement;
            $this->nicks[$channel] = $this->nicks[$channel] < 0 ? 0 : $this->nicks[$channel];
            $this->record($channel, $event);
        }
    }

    protected function getPatterns($channel)
    {
        return isset($this->config['channels'][$channel]) ? $this->config['channels'][$channel] : array();
    }

    protected function shouldCount($channel, Event $event)
    {
        $nick = $event->getRequest()->getSendingUser();
        $nick = ltrim($nick, '@+');

        foreach ($this->getPatterns($channel) as $pattern) {
            if (@preg_match($pattern, $nick) || $nick === $pattern) {
                return false;
            }
        }

        return true;
    }

    public function displayRecord($channel, Event $event)
    {
        if (isset($this->records[$channel]) && $this->records[$channel] > 0) {
            $event->addResponse(
                Response::msg(
                    $channel,
                    sprintf(
                        'Audience record: %d nick(s) on %s',
                        $this->records[$channel],
                        $this->dates[$channel]
                    )
                )
            );
        } else {
            $event->addResponse(Response::msg($channel, 'No audience record'));
        }
    }

    public function displayCurrent($channel, Event $event, $user = null)
    {
        if (isset($this->nicks[$channel]) && $this->nicks[$channel] > 0) {
            $event->addResponse(
                Response::msg(
                    $user ?: $channel,
                    sprintf(
                        'Current audience%s: %d nick(s)',
                        $user ? ' on ' . $channel : '',
                        $this->nicks[$channel]
                    )
                )
            );
        }
    }

    public function boot(array $config = array())
    {
        parent::boot($config);

        $this->bot->send(new Response('NAMES', implode(',', $this->getChannels())));
    }

    protected function getChannels()
    {
        $channels = array();
        foreach ($this->config['channels'] as $key => $value) {
            $channels[] = is_numeric($key) ? $value : $key;
        }

        return $channels;
    }

    public function init()
    {
        $self = $this;

        $this->bot->onServer(
            353,
            function(Event $event) use ($self) {
                $request = $event->getRequest();
                $params = $request->getParams();
                $channel = end($params);
                $nicks = explode(' ', $request->getMessage());

                $self->initialize($channel, $event, $nicks);
            }
        );

        $this->bot->onJoin(function(Event $event) use ($self) {
            $chan = $event->getRequest()->getSource();

            $self->increment($chan, $event);
        });

        $this->bot->onPart(function(Event $event) use ($self) {
            $chan = $event->getRequest()->getSource();

            $self->decrement($chan, $event);
        });

        $this->bot->onChannel(
            '/^!audience$/',
            function(Event $event) use ($self) {
                $channel = $event->getRequest()->getSource();
                $self->displayRecord($channel, $event);
                $self->displayCurrent($channel, $event);
            }
        );

        $this->bot->onPrivateMessage(
            '/^!audience add (?P<channel>([#&][^\x07\x2C\s]{0,200})) (?P<audience>\d+)/',
            function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $channel = $matches['channel'];
                $audience = $matches['audience'];

                if ($self->hasCredential($event->getRequest()->getSendingUser())) {
                    $self->increment($channel, $event, $audience);
                }
            }
        );

        $this->bot->onPrivateMessage(
            '/^!audience (?P<channel>([#&][^\x07\x2C\s]{0,200}))/',
            function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $channel = $matches['channel'];
                $user = $event->getRequest()->getSendingUser();

                $self->displayCurrent($channel, $event, $user);
            }
        );
    }

    public function displayHelp(Event $event)
    {
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '* Audience'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !audience'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !audience <#channel>'));

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
