<?php

namespace Newscoop\SolrSearchPluginBundle\Services;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Guzzle\Http\Client;
use Guzzle\Http\QueryString;
use Guzzle\Http\Exception\ServerErrorResponseException;
use Newscoop\Search\QueryInterface;
use Newscoop\Entity\Topic;
use Newscoop\SolrSearchPluginBundle\Search\SolrException;
use Exception;
use DateTime;

/**
 * Configuration service for article type
 */
class SolrQueryService implements QueryInterface
{
    const LIMIT = 12;

    /**
     * @var array
     */
    // TODO: Make this configurable
    protected $dates = array(
        '24h' => '[NOW-1DAY/HOUR TO *]',
        '1d' => '[NOW/DAY TO *]',
        '2d' => '[NOW-1DAY/DAY TO NOW/DAY]',
        '7d' => '[NOW-7DAY/DAY TO *]',
        '1y' => '[NOW-1YEAR/DAY TO *]',
    );

    /**
     * @var array
     */
    // TODO: Make this configurable
    protected $types = array(
        'article' => array('news', 'newswire'),
        'dossier' => 'dossier',
        'blog' => 'blog',
        'comment' => 'comment',
        'link' => 'link',
        'event' => 'event',
        'user' => 'user',
    );

    /**
     * Configuration data
     *
     * @var array
     */
    private $config = array();

    /**
     * Solr location
     *
     * @var string
     */
    private $url;

    /**
     * Solr query uri
     *
     * @var string
     */
    private $query_uri;

    /**
     * Initialize service data
     *
     * @param Symfony\Component\DependencyInjection\Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->em = $this->container->get('em');
        $this->router = $this->container->get('router');
        $this->request = $this->container->get('request');

        try {
            $this->config = $this->container->getParameter('SolrSearchPluginBundle');
        } catch(Exception $e) {
            return new SolrException($this->container->get('translator')->trans('plugin.error.config'));
        }

        $this->url = $this->getConfig('url');
        $this->query_uri = $this->getConfig('query_uri');
    }

    /**
     * Build solr date param
     *
     * @return string
     */
    public function buildSolrDateParam($parameters)
    {
        $date = (array_key_exists('date', $parameters)) ? $parameters['date'] : false;
        if (!$date) {
            return;
        }

        $dates = $this->dates;
        // TODO: Fix later
        //$dates = $this->getConfig('dates');

        if (array_key_exists($date, $dates)) {
            return sprintf('published:%s', $dates[$date]);
        }

        try {
            list($from, $to) = explode(',', $date, 2);
            $fromDate = empty($from) ? null : new DateTime($from);
            $toDate = empty($to) ? null : new DateTime($to);
        } catch (Exception $e) {
            return;
        }

        return sprintf('published:[%s TO %s]',
            $fromDate === null ? '*' : $fromDate->format('Y-m-d\TH:i:s\Z') . '/DAY',
            $toDate === null ? '*' : $toDate->format('Y-m-d\TH:i:s\Z') . '/DAY');
    }

    public function encodeParameters(array $parameters)
    {
        return array(
            'wt' => 'json',
            'rows' => self::LIMIT,
            'start' => max(0, (int) (array_key_exists('start', $parameters) ? $parameters['start'] : 0)),
        );
    }

    public function decodeParameters(array $parameters)
    {

    }

    public function decodeResponse($response)
    {
        $decoded = json_decode($response, true);
        $decoded['responseHeader']['params']['q'] = $this->request->get('q'); // this might be modified, keep users query
        $decoded['responseHeader']['params']['date'] = $this->request->get('date');
        $decoded['responseHeader']['params']['type'] = $this->request->get('type');
        $decoded['responseHeader']['params']['source'] = $this->request->get('source');
        $decoded['responseHeader']['params']['section'] = $this->request->get('section');
        $decoded['responseHeader']['params']['sort'] = $this->request->get('sort');
        $decoded['responseHeader']['params']['topic'] = array_filter(explode(',', $this->request->get('topic', '')));

        $decoded['responseHeader']['params']['q_topic'] = null;
        if ($this->request->get('q') !== '') {

            $topic = $this->em->getRepository('Newscoop\Entity\Topic')
                ->findOneByName($this->request->get('q') . ':de');
            if ($topic !== null) {
                $decoded['responseHeader']['params']['q_topic'] = $topic->getName(); // TODO: check why was: getName(5)
            }
        }

        return $decoded;
    }

    public function find(array $filter = array())
    {
        $translator = $this->container->get('translator');
        $client = new Client();

        if (array_key_exists('core-language', $filter)) {
            $core = $filter['core-language'];
            unset($filter['core-language']);
        } else {
            $defaultCore = $this->container->getParameter('SolrSearchPluginBundle.default_core');
            if ($defaultCore === null) {
                throw new SolrException($translator->trans('plugin.error.solrcore'));
            }
        }

        $uri = $this->url . str_replace('{core}', $core, $this->query_uri);
        $uri .= '?'.http_build_query($filter);

        // DEBUG
        // $uri = str_replace('json', 'xml', $uri);
        //echo '$uri: '.$uri.'<br>';// exit;

        $solrRequest = $client->get($uri);

        try {
            $solrResponse = $solrRequest->send();
        } catch(ServerErrorResponseException $e) {
            return $this->redirect($this->generateUrl('newscoop_solrsearchplugin_error'));
        } catch (Exception $e) {
            throw new SolrException($translator->trans('plugin.error.curl'));
        }

        if (!$solrResponse->isSuccessful()) {
            return $this->redirect($this->generateUrl('newscoop_solrsearchplugin_error'));
        }

        return $this->decodeResponse($solrResponse->getBody(true));
    }

    /**
     * Returns configuration array or part of it.
     *
     * @param  string $key Key to get only specific part from configuration
     *
     * @return mixed      Returns array with configuration or null if config
     *                    isn't initiated properly.
     */
    public function getConfig($key = null)
    {
        if (count($this->config) == 0) {
            return null;
        }

        if ($key !==  null && array_key_exists($key, $this->config)) {
            return $this->config[$key];
        } else {
            return $this->config;
        }
    }
}
