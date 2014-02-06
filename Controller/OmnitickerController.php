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

class OmnitickerController extends Controller
{
    /**
     * @var array
     */
    private $sources = array(
        'tageswoche' => array('news', 'dossier', 'blog'),
        'twitter' => 'tweet',
        'agentur' => 'newswire',
        'link' => 'link',
        'en' => array('newswire'),
    );

    /**
     * @Route("/omniticker/")
     * @Route("/{language}/omniticker/")
     */
    public function omnitickerAction(Request $request, $language = null)
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
            $response->setContent($templatesService->fetchTemplate("_views/omniticker_index.tpl"));
        } elseif ($parameters['format'] === 'xml') {

            $templatesService = $this->container->get('newscoop.templates.service');
            $smarty = $templatesService->getSmarty();
            $smarty->assign('result', $solrResponseBody);

            $response = new Response();
            $response->headers->set('Content-Type', 'application/rss+xml'); // TODO: Was rss-xml, check if possible to change
            $response->setContent($templatesService->fetchTemplate("_views/omniticker_xml.tpl"));
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

        return array_merge($queryService->encodeParameters($parameters), array(
            'q' => '*:*',
            'fq' => implode(' AND ', array_filter(array(
                $this->buildSolrSectionParam($parameters),
                $this->buildSolrSourceParam($parameters),
                $queryService->buildSolrDateParam($parameters),
                (array_key_exists('source', $parameters) && $parameters['source'] === 'en') ? 'section:swissinfo' : null,
                '-switches:print',
            ))),
            'sort' => 'published desc',
            'spellcheck' => 'false',
        ));
    }

    /**
     * Build solr source filter
     *
     * @return string
     */
    private function buildSolrSourceParam($parameters)
    {
        $queryService = $this->container->get('newscoop_solrsearch_plugin.query_service');

        $sourcesConfig = $queryService->getConfig('types_omniticker');
        // TODO: Fix later
        // $sourcesConfig = $this->container->getParameter('SolrSearchPluginBundle');
        // $sourcesConfig = $sourcesConfig['application']['omniticker']['types'];
        $source = (array_key_exists('source', $parameters)) ? $parameters['source'] : null;

        if (!empty($source) && array_key_exists($source, $sourcesConfig)) {
            $sources = (array) $this->sources[$source];
        } else {
            $sources = array();
            foreach ($sourcesConfig as $types) {
                $sources = array_merge($sources, (array) $types);
            }
        }

        return sprintf('type:(%s)', implode(' OR ', array_unique($sources)));
    }

    /**
     * Build solr section filter
     *
     * @return string
     */
    private function buildSolrSectionParam($parameters)
    {
        $section = (array_key_exists('section', $parameters)) ? $parameters['section'] : null;
        if ($section !== null) {
            return sprintf('section:("%s")', json_encode($section));
        }
    }
}
