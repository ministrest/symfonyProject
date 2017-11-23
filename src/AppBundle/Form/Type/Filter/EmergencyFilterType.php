<?php

namespace AppBundle\Form\Type\Filter;

use AppBundle\Entity\Vehicle;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\FormFilterBundle\Filter\FilterOperands;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\DateFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\DateTimeRangeFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\EntityFilterType;
use AppBundle\Form\FilterType\CustomEntityFilterType;
use AppBundle\Form\DataTransformer\StringToEntitiesTransformer;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\TextFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type\ChoiceFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use AppBundle\Entity\Emergency;
use AppBundle\Entity\Route;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderExecuterInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use AppBundle\Form\Type\GeozoneType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Doctrine\ORM\EntityManager;

class EmergencyFilterType extends AbstractFilterType
{
    /**
     * @var EntityManager $em
     */
    protected $em;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $stat_choices = array_flip(Emergency::$statuses);
        $stat_choices['Открыта'] = array('Подтверждена' => 1, 'Создана' => 0);
        $stat_choices['Закрыта'] = array('Отменена' => 4, 'Закрыта' => 5);

        $builder
            ->add('id', IntegerType::class, [
                'label' => "№ НС",
                'required' => false,
                "attr" => ["max" => "999999999"]
            ])
            ->add('operationalPlan', TextFilterType::class, [
                'label' => 'ОП',
                "attr" => ["class" => "js-example-basic-multiple"],
            ])
            ->add('photos', EntityFilterType::class, [
                'label' => 'Фотографии',
                'class' => 'AppBundle\Entity\Photo',
                "attr" => ["class" => "js-example-basic-multiple"],
            ])
            ->add('status', ChoiceFilterType::class, [
                'choices' => $stat_choices,
                'label' => 'Статус',
                "attr" => ["class" => "js-example-basic-multiple"],
                "multiple" => true,
                "data" => array_keys(Emergency::$defaultStatuses)
            ])
            ->add('vehicleType', EntityFilterType::class, [
                'label' => 'Вид ТС',
                'class' => 'AppBundle\Entity\VehicleType',
                'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                    if (!$values['value']) {
                        return null;
                    }

                    $query = $filterQuery->getQueryBuilder();
                    $aliases = $query->getAllAliases();
                    if (!in_array('vehicle', $aliases)) {
                        $query->leftJoin('e.vehicle', 'vehicle');
                    }

                    return $filterQuery->createCondition(
                        $filterQuery->getExpr()->eq('vehicle.type', ':p_vehicleType'),
                        ['p_vehicleType' => $values['value']]
                    );
                }
            ])
            ->add('createdTime', DateTimeRangeFilterType::class, [
                'label' => "Время создания",
                'required' => false,
                'data_extraction_method' => 'value_keys',
                'left_datetime_options' => array(
                    'widget' => 'single_text',
                    'html5' => false,
                    'attr' => ['class' => 'form_datetime'],
                    'label' => 'c '
                ),
                'right_datetime_options' => array(
                    'widget' => 'single_text',
                    'html5' => false,
                    'attr' => ['class' => 'form_datetime'],
                    'label' => ' по '
                ),
            ])
            ->add("stringCoordinates", HiddenType::class, [
                "label" => false
            ])
            ->add('geozone', GeozoneType::class, ['required' => false, 'label' => false]);
            
            $builder->get('route')->addViewTransformer(new StringToEntitiesTransformer($this->em, Route::class));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'validation_groups' => ['filtering']
        ]);
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'emergency_filter';
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return "Список НС";
    }
}
