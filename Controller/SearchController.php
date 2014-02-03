<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\OmnitickerPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Http\Client;

class SearchController extends Controller
{
    /**
     * @Route("/search")
     */
    public function searchAction(Request $request)
    {
        if ($this->container->get('webcode')->findArticleByWebcode($request->query->get('q')) !== null) {

            return $this->redirect(
                sprintf('/%s', $request->get('q')), 302
            );
        }

        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');
        $parameters = $request->query->all();

        if (array_key_exists('format', $parameters)) {

            $solrParameters = $this->encodeParameters($parameters);
            $response = $queryService->find($solrParameters);
        } else {

            $templatesService = $this->container->get('newscoop.templates.service');

            $response = new Response();
            $smarty = $templatesService->getSmarty();
            $response->setContent($templatesService->fetchTemplate("_views/search_index.tpl"));
        }

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

        return array_merge($queryService->encodeParameters($parameters), array(
            'q' => $this->buildSolrQuery($parameters),
            'fq' => empty($fq) ? '' : "{!tag=t}$fq",
            'sort' => $parameters['sort'] === 'latest' ? 'published desc' : ($parameters['sort'] === 'oldest' ? 'published asc' : 'score desc'),
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
        if (!array_key_exists('type', $parameters) || !array_key_exists($parameters['type'], $this->types)) {
            return;
        }

        $type = $parameters['type'];

        return sprintf('type:(%s)', is_array($this->types[$type]) ? implode(' OR ', $this->types[$type]) : $this->types[$type]);
    }

    public function errorAction()
    {
        $this->getResponse()->setHttpResponseCode(503);
    }
}
