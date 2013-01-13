<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class Audience extends AbstractPlugin
{
    private $nicks = array();
    private $audience = array();

    public function getName()
    {
        return 'audience';
    }

    public function getBrain()
    {
        return $this->bot->getPlugin('brain');
    }

    protected function record($channel, Event $event, array $nicks)
    {
        $record = $this->getBrain()->get(sprintf('audience.%s.record', $channel), 0);
        $botcfg = $this->bot->getConfig();
        $patterns = $this->getPatterns($channel);
        $patterns[] = $botcfg['nick'];

        $nicks = array_filter(
            $nicks,
            function(& $nick) use ($patterns, $channel) {
                foreach ($patterns as $pattern) {
                    if (@preg_match($pattern, $nick) || $nick === $pattern) {
                        return false;
                    }
                }

                return true;
            }
        );
        $this->audience[$channel] = count($nicks);

        if ($this->audience[$channel] > $record) {
            $this->getBrain()->set(sprintf('audience.%s.record', $channel), $this->audience[$channel]);
            $this->getBrain()->set(sprintf('audience.%s.date', $channel), date('d/m/Y H:i:s'));

            $event->addResponse(
                Response::msg(
                    $channel,
                    sprintf('New audience record: %d nick(s)', $this->audience[$channel])
                )
            );
        }
    }

    public function initialize($channel, Event $event, array $nicks)
    {
        $this->nicks[$channel] = $nicks;

        $this->record($channel, $event, $this->nicks[$channel]);

        $this->nicks[$channel] = array_map(
            function($nick) {
                return ltrim($nick, '@+');
            },
            $this->nicks[$channel]
        );
    }

    protected function getPatterns($channel)
    {
        return isset($this->config['channels'][$channel]) ? $this->config['channels'][$channel] : array();
    }

    public function displayRecord($channel, Event $event, $user = null)
    {
        $record = $this->getBrain()->get(sprintf('audience.%s.record', $channel), 0);

        if ($record > 0) {
            $date = $this->getBrain()->get(sprintf('audience.%s.date', $channel), 0);

            $event->addResponse(
                Response::msg(
                    $user ?: $channel,
                    sprintf(
                        'Audience record%s: %d nick(s) on %s',
                        $user ? ' on ' . $channel : '',
                        $record,
                        $date
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
                        $this->audience[$channel]
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

            $event->addResponse(new Response('NAMES', $chan));
        });

        $this->bot->onPart(function(Event $event) use ($self) {
            $chan = $event->getRequest()->getSource();

            $event->addResponse(new Response('NAMES', $chan));
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
            '/^!audience refresh (?P<channel>([#&][^\x07\x2C\s]{0,200}))/',
            function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $channel = $matches['channel'];

                if ($self->hasCredential($event->getRequest()->getSendingUser())) {
                    $event->addResponse(new Response('NAMES', $channel));
                }
            }
        );

        $this->bot->onPrivateMessage(
            '/^!audience (?P<channel>([#&][^\x07\x2C\s]{0,200}))/',
            function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $channel = $matches['channel'];
                $user = $event->getRequest()->getSendingUser();

                $self->displayRecord($channel, $event, $user);
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
            $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !audience refresh <#channel>'));
        }

        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
    }

    public function hasCredential($user)
    {
        return ($this->bot->isAdmin($user) && $this->bot->getPlugin('auth')->isLoggedIn($user));
    }

    public function isOn($nick, $channel)
    {
        $nick = ltrim($nick, '@+');

        return isset($this->nicks[$channel]) ? in_array($nick, $this->nicks[$channel]) : false;
    }
}
