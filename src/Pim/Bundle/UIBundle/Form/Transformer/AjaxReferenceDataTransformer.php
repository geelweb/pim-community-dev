<?php

namespace Pim\Bundle\UIBundle\Form\Transformer;

use Pim\Component\ReferenceData\Repository\ReferenceDataRepositoryInterface;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Ajax choice transformer for reference data
 *
 * @author    Julien Janvuer <jjanvier@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AjaxReferenceDataTransformer implements DataTransformerInterface
{
    /** @var ReferenceDataRepositoryInterface */
    protected $repository;

    /** @var array */
    protected $options;

    /**
     * Constructor
     *
     * @param ReferenceDataRepositoryInterface $repository
     * @param array                            $options
     */
    public function __construct(ReferenceDataRepositoryInterface $repository, array $options)
    {
        $this->repository = $repository;
        $this->options    = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if ($this->options['multiple']) {
            if (!$value) {
                return array();
            }

            $values = array();
            foreach (explode(',', $value) as $id) {
                $values[] = $this->repository->findOneBy(['id' => $id]);
            }

            return $values;
        }

        return $value ? $this->repository->findOneBy(['id' => $value]) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if ($this->options['multiple']) {
            $values = [];
            foreach ($value as $row) {
                $values[] = $row->getId();
            }

            return implode(',', $values);
        }

        return $value ? $value->getId() : '';
    }

    /**
     * Returns the labels corresponding to the given value
     *
     * @param mixed $value
     *
     * @return array
     */
    public function getOptions($value)
    {
        if ($this->options['multiple']) {
            $options = array();

            foreach ($value as $entity) {
                $options[] = ['id' => $entity->getId(), 'text' => $entity->getCode()];
            }

            return $options;
        }

        return $value
            ? ['id' => $value->getId(), 'text' => $value->getCode()]
            : null;
    }
}
