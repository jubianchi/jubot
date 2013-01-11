<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;
use Github\Client;

class Github extends AbstractPlugin
{
    private $client;

    const EXP_PREFIX = '^!(?:gh|github) ';
    const EXP_QUERY  = '"(?P<query>.*)"';
    const EXP_OFFSET = '(?: (?P<offset>\d+)$|)';
    const EXP_REPO   = '(?P<username>[^ ]+)\/(?P<repository>[^ ]+)';

    public function getName()
    {
        return 'github';
    }

    public function init()
    {
        $self = $this;

        $commands = array(
            '/' . static::EXP_PREFIX . '(?:repos?|repositor(?:y|ies)) (?:find|search) ' . static::EXP_QUERY . static::EXP_OFFSET . '/'
            => function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $query = $matches['query'];
                $offset = isset($matches['offset']) ? (int) $matches['offset'] : 0;
                $offset = ($offset - 1) < 0 ? 0 : ($offset - 1);

                $repos = $self->getClient()->api('repositories')->find($query, array());
                $more  = count($repos['repositories']) - (($offset + 1) * 10);
                $repos = array_slice($repos['repositories'], ($offset * 10), 10);

                foreach ($repos as $repo) {
                    $event->addResponse(Response::msg(
                        $event->getRequest()->getSource(),
                        sprintf(
                            '%s/%s - https://github.com/%1$s/%2$s',
                            $repo['owner'],
                            $repo['name']
                        )
                    ));
                }

                if ($more > 0) {
                    $event->addResponse(Response::msg(
                        $event->getRequest()->getSource(),
                        sprintf('%d more repositories', $more)
                    ));
                }
            },
            '/' . static::EXP_PREFIX . 'issues? (?:find|search)(?: (?P<status>open|close)|) ' . static::EXP_REPO . ' ' . static::EXP_QUERY . static::EXP_OFFSET . '/'
            => function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $username = $matches['username'];
                $repository = $matches['repository'];
                $query = $matches['query'];
                $status = isset($matches['status']) ? $matches['status'] : 'open';
                $offset = isset($matches['offset']) ? (int) $matches['offset'] : 0;
                $offset = ($offset - 1) < 0 ? 0 : ($offset - 1);

                $issues = $self->getClient()->api('issues')->find($username, $repository, $status, $query);
                $more  = count($issues['issues']) - (($offset + 1) * 10);
                $issues = array_slice($issues['issues'], ($offset * 10), 10);

                foreach ($issues as $issue) {
                    $event->addResponse(Response::msg(
                        $event->getRequest()->getSource(),
                        sprintf(
                            '%s - %s:',
                            $issue['created_at'],
                            $issue['user']
                        )
                    ));

                    preg_match('/issues\/(\d+)/', $issue['html_url'], $matches);

                    $event->addResponse(Response::msg($event->getRequest()->getSource(), '#' . $matches[1] . ' ' . $issue['title']));
                    $event->addResponse(Response::msg($event->getRequest()->getSource(), $issue['html_url']));
                    $event->addResponse(Response::msg($event->getRequest()->getSource(), str_repeat('-', 50)));
                }

                if ($more > 0) {
                    $event->addResponse(Response::msg(
                        $event->getRequest()->getSource(),
                        sprintf('%d more issues', $more)
                    ));
                }
            },
            '/' . static::EXP_PREFIX . 'issues? show ' . static::EXP_REPO . ' \#?(?P<id>\d+)/'
            => function(Event $event) use ($self) {
                $matches = $event->getMatches();
                $username = $matches['username'];
                $repository = $matches['repository'];
                $id = $matches['id'];

                $issue = $self->getClient()->api('issues')->show($username, $repository, $id);

                $event->addResponse(Response::msg(
                    $event->getRequest()->getSource(),
                    sprintf(
                        '[%s] #%d %s',
                        $issue['state'],
                        $issue['number'],
                        $issue['title']
                    )
                ));
                $event->addResponse(Response::msg($event->getRequest()->getSource(), $issue['created_at'] . ' / ' . $issue['updated_at']));
                $event->addResponse(Response::msg($event->getRequest()->getSource(), $issue['html_url']));
                $event->addResponse(Response::msg($event->getRequest()->getSource(), ''));

                foreach (explode("\n", $issue['body']) as $line) {
                    $event->addResponse(Response::msg($event->getRequest()->getSource(), $line));
                }
            }
        );

        foreach ($commands as $pattern => $callback) {
            $this->bot->onChannel($pattern, $callback);
            $this->bot->onPrivateMessage($pattern, $callback);
        }
    }

    public function getClient()
    {
        if (null === $this->client) {
            $this->client = new Client();
            $this->client->authenticate($this->config['token'], null, Client::AUTH_HTTP_TOKEN);
        }

        return $this->client;
    }

    public function displayHelp(Event $event)
    {
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '* Github'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !github repos search "<query>" [<page>]'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !github issues search [open|closed] <username>/<repository> "<query>" [<page>]'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    !github issue show <id>'));
        $event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
    }
}
