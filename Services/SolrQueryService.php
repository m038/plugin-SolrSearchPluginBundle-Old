<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Services;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\Translator;
use Newscoop\Topic\TopicService;
use Newscoop\SolrSearchPluginBundle\Exception\SolrException;
use Newscoop\SolrSearchPluginBundle\Search\SolrQuery;
use Newscoop\SolrSearchPluginBundle\Services\SolrHelperService;
use Exception;
use DateTime;

/**
 * Configuration service for article type
 */
class SolrQueryService
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
     * @var Newscoop\SolrSearchPluginBundle\Services\SolrHelperService
     */
    private $helper;

    /**
     * @var Symfony\Component\Translation\Translator
     */
    private $translator;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Newscoop\Topic\TopicService
     */
    private $topicService;

    /**
     * Request object
     *
     * @var Symfony\Component\HttpFoundation\Request
     */
    private $request;

    /**
     * @param SolrHelperService $helper
     * @param Translator        $translator
     * @param EntityManager     $em
     * @param TopicService      $topic
     */
    public function __construct(
        SolrHelperService $helper,
        Translator $translator,
        EntityManager $em,
        TopicService $topicService
    ) {
        $this->helper = $helper;
        $this->translator = $translator;
        $this->em = $em;
        $this->topicService = $topicService;
    }

    /**
     * Set request
     *
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
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

    /**
     * Build solr topic filter
     *
     * @return string
     */
    public function buildSolrSingleValueParam($fieldName, $fieldValue)
    {
        $param = '';

        if (!empty($fieldValue)) {
            $fieldValue = (is_array($fieldValue)) ? $fieldValue : array($fieldValue);
            $param = sprintf('%s:(%s)', $fieldName, implode(' OR ', $fieldValue));
        }

        return $param;
    }

    public function buildSolrMultiValueParam($fieldName, $fieldValue)
    {
        $param = '';

        if (!empty($fieldValue)) {
            $fieldValue = (is_array($fieldValue)) ? $fieldValue : array($fieldValue);
            array_walk($fieldValue, function(&$value) {
                $value = '"'.trim($value, '"').'"';
            });
            $param =  sprintf('%s:(%s)', $fieldName, implode(',', $fieldValue));
        }

        return $param;
    }

    public function encodeParameters($query)
    {
        $query = ($query instanceof SolrQuery) ? $query : new SolrQuery($query);

        if (is_null($query->core) || empty($query->core)) {
            $query->core = $this->helper->getConfigValue('default_core', null);
        }

        return $query;
    }

    public function decodeParameters(array $parameters)
    {

    }

    public function decodeResponse($response)
    {
        $decoded = json_decode($response, true);

        if ($this->helper->getConfigValue('index_type') == SolrHelperService::INDEX_AND_DATA) {

            $decoded['responseHeader']['params']['q'] = $this->request->get('q'); // this might be modified, keep users query
            $decoded['responseHeader']['params']['date'] = $this->request->get('date');
            $decoded['responseHeader']['params']['type'] = $this->request->get('type');
            $decoded['responseHeader']['params']['source'] = $this->request->get('source');
            $decoded['responseHeader']['params']['section'] = $this->request->get('section');
            $decoded['responseHeader']['params']['sort'] = $this->request->get('sort');
            $decoded['responseHeader']['params']['topic'] = array_filter(explode(',', $this->request->get('topic', '')));

            $decoded['responseHeader']['params']['q_topic'] = null;

            if ($this->request->get('q') !== '') {
                $topic = $this->topicService->getTopicByIdOrName($this->request->get('q'), 5);
                if ($topic !== null) {
                    $decoded['responseHeader']['params']['q_topic'] = $topic->getName();
                }
            }
        }

        return $decoded;
    }

    public function find(SolrQuery $query)
    {
        $client = $this->helper->initClient();

        $url = sprintf(
            '%s?%s',
            $this->helper->getQueryUrl($query->core),
            http_build_query($this->encodeParameters($query))
        );

        try {
            $solrResponse = $client->get($url);
        } catch (Exception $e) {
            throw new Exception($this->translator->trans('plugin.error.curl'));
        }

        if (!$solrResponse->isSuccessful()) {
            throw new SolrException($this->translator->trans('plugin.error.response_false'));
        }

        return $this->decodeResponse($solrResponse->getContent());
    }
}
