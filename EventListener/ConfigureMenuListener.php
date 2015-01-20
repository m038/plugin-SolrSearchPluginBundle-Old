<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\EventListener;

use Newscoop\NewscoopBundle\Event\ConfigureMenuEvent;
use Newscoop\Services\UserService;
use Symfony\Component\Translation\Translator;

class ConfigureMenuListener
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var UserService;
     */
    private $userService;

    /**
     * @param Translator $translator
     */
    public function __construct(Translator $translator, UserService $userService)
    {
        $this->translator = $translator;
        $this->userService = $userService;
    }

    /**
     * @param \Newscoop\NewscoopBundle\Event\ConfigureMenuEvent $event
     */
    public function onMenuConfigure(ConfigureMenuEvent $event)
    {
        $user = $this->userService->getCurrentUser();

        $hasStatus = $user->hasPermission('plugin_solr_status');
        $hasSettings = $user->hasPermission('plugin_solr_settings');

        if ($hasStatus || $hasSettings) {

            $statusUri = $event->getRouter()->generate('newscoop_solrsearchplugin_admin_status');
            $settingsUrl = $event->getRouter()->generate('newscoop_solrsearchplugin_admin_settings');

            $menu = $event->getMenu();
            $labelPlugins = $this->translator->trans('Plugins');
            $labelPluginName = $this->translator->trans('plugins.solr.menu.main');

            $menu[$labelPlugins]
                ->addChild(
                    $labelPluginName,
                    array('uri' => ($hasStatus) ? $statusUri : $settingsUrl)
                )
                ->setAttribute('rightdrop', true)
                ->setLinkAttribute('data-toggle', 'rightdrop');

            if ($hasStatus) {
                $menu[$labelPlugins][$labelPluginName]->addChild(
                    $this->translator->trans('plugins.solr.menu.status'),
                    array('uri' => $statusUri)
                );
            }

            if ($hasSettings) {
                $menu[$labelPlugins][$labelPluginName]->addChild(
                    $this->translator->trans('plugins.solr.menu.settings'),
                    array('uri' => $settingUri)
                );
            }
        }
    }
}
