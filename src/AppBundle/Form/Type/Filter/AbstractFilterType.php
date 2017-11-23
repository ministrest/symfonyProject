<?php

namespace AppBundle\Form\Type\Filter;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class AbstractFilterType extends AbstractType
{
    /**
     * Get list title
     * @return string
     */
    public function getTitle()
    {
        return '';
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);

        $view->vars['title'] = $this->getTitle();
        $view->vars['prefix'] = $this->getBlockPrefix();
    }
}
