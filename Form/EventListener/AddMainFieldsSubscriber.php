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
use Newscoop\SolrSearchPluginBundle\Services\SolrHelperService;

class AddMainFieldsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(FormEvents::PRE_SET_DATA => 'preSetData', FormEvents::PRE_SUBMIT => 'preSetData');
    }

    public function preSetData(FormEvent $event)
    {
        $data = $event->getData();
        $form = $event->getForm();

        if (is_array($data) && array_key_exists('enabled', $data) && $data['enabled'] == 1) {

            $options = $form->getConfig()->getOptions();

            $form
                ->add('index_type', 'choice', array(
                    'label' => 'plugin.solr.admin.form.label.index_type',
                    'choices' => array(
                        SolrHelperService::INDEX_ONLY => 'plugin.solr.admin.form.label.index_only',
                        SolrHelperService::INDEX_AND_DATA => 'plugin.solr.admin.form.label.index_and_data'
                    ),
                    'required' => true,
                    'attr' => array(
                        'help_text' => 'plugin.solr.admin.form.help_text.index_type'
                    )
                ))
                ->add('host', 'text', array(
                    'label' => 'plugin.solr.admin.form.label.host',
                    'required' => true,
                    'attr' => array('class' => 'auto-submit')
                ))
                ->add('port', 'text', array(
                    'label' => 'plugin.solr.admin.form.label.port',
                    'required' => true,
                    'attr' => array(
                        'class' => 'auto-submit',
                        'help_text' => 'plugin.solr.admin.form.help_text.port'
                    )
                ))
                ->add('query_uri', 'text', array(
                    'label' => 'plugin.solr.admin.form.label.query_uri',
                    'required' => true,
                ))
                ->add('update_uri', 'text', array(
                    'label' => 'plugin.solr.admin.form.label.update_uri',
                    'required' => true,
                ))
                ->add('indexables', 'choice', array(
                    'label' => 'plugin.solr.admin.form.label.indexables',
                    'choices' => $options['indexables'],
                    'required' => true,
                    'multiple' => true,
                    'expanded' => true,
                ))
                ->add('cron_interval', 'choice', array(
                    'label' => 'plugin.solr.admin.form.label.cron_interval',
                    'choices' => array(
                        '*/5 * * * *' => '5 minutes',
                        '*/15 * * * *' => '15 minutes',
                        '*/30 * * * *' => '30 minutes',
                        '0 */1 * * *' => '1 hour',
                        '0 */4 * * *' => '4 hours',
                        '0 0 */1 * *' => '1 day',
                        'custom' => 'custom',
                    ),
                    'required' => true,
                    'attr' => array(
                        'class' => 'auto-submit',
                        'help_text' => 'plugin.solr.admin.form.help_text.cron_interval'
                    )
                ));
        }
    }
}
