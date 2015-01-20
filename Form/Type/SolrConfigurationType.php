<?php
/**
 * @package   Newscoop\SolrSearchPluginBundle
 * @author    Mischa Gorinskat <mischa.gorinskat@sourcefabric.org>
 * @copyright 2015 Sourcefabric o.p.s.
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\SolrSearchPluginBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Newscoop\SolrSearchPluginBundle\Form\EventListener\AddArticleTypeFieldSubscriber;
use Newscoop\SolrSearchPluginBundle\Form\EventListener\AddCusomCronFieldSubscriber;
use Newscoop\SolrSearchPluginBundle\Form\EventListener\AddDefaultCoreFieldSubscriber;
use Newscoop\SolrSearchPluginBundle\Form\EventListener\AddMainFieldsSubscriber;

class SolrConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enabled', 'checkbox', array(
                'label' => 'plugin.solr.admin.form.label.enabled',
                'required' => false,
                'attr' => array('class' => 'auto-submit')
            ))
            ->add('save', 'submit', array(
                'label' => 'plugin.solr.admin.form.label.save',
            ));

        $builder
            ->addEventSubscriber(new AddMainFieldsSubscriber())
            ->addEventSubscriber(new AddDefaultCoreFieldSubscriber())
            ->addEventSubscriber(new AddArticleTypeFieldSubscriber())
            ->addEventSubscriber(new AddCusomCronFieldSubscriber());
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setRequired(array(
            'em', 'helper'
        ));

        $resolver->setDefaults(array(
            'indexables' => array(),
            'validation_groups' => false,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'intention'       => 'solr_configuration_type_form',
        ));
    }

    public function getName()
    {
        return 'solr_configuration_type';
    }
}
