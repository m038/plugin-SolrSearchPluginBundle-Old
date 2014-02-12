<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\OmnitickerPluginBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Newscoop\Http\Client;
use Newscoop\NewscoopException;

class SearchController extends Controller
{
    /**
     * @Route("/search/", name="search")
     * @Route("/{language}/search/", name="search_lang")
     */
    public function searchAction(Request $request, $language = null)
    {
        if ($this->container->get('webcode')->findArticleByWebcode($request->query->get('q')) !== null) {

            return $this->redirect(
                sprintf('/%s', $request->get('q')), 302
            );
        }

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
        $solrResponseBody = $queryService->find($solrParameters);

        if (!array_key_exists('format', $parameters)) {

            $templatesService = $this->container->get('newscoop.templates.service');
            $smarty = $templatesService->getSmarty();
            $smarty->assign('result', json_encode($solrResponseBody));

            $response = new Response();
            $response->setContent($templatesService->fetchTemplate("_views/search_index.tpl"));
        } elseif ($parameters['format'] === 'json') {

            $response = new JsonResponse($solrResponseBody);
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
        $types = $queryService->getConfig('types_search');
        // TODO: Fix later
        // $types = $this->container->getParameter('SolrSearchPluginBundle');
        // $types = $types['application']['search']['types'];

        if (!array_key_exists('type', $parameters) || !array_key_exists($parameters['type'], $types)) {
            return;
        }

        $type = $parameters['type'];

        return sprintf('type:(%s)', is_array($types[$type]) ? implode(' OR ', $types[$type]) : $types[$type]);
    }
}
