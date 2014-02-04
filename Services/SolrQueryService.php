<?php

namespace Newscoop\SolrSearchPluginBundle\Services;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Guzzle\Http\Client;
use Guzzle\Http\QueryString;
use Newscoop\Search\QueryInterface;
use Newscoop\Entity\Topic;

/**
 * Configuration service for article type
 */
class SolrQueryService implements QueryInterface
{
    const LIMIT = 12;

    const SOLR_URL = 'http://localhost:8983/solr';
    const QUERY_URI = '/{core}/select';

    /**
     * @var array
     */
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
    }

    /**
     * Build solr params array
     *
     * @return array
     */
    public function buildSolrParams()
    {

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

        if (array_key_exists($date, $this->dates)) {
            return sprintf('published:%s', $this->dates[$date]);
        }

        try {
            list($from, $to) = explode(',', $date, 2);
            $fromDate = empty($from) ? null : new \DateTime($from);
            $toDate = empty($to) ? null : new \DateTime($to);
        } catch (\Exception $e) {
            return;
        }

        return sprintf('published:[%s TO %s]',
            $fromDate === null ? '*' : $fromDate->format('Y-m-d\TH:i:s\Z') . '/DAY',
            $toDate === null ? '*' : $toDate->format('Y-m-d\TH:i:s\Z') . '/DAY');
    }

    /**
     * Decode solr response
     *
     * @return array
     */
    public function decodeSolrResponse(\Zend_Http_Response $response)
    {

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
        $client = new Client();

        // TODO: replace by language
        $core = '';//'de-DE';

        $uri = self::SOLR_URL . str_replace('{core}/', $core, self::QUERY_URI);
        $uri .= '?'.http_build_query($filter);

        //echo '$uri: '.$uri.'<br>'; exit;

        $request = $client->get($uri);

        try {
            $response = $request->send();
        } catch(\Guzzle\Http\Exception\ServerErrorResponseException $e) {
            $this->_forward('error', 'search', 'default');
            return false;
        }

        if (!$response->isSuccessful()) {
            $this->_forward('error', 'search', 'default');
            return;
        }

        if ($response->isContentType('application/json')) {
            return new JsonResponse($this->decodeResponse($response->getBody(true)));
        }

        if ($response->isContentType('application/xml')) {
            return new JsonResponse($this->decodeResponse($response));
        }

        return $response;

        // if ($this->_helper->contextSwitch->getCurrentContext() === 'json') {
        //     $this->_helper->json($this->decodeSolrResponse($response));
        //     return;
        // }

        // $this->view->result = $this->decodeSolrResponse($response);

        // if ($this->_helper->contextSwitch->getCurrentContext() === 'xml') {
        //     $this->getResponse()->setHeader('Content-Type', 'application/rss-xml', true);
        //     $this->render('xml');
        // }
    }
}
