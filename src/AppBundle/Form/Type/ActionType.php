<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\User;
use AppBundle\Form\Type\ChangeRouteType;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class ActionType extends AbstractType
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @param TokenStorage $storage
     */
    public function __construct(TokenStorage $storage)
    {
        $this->user = $storage->getToken()->getUser();
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Комментарии',
                'required' => false,
                'mapped' => false,
                'label_attr' => ['class' => 'subtitle']
            ])
            ->add('changeRoutes', CollectionType::class, [
                'entry_type' => ChangeRouteType::class,
                'allow_add' => true,
                'prototype' => true,
                'allow_delete' => true,
                'label' => false,
                'by_reference'  => false,
                'delete_empty' => true,
                'attr' => [
                    'data-toggle' => '',
                    'data-label' => 'Оперативное изменение маршрутов',
                    'custom_widget' => true
                ]
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'actionDescriptions'])
            ;
    }
    public function actionDescriptions(FormEvent $event)
    {
        if ($data = $event->getData() and $action = $event->getForm()->getData()) {
            $userName = $this->user->getFullName();
            if (!empty($data['description'])) {
                $description = $action->getDescription();
                $data['description'] = (!empty($description) ? $description . '<br/> ' : '') . '"' . $data['description'] . '" ' . $userName;
                $action->setDescription($data['description']);
            }
        }
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'action';
    }
}
