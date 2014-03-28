<?php

namespace spec\Pim\Bundle\CatalogBundle\Doctrine\MongoDBODM\Sorter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * @require Doctrine\ODM\MongoDB\Query\Builder
 */
class InGroupSorterSpec extends ObjectBehavior
{
    function let(Builder $queryBuilder)
    {
        $this->beConstructedWith($queryBuilder, 'en_US', 'mobile');
    }

    function it_is_a_field_sorter()
    {
        $this->shouldBeAnInstanceOf('Pim\Bundle\CatalogBundle\Doctrine\FieldSorterInterface');
    }

    function it_adds_a_order_by_in_group_in_the_query(Builder $queryBuilder)
    {
        $queryBuilder->sort('normalizedData.in_group_12', 'desc')->willReturn($queryBuilder);

        $this->addFieldSorter('in_group_12', 'desc');
    }
}