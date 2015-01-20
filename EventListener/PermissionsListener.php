<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\EventListener;

use Newscoop\EventDispatcher\Events\PluginPermissionsEvent;
use Symfony\Component\Translation\Translator;

class PermissionsListener
{
    /**
     * Translator
     * @var Translator
     */
    protected $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Register plugin permissions in Newscoop ACL
     *
     * @param PluginPermissionsEvent $event
     */
    public function registerPermissions(PluginPermissionsEvent $event)
    {
        $event->registerPermissions($this->translator->trans('plugins.solr.permissions.label'), array(
            'plugin_solr_status' => $this->translator->trans('plugins.solr.permissions.status'),
            'plugin_solr_settings' => $this->translator->trans('plugins.solr.permissions.settings')
        ));
    }
}
