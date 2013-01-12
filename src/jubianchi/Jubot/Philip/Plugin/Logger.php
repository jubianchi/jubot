<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class Logger extends AbstractPlugin
{
    public function getName()
    {
        return 'logger';
    }

    public function init()
    {
        $this->bot->onChannel('/.*/', $this->getLoggerCallback());

        $this->bot->onChannel(
            '/^!log (?:(?P<since>[a-zA-Z0-9][a-zA-Z0-9 +\-]*)|)(?:\.\.(?P<until>[a-zA-Z0-9][a-zA-Z0-9 +\-]*?))?(?: -(?:f |-from=)(?P<from>[a-zA-Z][a-zA-Z0-9\-\[\]\\`\^{}]*)|$)/',
            $this->getLogCallback()
        );

        $this->bot->onPrivateMessage(
            '/^!log (?P<channel>([#&][^\x07\x2C\s]{0,200})) (?:(?P<since>[a-zA-Z0-9][a-zA-Z0-9 +\-]*)|)(?:\.\.(?P<until>[a-zA-Z0-9][a-zA-Z0-9 +\-]*?))?(?: -(?:f |-from=)(?P<from>[a-zA-Z][a-zA-Z0-9\-\[\]\\`\^{}]*)|$)/',
            $this->getLogCallback()
        );
    }

    protected function getLoggerCallback()
    {
        $self = $this;

        return function(Event $event) use ($self) {
            $self->log($event);
        };
    }

    protected function getLogCallback()
    {
        $self = $this;

        return function(Event $event) use ($self) {
            $self->display($event);
        };
    }

    public function log(Event $event)
    {
        if (in_array($event->getRequest()->getSource(), $this->config['channels'])) {
            $log = sprintf(
                "%s\t%-20s\t%s" . PHP_EOL,
                date('m/d/Y H:i:s'),
                $event->getRequest()->getSendingUser(),
                $event->getRequest()->getMessage()
            );
            $file = $this->config['path'] . DIRECTORY_SEPARATOR . trim($event->getRequest()->getSource(), '#&!+');

            $handle = fopen($file, 'a+');
            fwrite($handle, $log);
            fclose($handle);
        }
    }

    public function display(Event $event)
    {
        $matches = $event->getMatches();
        $channel = isset($matches['channel']) ? $matches['channel'] : $event->getRequest()->getSource();
        $file = $this->config['path'] . DIRECTORY_SEPARATOR . trim($channel, '#');

        try {
            $since = new \DateTime(isset($matches['since']) ? $matches['since'] : '1 hour ago');
            $until = new \DateTime(isset($matches['until']) ? $matches['until'] : 'now');
            $from = isset($matches['from']) ? trim($matches['from']) : null;

            if (file_exists($file)) {
                foreach (file($file) as $line) {
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
                    } catch (\Exception $exception) {}
                }
            }
        } catch (\Exception $exception) {}
    }

    public function boot(array $config = array())
    {
        parent::boot($config);

        if (false === is_dir($config['path'])) {
            mkdir($config['path'], 0777, true);
        }
    }

    public function displayHelp(Event $event)
    {
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '* Logger'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !log [[<since>=1 hour ago]..[<until>=now]] [-f|--from=<from>]'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !log <#channel> [[<since>=1 hour ago]..[<until>=now]] [-f|--from=<from>]'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
    }
}
