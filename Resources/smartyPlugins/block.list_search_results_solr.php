<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Smarty block list search results
 *
 * @param array $params
 * @param string $content
 * @param Smarty $smarty
 * @param bool $repeat
 * @return string
 */
function smarty_block_list_search_results_solr($params, $content, $smarty, &$repeat)
{
    if (empty($params['q']) && !empty($_GET['q'])) {
        $params['q'] = $_GET['q'];
    } elseif (empty($params['q'])) {
        $repeat = false;
        return;
    }

    $context = $smarty->getTemplateVars('gimme');
    if (empty($params['language'])) {
        $params['language'] = $context->language->code;
    }

    $paginatorService = \Zend_Registry::get('container')->get('newscoop.listpaginator.service');
    $cacheService = \Zend_Registry::get('container')->get('newscoop.cache');

    if (!isset($content)) { // init

        $start = $context->next_list_start('Newscoop\SolrSearchPluginBundle\TemplateList\SearchResultsSolrList');
        $list = new \Newscoop\SolrSearchPluginBundle\TemplateList\SearchResultsSolrList(
            new \Newscoop\SolrSearchPluginBundle\TemplateList\SearchResultsSolrCriteria(),
            $paginatorService,
            $cacheService
        );

        $list->setPageParameterName($context->next_list_id($context->getListName($list)));
        $list->setPageNumber(\Zend_Registry::get('container')->get('request')->get($list->getPageParameterName(), 1));

        $list->getList($start, $params);
        if ($list->isEmpty()) {
            $context->setCurrentList($list, array());
            $context->resetCurrentList();
            $repeat = false;

            return null;
        }

        $context->setCurrentList($list, array(
            'publication',
            'language',
            'issue',
            'section',
            'article',
            'image',
            'attachment',
            'comment',
            'subtitle',
        ));
        $context->article = $context->current_search_result_solr_list->current;
        $repeat = true;
    } else { // next
        $context->current_search_result_solr_list->defaultIterator()->next();
        if (!is_null($context->current_search_result_solr_list->current)) {
            $context->article = $context->current_search_result_solr_list->current;
            $repeat = true;
        } else {
            $context->resetCurrentList();
            $repeat = false;
        }
    }

    return $content;
}
