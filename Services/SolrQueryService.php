<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\Translator;
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
     * @param SolrHelperService $helper
     * @param Translator        $translator
     */
    public function __construct(SolrHelperService $helper, Translator $translator)
    {
        $this->helper = $helper;
        $this->translator = $translator;

        // TODO: Check if we can use a request listener for this
        // if ($this->container->isScopeActive('request')) {
        //     $this->request = $this->container->get('request');
        // } else {
        //     $this->request = new Request();
        // }
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

    public function encodeParameters($query)
    {
        return $query;

        // return array(
        //     'wt' => 'json',
        //     'rows' => self::LIMIT,
        //     'start' => max(0, (int) (array_key_exists('start', $parameters) ? $parameters['start'] : 0)),
        // );
    }

    public function decodeParameters(array $parameters)
    {

    }

    public function decodeResponse($response)
    {
        $decoded = json_decode($response, true);

        if ($this->helper->getConfigValue('index_mode') == SolrHelperService::INDEX_AND_DATA) {
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
