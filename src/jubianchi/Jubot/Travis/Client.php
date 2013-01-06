<?php
namespace jubianchi\Jubot\Travis;

use Github\Client as BaseClient;
use Github\Api\ApiInterface;
use Github\Exception\InvalidArgumentException;
use Github\HttpClient\HttpClient;
use Github\HttpClient\HttpClientInterface;

class Client extends BaseClient
{
    /**
     * @var array
     */
    private $options = array(
        'base_url'    => 'https://api.travis-ci.org/',

        'user_agent'  => 'jubot-travis-api',
        'timeout'     => 10,

        'cache_dir'   => null
    );

    /**
     * The Buzz instance used to communicate with GitHub
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Instantiate a new GitHub client
     *
     * @param null|HttpClientInterface $httpClient Github http client
     */
    public function __construct(HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?: new HttpClient($this->options);
    }

    /**
     * @param string $name
     *
     * @return ApiInterface
     *
     * @throws InvalidArgumentException
     */
    public function api($name)
    {
        switch ($name) {
            case 'repo':
            case 'repos':
            case 'repository':
            case 'repositories':
                $api = new Api\Repository($this);
                break;

            case 'build':
            case 'builds':
                $api = new Api\Build($this);
                break;

            default:
                throw new InvalidArgumentException();
        }

        return $api;
    }

    /**
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @param HttpClientInterface $httpClient
     */
    public function setHttpClient(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Clears used headers
     */
    public function clearHeaders()
    {
        $this->httpClient->clearHeaders();
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->httpClient->setHeaders($headers);
    }

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function getOption($name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new InvalidArgumentException(sprintf('Undefined option called: "%s"', $name));
        }

        return $this->options[$name];
    }


    /**
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function setOption($name, $value)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new InvalidArgumentException(sprintf('Undefined option called: "%s"', $name));
        }

        if ('api_version' == $name && !in_array($value, array('v3', 'beta'))) {
            throw new InvalidArgumentException(sprintf('Invalid API version ("%s"), valid are: %s', $name, implode(', ', array('v3', 'beta'))));
        }

        $this->options[$name] = $value;
    }
}
