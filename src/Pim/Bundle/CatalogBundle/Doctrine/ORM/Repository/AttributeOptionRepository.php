<?php

namespace Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository;

use Doctrine\ORM\EntityRepository;
use Pim\Bundle\CatalogBundle\Repository\AttributeOptionRepositoryInterface;
use Pim\Bundle\CatalogBundle\Repository\ReferableEntityRepositoryInterface;

/**
 * Repository for AttributeOption entity
 *
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeOptionRepository extends EntityRepository implements
    ReferableEntityRepositoryInterface,
    AttributeOptionRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getOption($id, $collectionId = null, array $options = array())
    {
        if (null === $collectionId) {
            throw new \InvalidArgumentException('Please supply attribute id as collectionId');
        }
        $option = $this->find($id);

        return $option && ($collectionId == $option->getAttribute()->getId())
            ? $option
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions($dataLocale, $collectionId = null, $search = '', array $options = array())
    {
        if (null === $collectionId) {
            throw new \InvalidArgumentException('Please supply attribute id as collectionId');
        }

        $qb = $this->createQueryBuilder('o')
            ->select('o.id, o.code, v.value AS label, a.properties')
            ->leftJoin('o.optionValues', 'v', 'WITH', 'v.locale=:locale')
            ->leftJoin('o.attribute', 'a')
            ->where('o.attribute=:attribute')
            ->orderBy('o.sortOrder')
            ->setParameter('locale', $dataLocale)
            ->setParameter('attribute', $collectionId);
        if ($search) {
            $qb->andWhere('v.value like :search OR o.code LIKE :search')
                ->setParameter('search', "$search%");
        }

        if (isset($options['ids'])) {
            $qb
                ->andWhere(
                    $qb->expr()->in('o.id', ':ids')
                )
                ->setParameter('ids', $options['ids']);
        }

        if (isset($options['limit'])) {
            $qb->setMaxResults((int) $options['limit']);
            if (isset($options['page'])) {
                $qb->setFirstResult((int) $options['limit'] * ((int) $options['page'] -1));
            }
        }

        $results = array();
        $autoSorting = null;
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            if (null === $autoSorting && isset($row['properties']['autoOptionSorting'])) {
                $autoSorting = $row['properties']['autoOptionSorting'];
            }
            $results[] = array(
                'id'   => $row['id'],
                'text' => $row['label'] ?: sprintf('[%s]', $row['code'])
            );
        }

        if ($autoSorting) {
            usort(
                $results,
                function ($first, $second) {
                    return strcasecmp($first['text'], $second['text']);
                }
            );
        }

        return array(
            'results' => $results
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionLabel($object, $dataLocale)
    {
        foreach ($object->getOptionValues() as $value) {
            if ($dataLocale === $value->getLocale() && null !== $value->getValue()) {
                return $value->getValue();
            }
        }

        return sprintf('[%s]', $object->getCode());
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionId($object)
    {
        return $object->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByIdentifier($code)
    {
        list($attributeCode, $optionCode) = explode('.', $code);

        return $this->createQueryBuilder('o')
            ->innerJoin('o.attribute', 'a')
            ->where('a.code=:attribute_code')
            ->andWhere('o.code=:option_code')
            ->setParameter('attribute_code', $attributeCode)
            ->setParameter('option_code', $optionCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierProperties()
    {
        return array('attribute', 'code');
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated will be removed in 1.4
     */
    public function getReferenceProperties()
    {
        return $this->getIdentifierProperties();
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated will be removed in 1.4
     */
    public function findByReference($code)
    {
        return $this->findOneByIdentifier($code);
    }
}
