<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Http\Client;
use Newscoop\NewscoopException;
use Newscoop\SolrSearchPluginBundle\Search\SolrQuery;
use Newscoop\SolrSearchPluginBundle\Services\SolrHelperService;


class SearchController extends Controller
{
    /**
     * @Route("/search/", name="search")
     */
    public function searchAction(Request $request, $language = null)
    {
        $helper = $this->get('newscoop_solrsearch_plugin.helper');

        if ($helper->getConfigValue('index_type') == SolrHelperService::INDEX_AND_DATA) {

            $parameters = $request->query->all();
            $searchParam = trim($request->query->get('q'));

            // Check for webcode and redirect
            if (substr($searchParam, 0, 1) === '+' && $this->container->get('webcode')->findArticleByWebcode(substr($searchParam, 1)) !== null) {
                return $this->redirect(
                    sprintf('/%s', $searchParam), 302
                );
            }

            if (array_key_exists('q', $parameters) && $parameters['q'] === '') {

                $solrResponseBody = array();
            } else {

                $solrQuery = $this->encodeParameters($parameters, $language);
                $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');

                try {
                    $solrResponseBody = $queryService->find($solrQuery);
                } catch(\Exception $e) {
                    $request->query->set('error', $e->getMessage());

                    $response = $this->forward('NewscoopSolrSearchPluginBundle:Error:search', array(
                        'request' => $request
                    ));

                    return $response;
                }
            }
        }

        if (array_key_exists('format', $parameters) && $parameters['format'] == 'json') {

            $response = new JsonResponse($solrResponseBody);
        } else {

            $templatesService = $this->container->get('newscoop.templates.service');
            $smarty = $templatesService->getSmarty();

            if (isset($solrResponseBody)) {
                $smarty->assign('result', json_encode($solrResponseBody));
            }

            $response = new Response();
            $response->headers->set('Content-Type', 'text/html');
            $response->setContent($templatesService->fetchTemplate('search_index.tpl'));
        }

        return $response;
    }

    /**
     * Build solr params array
     *
     * @return array
     */
    protected function encodeParameters(array $parameters, $language = null)
    {
        $helper = $this->container->get('newscoop_solrsearch_plugin.helper');
        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');

        // Only needed for output to browser
        if (array_key_exists('format', $parameters)) {
            unset($solrParameters['format']);
        }

        $query = new SolrQuery($parameters);

        $language = $this->container->get('em')
            ->getRepository('Newscoop\Entity\Language')
            ->findOneByCode($language);
        if ($language instanceof \Newscoop\Entity\Language) {
            $query->core = $language->getRFC3066bis();
        }

        $fq = implode(' AND ', array_filter(array(
            $this->buildSolrTypeParam($parameters),
            $queryService->buildSolrDateParam($parameters),
            '-section:swissinfo', // filter en news
        )));

        $sort = 'score desc';
        if (array_key_exists('sort', $parameters)) {
            if ($parameters['sort'] === 'latest') {
                $query->sort = 'published desc';
            } elseif ($parameters['sort'] === 'oldest') {
                $query->sort = 'published asc';
            }
        }

        $query->q = $this->buildSolrQuery($parameters);
        $query->fq = empty($fq) ? '' : "{!tag=t}$fq";
        $query->facet = 'true';
        $query->{'facet.field'} = '{!ex=t}type';
        $query->spellcheck = 'true';

        // We don't want this
        $query->df = null;
        $query->fl = null;

        return $query;
    }

    /**
     * Build solr query
     *
     * @return string
     */
    private function buildSolrQuery($parameters)
    {
        $q = (array_key_exists('q', $parameters)) ? trim($parameters['q']) : sha1(__FILE__); // search for nonsense to show empty search result page

        $matches = array();
        if (preg_match('/^(author|topic):([^"]+)$/', $q, $matches)) {
            $q = sprintf('%s:%s', $matches[1], json_encode(trim($matches[2], '"')));
        }

        return $q;
    }

    /**
     * Build solr source filter
     *
     * @return string
     */
    private function buildSolrTypeParam($parameters)
    {
        $queryService = $this->get('newscoop_solrsearch_plugin.query_service');
        $typesConfig = $this->container->getParameter('types_search');
        $type = (array_key_exists('type', $parameters)) ? $parameters['type'] : null;

        if (!empty($type) && array_key_exists($type, $typesConfig)) {
            $types = (array) $typesConfig[$type];
        } else {
            $types = array();
            foreach ($typesConfig as $typeConfig) {
                $types = array_merge($types, (array) $typeConfig);
            }
        }

        return $queryService->buildSolrSingleValueParam('type', array_unique($types));
    }
}
