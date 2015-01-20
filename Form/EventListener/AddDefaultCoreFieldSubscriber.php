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
use Symfony\Component\Form\FormError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddDefaultCoreFieldSubscriber implements EventSubscriberInterface
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
            array_key_exists('host', $data) && array_key_exists('port', $data)
        ) {
            $options = $form->getConfig()->getOptions();
            $helper = $options['helper'];

            if ($data['host'] != $helper->getConfigValue('host')) {
                $helper->setConfigValue('host', $data['host']);
            }
            if ($data['port'] != $helper->getConfigValue('port')) {
                $helper->setConfigValue('port', $data['port']);
            }

            try {
                $cores = $helper->getCoresFromSolr();
                $cores = array_combine($cores, $cores);

                $form
                    ->add('default_core', 'choice', array(
                        'label' => 'plugin.solr.admin.form.label.default_core',
                        'choices' => $cores,
                        'attr' => array(
                            'help_text' => 'plugin.solr.admin.form.help_text.default_core'
                        )
                    ));
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }
    }
}
