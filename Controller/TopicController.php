<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class TopicController extends Controller
{
    /**
     * @var array
     */
    private $sources = array(
        'tageswoche' => array('news', 'dossier', 'blog'),
    );

    // TODO: Fix methods to match current parameters

    /**
     * @Route("/topic/")
     * @Route("/{language}/topic/")
     */
    public function topicAction(Request $request, $language = null)
    {
        $language = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Language')
            ->findOneByCode($language);

        if ($language === null) {
            $language = $this->container->get('em')
                ->getRepository('Newscoop\Entity\Language')
                ->findByRFC3066bis('en-US', true);
            if ($language == null) {
                throw new NewscoopException('Could not find default language.');
            }
        }

        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');
        $parameters = $request->query->all();

        $solrParameters = $this->encodeParameters($parameters);
        $solrParameters['core-language'] = $language->getRFC3066bis();

        $response = $queryService->find($solrParameters);

        return $response;
    }

    /**
     * Build solr params array
     *
     * @return array
     */
    protected function encodeParameters(array $parameters)
    {
        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');

        $fq = implode(' AND ', array_filter(array(
            $this->buildSolrTypeParam($parameters),
            $queryService->buildSolrDateParam($parameters),
            '-section:swissinfo', // filter en news
        )));

        $sort = 'score desc';
        if (array_key_exists('sort', $parameters)) {
            if ($parameters['sort'] === 'latest') {
                $sort = 'published desc';
            } elseif ($parameters['sort'] === 'oldest') {
                $sort = 'published asc';
            }
        }

        return array_merge($queryService->encodeParameters($parameters), array(
            'q' => $this->buildSolrQuery($parameters),
            'fq' => empty($fq) ? '' : "{!tag=t}$fq",
            'sort' => $sort,
            'facet' => 'true',
            'facet.field' => '{!ex=t}type',
            'spellcheck' => 'true',
        ));
    }

    /**
     * Build solr query
     *
     * @return string
     */
    private function buildSolrQuery($parameters)
    {
        $q = (array_key_exists('q', $parameters)) ? trim($parameters['q']) : sha1(__FILE__); // search for nonsense to show empty search result page

        if ($this->container->get('webcode')->findArticleByWebcode($q) !== null) {
            return sprintf('webcode:\%s', $q);
        }

        $matches = array();
        if (preg_match('/^(author|topic):([^"]+)$/', $q, $matches)) {
            $q = sprintf('%s:"%s"', $matches[1], json_encode($matches[2]));
        }

        return $q;
    }

    /**
     * Build solr type param
     *
     * @return string
     */
    private function buildSolrTypeParam($parameters)
    {
        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');
        //$types = $queryService->getConfig('types');
        $types = $this->container->getParameter('SolrSearchPluginBundle');
        $types = $types['application']['topic']['types'];

        if (!array_key_exists('type', $parameters) || !array_key_exists($parameters['type'], $types)) {
            return;
        }

        $type = $parameters['type'];

        return sprintf('type:(%s)', is_array($types[$type]) ? implode(' OR ', $types[$type]) : $types[$type]);
    }
}
