<?php
/**
 * @package Newscoop\ExamplePluginBundle
 * @author Paweł Mikołajczuk <pawel.mikolajczuk@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\EventListener;

use Newscoop\EventDispatcher\Events\CollectObjectsDataEvent;

class ListObjectsListener
{
    /**
     * Register plugin list objects in Newscoop
     *
     * @param  CollectObjectsDataEvent $event
     */
    public function registerObjects(CollectObjectsDataEvent $event)
    {
        $event->registerListObject('newscoop\solrsearchpluginbundle\templatelist\searchresultssolr', array(
            'class' => 'Newscoop\SolrSearchPluginBundle\TemplateList\SearchResultsSolr',
            'list' => 'search_result_solr',
            'url_id' => 'cnt',
        ));

        $event->registerObjectTypes('solr_result', array(
            'class' => '\Newscoop\SolrSearchPluginBundle\TemplateList\SolrResult'
        ));
    }
}
