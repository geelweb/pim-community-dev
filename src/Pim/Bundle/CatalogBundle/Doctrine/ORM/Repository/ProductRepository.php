<?php

namespace Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository;

use Akeneo\Bundle\StorageUtilsBundle\Doctrine\ORM\Repository\CursorableRepositoryInterface;
use Akeneo\Bundle\StorageUtilsBundle\Repository\IdentifiableObjectRepositoryInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Pim\Bundle\CatalogBundle\Doctrine\ORM\QueryBuilderUtility;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Model\AttributeOptionInterface;
use Pim\Bundle\CatalogBundle\Model\ChannelInterface;
use Pim\Bundle\CatalogBundle\Model\GroupInterface;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\CatalogBundle\Query\Filter\Operators;
use Pim\Bundle\CatalogBundle\Query\ProductQueryBuilderFactoryInterface;
use Pim\Bundle\CatalogBundle\Repository\AttributeRepositoryInterface;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;
use Pim\Bundle\CatalogBundle\Repository\ReferableEntityRepositoryInterface;

/**
 * Product repository
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductRepository extends EntityRepository implements
    ProductRepositoryInterface,
    IdentifiableObjectRepositoryInterface,
    ReferableEntityRepositoryInterface,
    CursorableRepositoryInterface
{
    /** @var ProductQueryBuilderFactoryInterface */
    protected $queryBuilderFactory;

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    /**
     * {@inheritdoc}
     */
    public function setProductQueryBuilderFactory(ProductQueryBuilderFactoryInterface $factory)
    {
        $this->queryBuilderFactory = $factory;
    }

    /**
     * Set attribute repository
     *
     * @param AttributeRepositoryInterface $attributeRepository
     *
     * @return ProductRepository
     */
    public function setAttributeRepository(AttributeRepositoryInterface $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildByScope($scope)
    {
        $productQb = $this->queryBuilderFactory->create();
        $qb = $productQb->getQueryBuilder();
        $this->addJoinToValueTables($qb);
        $rootAlias = current($qb->getRootAliases());
        $qb
            ->andWhere(
                $qb->expr()->eq($rootAlias.'.enabled', ':enabled')
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('Value.scope', ':scope'),
                    $qb->expr()->isNull('Value.scope')
                )
            )
            ->setParameter('enabled', true)
            ->setParameter('scope', $scope);

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function buildByChannelAndCompleteness(ChannelInterface $channel)
    {
        $scope = $channel->getCode();
        $qb = $this->buildByScope($scope);
        $rootAlias = current($qb->getRootAliases());
        $expression =
            'pCompleteness.product = '.$rootAlias.' AND '.
            $qb->expr()->eq('pCompleteness.ratio', '100').' AND '.
            $qb->expr()->eq('pCompleteness.channel', $channel->getId());

        $rootEntity          = current($qb->getRootEntities());
        $completenessMapping = $this->_em->getClassMetadata($rootEntity)
            ->getAssociationMapping('completenesses');
        $completenessClass   = $completenessMapping['targetEntity'];
        $qb->innerJoin(
            $completenessClass,
            'pCompleteness',
            'WITH',
            $expression
        );

        $treeId = $channel->getCategory()->getId();
        $expression = $qb->expr()->eq('pCategory.root', $treeId);
        $qb->innerJoin(
            $rootAlias.'.categories',
            'pCategory',
            'WITH',
            $expression
        );

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function findByIds(array $ids)
    {
        $qb = $this->createQueryBuilder('Product');
        $this->addJoinToValueTables($qb);
        $rootAlias = current($qb->getRootAliases());
        $qb->andWhere(
            $qb->expr()->in($rootAlias.'.id', $ids)
        );

        return $qb->getQuery()->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllForVariantGroup(GroupInterface $variantGroup, array $criteria = array())
    {
        $qb = $this->createQueryBuilder('Product');

        $qb
            ->where(':variantGroup MEMBER OF Product.groups')
            ->setParameter('variantGroup', $variantGroup);

        $index = 0;
        foreach ($criteria as $item) {
            $code = $item['attribute']->getCode();
            $qb
                ->innerJoin(
                    'Product.values',
                    sprintf('Value_%s', $code),
                    'WITH',
                    sprintf('Value_%s.attribute = ?%d AND Value_%s.option = ?%d', $code, ++$index, $code, ++$index)
                )
                ->setParameter($index - 1, $item['attribute'])
                ->setParameter($index, $item['option']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllWithAttribute(AttributeInterface $attribute)
    {
        return $this
            ->createQueryBuilder('p')
            ->leftJoin('p.values', 'value')
            ->leftJoin('value.attribute', 'attribute')
            ->where('attribute=:attribute')
            ->setParameter('attribute', $attribute)
            ->getQuery()
            ->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllWithAttributeOption(AttributeOptionInterface $option)
    {
        $backendType = $option->getAttribute()->getBackendType();

        $qb = $this
            ->createQueryBuilder('p')
            ->leftJoin('p.values', 'value')
            ->leftJoin(sprintf('value.%s', $backendType), 'option');

        if ('options' === $backendType) {
            $qb->where(
                $qb->expr()->in('option', ':option')
            );
        } else {
            $qb->where('option=:option');
        }

        return $qb
            ->setParameter('option', $option)
            ->getQuery()
            ->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getFullProduct($id)
    {
        $qb = $this->getFullProductQB();

        return $qb
            ->where('p.id=:id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getFullProducts(array $productIds, array $attributeIds = array())
    {
        $qb = $this->getFullProductQB();
        $qb
            ->addSelect('c, assoc, g')
            ->leftJoin('v.attribute', 'a', $qb->expr()->in('a.id', $attributeIds))
            ->leftJoin('p.categories', 'c')
            ->leftJoin('p.associations', 'assoc')
            ->leftJoin('p.groups', 'g')
            ->where($qb->expr()->in('p.id', $productIds));

        return $qb->getQuery()->execute();
    }

    /**
     * Get full product query builder
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getFullProductQB()
    {
        return $this
            ->createQueryBuilder('p')
            ->select('p, f, v, pr, m, o, os')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.values', 'v')
            ->leftJoin('v.prices', 'pr')
            ->leftJoin('v.media', 'm')
            ->leftJoin('v.option', 'o')
            ->leftJoin('v.options', 'os');
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierProperties()
    {
        return array($this->attributeRepository->getIdentifierCode());
    }

    /**
     * Returns the ProductValue class
     *
     * @return string
     */
    protected function getValuesClass()
    {
        return $this->getClassMetadata()
            ->getAssociationTargetClass('values');
    }

    /**
     * Returns the Attribute class
     *
     * @return string
     */
    protected function getAttributeClass()
    {
        return $this->getEntityManager()
            ->getClassMetadata($this->getValuesClass())
            ->getAssociationTargetClass('attribute');
    }

    /**
     * @return QueryBuilder
     */
    public function createDatagridQueryBuilder()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from($this->_entityName, 'p', 'p.id');

        return $qb;
    }

    /**
     * @return QueryBuilder
     */
    public function createGroupDatagridQueryBuilder()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from($this->_entityName, 'p', 'p.id');

        $isCheckedExpr =
            'CASE WHEN ' .
            '(:currentGroup MEMBER OF p.groups '.
            'OR p.id IN (:data_in)) AND p.id NOT IN (:data_not_in) '.
            'THEN true ELSE false END';
        $qb
            ->addSelect($isCheckedExpr.' AS is_checked');

        return $qb;
    }

    /**
     * @return QueryBuilder
     */
    public function createVariantGroupDatagridQueryBuilder()
    {
        $qb = $this->createGroupDatagridQueryBuilder();
        $qb->andWhere($qb->expr()->in('p.id', ':productIds'));

        return $qb;
    }

    /**
     * @return QueryBuilder
     */
    public function createAssociationDatagridQueryBuilder()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from($this->_entityName, 'p', 'p.id');

        $qb
            ->leftJoin(
                'Pim\Bundle\CatalogBundle\Model\Association',
                'pa',
                'WITH',
                'pa.associationType = :associationType AND pa.owner = :product AND p MEMBER OF pa.products'
            );

        $qb->andWhere($qb->expr()->neq('p', ':product'));

        $isCheckedExpr =
            'CASE WHEN (pa IS NOT NULL OR p.id IN (:data_in)) AND p.id NOT IN (:data_not_in) ' .
            'THEN true ELSE false END';

        $isAssociatedExpr = 'CASE WHEN pa IS NOT NULL THEN true ELSE false END';

        $qb
            ->addSelect($isCheckedExpr.' AS is_checked')
            ->addSelect($isAssociatedExpr.' AS is_associated');

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function valueExists(ProductValueInterface $value)
    {
        $criteria = array(
            'attribute' => $value->getAttribute(),
            $value->getAttribute()->getBackendType() => $value->getData()
        );
        $result = $this->getEntityManager()->getRepository(get_class($value))->findBy($criteria);

        return (
            (0 !== count($result)) &&
            !(1 === count($result) && $value === ($result instanceof \Iterator ? $result->current() : current($result)))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getEligibleProductIdsForVariantGroup($variantGroupId)
    {
        $sql = 'SELECT count(ga.attribute_id) as nb '.
            'FROM pim_catalog_group_attribute as ga '.
            'WHERE ga.group_id = :groupId;';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('groupId', $variantGroupId);
        $stmt->execute();
        $nbAxes = $stmt->fetch()['nb'];

        $elligibleProductsSQL = 'SELECT v.entity_id as product_id '.
            'FROM pim_catalog_group_attribute as ga '.
            'LEFT JOIN %product_value_table% as v ON v.attribute_id = ga.attribute_id '.
            'WHERE ga.group_id = :groupId '.
            'GROUP BY v.entity_id '.
            'having count(v.option_id) = :nbAxes';

        $alreadyInGroupSQL = 'SELECT p.id as product_id ' .
            'FROM pim_catalog_product as p ' .
            'JOIN pim_catalog_group_product as gp on p.id = gp.product_id ' .
            'JOIN pim_catalog_group as g on g.id = gp.group_id ' .
            'JOIN pim_catalog_group_type as gt on gt.id = g.type_id ' .
            'WHERE gt.code = "VARIANT" ' .
            'AND g.id != :groupId';

        $sql = sprintf(
            'SELECT * FROM (%s) as p WHERE p.product_id NOT IN (%s);',
            $elligibleProductsSQL,
            $alreadyInGroupSQL
        );
        $sql = QueryBuilderUtility::prepareDBALQuery($this->_em, $this->_entityName, $sql);

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('groupId', $variantGroupId);
        $stmt->bindValue('nbAxes', $nbAxes);
        $stmt->execute();
        $results = $stmt->fetchAll();
        $productIds = array_map(
            function ($row) {
                return $row['product_id'];
            },
            $results
        );

        return $productIds;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByIdentifier($identifier)
    {
        $pqb = $this->queryBuilderFactory->create();
        $qb = $pqb->getQueryBuilder();
        $attribute = $this->getIdentifierAttribute();
        $pqb->addFilter($attribute->getCode(), Operators::EQUALS, $identifier);
        $result = $qb->getQuery()->execute();

        return reset($result);
    }

    /**
     * {@inheritdoc}
     */
    public function findOneById($id)
    {
        $pqb = $this->queryBuilderFactory->create();
        $pqb->addFilter('id', '=', $id);
        $qb = $pqb->getQueryBuilder();
        $result = $qb->getQuery()->execute();

        return reset($result);
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByWithValues($id)
    {
        $productQb = $this->queryBuilderFactory->create();
        $qb = $productQb->getQueryBuilder();
        $rootAlias = current($qb->getRootAliases());
        $this->addJoinToValueTables($qb);
        $qb->leftJoin('Attribute.translations', 'AttributeTranslations');
        $qb->leftJoin('Attribute.availableLocales', 'AttributeLocales');
        $qb->addSelect('Value');
        $qb->addSelect('Attribute');
        $qb->addSelect('AttributeTranslations');
        $qb->addSelect('AttributeLocales');
        $qb->leftJoin('Attribute.group', 'AttributeGroup');
        $qb->addSelect('AttributeGroup');
        $qb->leftJoin('AttributeGroup.translations', 'AGroupTranslations');
        $qb->addSelect('AGroupTranslations');
        $qb->andWhere(
            $qb->expr()->eq($rootAlias.'.id', $id)
        );

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Add join to values tables
     *
     * @param QueryBuilder $qb
     */
    protected function addJoinToValueTables(QueryBuilder $qb)
    {
        $qb->leftJoin(current($qb->getRootAliases()).'.values', 'Value')
            ->leftJoin('Value.attribute', 'Attribute')
            ->leftJoin('Value.options', 'ValueOption')
            ->leftJoin('ValueOption.optionValues', 'AttributeOptionValue');
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableAttributeIdsToExport(array $productIds)
    {
        $qb = $this->createQueryBuilder('p');
        $qb
            ->select('a.id')
            ->innerJoin('p.values', 'v')
            ->innerJoin('v.attribute', 'a')
            ->where($qb->expr()->in('p.id', $productIds))
            ->groupBy('a.id');

        $attributes = $qb->getQuery()->getArrayResult();
        $attributeIds = array();
        foreach ($attributes as $attribute) {
            $attributeIds[] = $attribute['id'];
        }

        return $attributeIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectManager()
    {
        return $this->getEntityManager();
    }

    /**
     * Return the identifier attribute
     *
     * @return AttributeInterface|null
     */
    protected function getIdentifierAttribute()
    {
        return $this->attributeRepository->findOneBy(['attributeType' => 'pim_catalog_identifier']);
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

    /**
     * {@inheritdoc}
     */
    public function getProductsByGroup(GroupInterface $group, $maxResults)
    {
        $products = $this
            ->createQueryBuilder('p')
            ->innerJoin('p.groups', 'g', 'WITH', 'g=:group')
            ->setParameter('group', $group)
            ->getQuery()
            ->setMaxResults($maxResults)
            ->execute();

        return $products;
    }

    /**
     * {@inheritdoc}
     */
    public function getProductCountByGroup(GroupInterface $group)
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p)')
            ->innerJoin('p.groups', 'g', 'WITH', 'g=:group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }
}
