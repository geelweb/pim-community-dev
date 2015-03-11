<?php

namespace Pim\Component\ReferenceData\Repository;

use Doctrine\Common\Persistence\ObjectRepository;

/**
 * Reference data repository interface
 *
 * @author    Julien Janvier <jjanvier@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
interface ReferenceDataRepositoryInterface extends ObjectRepository
{
    const LIMIT_IF_NO_SEARCH = 20;

    /**
     * Returns an array of reference data ids and codes according to the search that was performed
     *
     *  return array(
     *      array('id' => 1, 'text' => 'Reference Data 1'),
     *      array('id' => 2, 'text' => 'Reference Data 2'),
     *  );
     *
     * @param string $search
     * @param array  $options
     *
     * Possible options are:
     *    limit: the limit of reference data to return (if no search if performed, self::LIMIT_IF_NO_SEARCH is used)
     *
     * @return array
     */
    public function findBySearch($search = null, array $options = []);
}
