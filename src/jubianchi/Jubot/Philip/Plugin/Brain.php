<?php
namespace jubianchi\Jubot\Philip\Plugin;

use Philip\AbstractPlugin;
use Philip\IRC\Event;
use Philip\IRC\Response;

class Brain extends AbstractPlugin
{
    /** @var \SQLite3 */
    private $brain;

    public function getName()
    {
        return 'brain';
    }

    public function init()
    {
        $self = $this;
        $brain = $this->brain;

        $this->bot->onPrivateMessage(
            '/^!get (?P<key>[^\s]+)/',
            function(Event $event) use ($self, $brain) {
                $matches = $event->getMatches();

                if($self->hasCredential($event->getRequest()->getSendingUser())) {
                    $values = $self->get($matches['key']);
                    if (false === is_array($values)) {
                        $values = array($matches['key'] => $values);
                    }

                    foreach ($values as $key => $value) {
                        $event->addResponse(
                            Response::msg(
                                $event->getRequest()->getSource(),
                                sprintf('%s => %s', $key, $value)
                            )
                        );
                    }
                }
            }
        );

        $this->bot->onPrivateMessage(
            '/^!set (?P<key>[^\s]+) (?P<value>.*)/',
            function(Event $event) use ($self, $brain) {
                $matches = $event->getMatches();

                if($self->hasCredential($event->getRequest()->getSendingUser())) {
                    $event->addResponse(
                        Response::msg(
                            $event->getRequest()->getSource(),
                            sprintf('%s => %s', $matches['key'], $self->set($matches['key'], $matches['value']))
                        )
                    );
                }
            }
        );
    }

    public function boot(array $config = array())
    {
        parent::boot($config);

        if (false === is_dir($config['path'])) {
            mkdir($config['path'], 0777, true);
        }

        $db = $config['path'] . DIRECTORY_SEPARATOR . 'brain.db';
        $flags = SQLITE3_OPEN_READWRITE;
        if (false === is_file($db)) {
            $flags += SQLITE3_OPEN_CREATE;
        }

        $this->brain = new \SQLite3($db, $flags);

        if (($flags & SQLITE3_OPEN_CREATE) === SQLITE3_OPEN_CREATE) {
            $this->brain->exec('CREATE TABLE brain (key VARCHAR(255), value TEXT)');
        }
    }

    public function get($key, $default = null)
    {
        $sql = 'SELECT * FROM brain WHERE key LIKE \'' . sqlite_escape_string($key) . '\'';
        $query = $this->brain->query($sql);

        $results = array();
        while ($row = $query->fetchArray(SQLITE_ASSOC)) {
            $results[$row['key']] = $row['value'];
        }

        $count = count($results);

        return ($count === 0 ? $default : ($count === 1 ? array_pop($results) : $results));
    }

    public function set($key, $value)
    {
        $sql = 'SELECT COUNT(*) as num FROM brain WHERE key=\'' . sqlite_escape_string($key) . '\'';
        $result = $this->brain->querySingle($sql);

        if ($result === 0) {
            $sql = 'INSERT INTO brain VALUES(\'' . sqlite_escape_string($key) . '\', \'' . sqlite_escape_string($value) . '\')';
        } else {
            $sql = 'UPDATE brain SET value=\'' . sqlite_escape_string($value) . '\' WHERE key=\'' . sqlite_escape_string($key) . '\'';
        }

        $this->brain->exec($sql);

        return $value;
    }

    public function displayHelp(Event $event)
    {
        if ($this->hasCredential($event->getRequest()->getSendingUser())) {
            $event->addResponse(Response::msg($event->getRequest()->getSource(), '* Brain'));
            $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !set <key> <value>'));
            $event->addResponse(Response::msg($event->getRequest()->getSource(), '*    [priv] !get <key>'));
            $event->addResponse(Response::msg($event->getRequest()->getSource(), '*'));
        }
    }

    public function hasCredential($user)
    {
        return ($this->bot->isAdmin($user) && $this->bot->getPlugin('auth')->isLoggedIn($user));
    }
}
