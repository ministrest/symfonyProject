<?php

namespace AppBundle\Form\FilterType;

use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\TextFilterType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CustomEntityFilterType extends TextFilterType
{
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return TextType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'filter_entity';
    }
}
