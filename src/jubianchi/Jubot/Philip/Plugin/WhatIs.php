<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class WhatIs extends AbstractPlugin
{
    public function getName()
    {
        return 'whatis';
    }

    public function init()
    {
        $bot = $this->bot;

        $this->bot->onChannel(
            '/^!(?P<nick>[^\s]+) (?P<verb>est|is) (?P<what>(?:((?:an?|the)|(?:une?|le|la)) )?(.*))/',
            function(Event $event) use ($bot) {
                $matches = $event->getMatches();
                $channel = $event->getRequest()->getSource();
                $nick = $matches['nick'];
                $verb = isset($matches['verb']) ? $matches['verb'] . ' ' : null;
                $what = $matches['what'];

                if ($bot->getPlugin('audience')->isOn($nick, $channel)) {
                    $values = $bot->getPlugin('brain')->get(sprintf('whatis.%s', $nick), '[]');
                    $values = json_decode($values);

                    if(false === in_array($verb . $what, $values)) {
                        $values[] = $verb . $what;
                        $bot->getPlugin('brain')->set(sprintf('whatis.%s', $nick), json_encode($values));
                    } else {
                        $event->addResponse(
                            Response::msg(
                                $channel,
                                sprintf('I already know that %s %s', $nick, $verb . $what)
                            )
                        );
                    }
                } else {
                    $event->addResponse(
                        Response::msg(
                            $event->getRequest()->getSendingUser(),
                            sprintf('I can\'t find %s on channel %s', $nick, $event->getRequest()->getSource())
                        )
                    );
                }
            }
        );

        $this->bot->onChannel(
            '/^!(?P<nick>[^\s]+)/',
            function(Event $event) use ($bot) {
                $matches = $event->getMatches();
                $channel = $event->getRequest()->getSource();
                $nick = $matches['nick'];

                if ($bot->getPlugin('audience')->isOn($nick, $channel)) {
                    $values = $bot->getPlugin('brain')->get(sprintf('whatis.%s', $nick), '[]');
                    $values = json_decode($values);

                    if (count($values)) {
                        $value = $values[rand(0, count($values) - 1)];

                        $event->addResponse(
                            Response::msg(
                                $channel,
                                sprintf('%s %s', $nick, $value)
                            )
                        );
                    }
                }
            }
        );
    }

    public function displayHelp(Event $event)
    {
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '* What is'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !<nick> is <what>'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !<nick>'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
    }
}
