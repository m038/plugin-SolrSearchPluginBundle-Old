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

class AddArticleTypeFieldSubscriber implements EventSubscriberInterface
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
            array_key_exists('indexables', $data) && in_array('indexer.article', $data['indexables'])
        ) {
            $options = $form->getConfig()->getOptions();

            $articleTypes = array();
            $articleTypesFromDb = $options['em']->getRepository('Newscoop\Entity\ArticleType')
                ->getAllTypes()
                ->getArrayResult();
            array_walk($articleTypesFromDb, function ($value) use (&$articleTypes) {
                $articleTypes[$value['name']] = $value['name'];
            });

            $form
                ->add('indexer_article_types', 'choice', array(
                    'label' => 'plugin.solr.admin.form.label.article_types',
                    'choices' => $articleTypes,
                    'required' => true,
                    'multiple' => true,
                    'expanded' => true
                ));
        }
    }
}
