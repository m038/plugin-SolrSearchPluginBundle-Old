<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Form\EventListener;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddCusomCronFieldSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(FormEvents::PRE_SET_DATA => 'preSetData', FormEvents::PRE_SUBMIT => 'preSetData');
    }

    public function preSetData(FormEvent $event)
    {
        $data = $event->getData();
        $form = $event->getForm();

        if (
            is_array($data) && array_key_exists('enabled', $data) &&
            $data['enabled'] == 1 &&
            array_key_exists('cron_interval', $data) && $data['cron_interval'] == 'custom'
        ) {
            $form
                ->add('cron_custom', 'text', array(
                    'label' => 'plugin.solr.admin.form.label.cron_custom',
                    'required' => true,
                    'attr' => array(
                        'help_text' => 'plugin.solr.admin.form.help_text.cron_custom'
                    )
                ));
        }
    }
}
