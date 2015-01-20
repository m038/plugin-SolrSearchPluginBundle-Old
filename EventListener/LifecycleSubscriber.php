<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Translation\Translator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Newscoop\EventDispatcher\Events\GenericEvent;
use Newscoop\Services\Plugins\PluginsService;

/**
 * Event lifecycle management
 */
class LifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var \Newscoop\Services\Plugins\PluginsService
     */
    private $pluginsService;

    /**
     * @var \Symfony\Component\Translation\Translator
     */
    private $translator;

    /**
     * @param EntityManager  $em
     * @param PluginsService $pluginsService
     * @param Translator     $translator
     */
    public function __construct(EntityManager $em, PluginsService $pluginsService, Translator $translator) {
        $this->em = $em;
        $this->pluginsService = $pluginsService;
        $this->translator = $translator;
    }

    public function install(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->updateSchema($this->getClasses(), true);
        $this->em->getProxyFactory()->generateProxyClasses($this->getClasses(), __DIR__ . '/../../../../library/Proxy');

        $this->setPermissions();
    }

    public function update(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->updateSchema($this->getClasses(), true);
        $this->em->getProxyFactory()->generateProxyClasses($this->getClasses(), __DIR__ . '/../../../../library/Proxy');

        $this->setPermissions();
    }

    public function remove(GenericEvent $event)
    {
        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->dropSchema($this->getClasses(), true);

        $this->setPermissions();
    }

    public static function getSubscribedEvents()
    {
        return array(
            'plugin.install.m038_solr_search_plugin_bundle' => array('install', 1),
            'plugin.update.m038_solr_search_plugin_bundle' => array('update', 1),
            'plugin.remove.m038_solr_search_plugin_bundle' => array('remove', 1),
        );
    }

    private function getClasses(){
        return array(
          $this->em->getClassMetadata('Newscoop\SolrSearchPluginBundle\Entity\SolrConfig'),
        );
    }

    /**
     * Save plugin permissions into database
     */
    private function setPermissions()
    {
        $this->pluginsService->savePluginPermissions($this->pluginsService->collectPermissions($this->translator->trans('plugins.solr.permissions.label')));
    }

    /**
     * Remove plugin permissions
     */
    private function removePermissions()
    {
        $this->pluginsService->removePluginPermissions($this->pluginsService->collectPermissions($this->translator->trans('plugins.solr.permissions.label')));
    }
}
