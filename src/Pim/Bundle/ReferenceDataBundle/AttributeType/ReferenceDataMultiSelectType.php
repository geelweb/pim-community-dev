<?php

namespace Pim\Bundle\ReferenceDataBundle\AttributeType;

use Pim\Bundle\CatalogBundle\AttributeType\OptionMultiSelectType;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\CatalogBundle\Validator\ConstraintGuesserInterface;
use Pim\Component\ReferenceData\Model\ConfigurationInterface;
use Pim\Component\ReferenceData\ConfigurationRegistryInterface;

/**
 * Reference data multi options (select) attribute type
 *
 * TODO-CR: do not extend OptionMultiSelectType
 *
 * @author    Julien Janvier <jjanvier@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ReferenceDataMultiSelectType extends OptionMultiSelectType
{
    /** @var ConfigurationRegistryInterface */
    protected $referenceDataRegistry;

    /**
     * Constructor
     *
     * @param string                         $backendType the backend type
     * @param string                         $formType the form type
     * @param ConstraintGuesserInterface     $constraintGuesser the form type
     * @param ConfigurationRegistryInterface $registry
     */
    public function __construct(
        $backendType,
        $formType,
        ConstraintGuesserInterface $constraintGuesser,
        ConfigurationRegistryInterface $registry
    ) {
        parent::__construct($backendType, $formType, $constraintGuesser);
        $this->referenceDataRegistry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareValueFormName(ProductValueInterface $value)
    {
        $referenceDataConf = $this->referenceDataRegistry->get($value->getAttribute()->getReferenceDataName());

        return $referenceDataConf->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function prepareValueFormOptions(ProductValueInterface $value)
    {
        $referenceDataConf = $this->referenceDataRegistry->get($value->getAttribute()->getReferenceDataName());
        $options           = parent::prepareValueFormOptions($value);
        $options['class']  = $referenceDataConf->getClass();

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function defineCustomAttributeProperties(AttributeInterface $attribute)
    {
        return parent::defineCustomAttributeProperties($attribute) + [
            'reference_data_name' => [
                'name'      => 'reference_data_name',
                'fieldType' => 'choice',
                'options'   => [
                    'choices'     => $this->getReferenceDataChoices(),
                    'required'    => true,
                    'multiple'    => false,
                    //TODO-CR: should be translatable
                    'empty_value' => 'Choose the reference data type',
                    'select2'     => true
                ],
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pim_reference_data_multiselect';
    }

    /**
     * @return array
     */
    protected function getReferenceDataChoices()
    {
        $choices = [];

        foreach ($this->referenceDataRegistry->all() as $configuration) {
            if (ConfigurationInterface::TYPE_MULTI === $configuration->getType()) {
                $choices[$configuration->getName()] = $configuration->getName();
            }
        }

        return $choices;
    }
}