<?php

namespace Pim\Bundle\BaseConnectorBundle\Reader\Doctrine;

use Akeneo\Bundle\BatchBundle\Item\AbstractConfigurableStepElement;
use Akeneo\Bundle\BatchBundle\Item\ItemReaderInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Validator\Constraints as Assert;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;
use Pim\Bundle\BaseConnectorBundle\Validator\Constraints\Channel as ChannelConstraint;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\CompletenessManager;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Pim\Bundle\CatalogBundle\Repository\ProductRepositoryInterface;

/**
 * Reads products one by one
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class PaginatedProductReader extends AbstractConfigurableStepElement implements ItemReaderInterface
{
    /**
     * Range of items to fetch from database
     */
    const LIMIT = 100;

    /**
     * @var string
     *
     * @Assert\NotBlank(groups={"Execution"})
     * @ChannelConstraint
     */
    protected $channel;

    /**
     * @var AbstractQuery
     */
    protected $query;

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @var array
     */
    protected $ids;

    /**
     * @var int
     */
    protected $readIndex = 1;

    /**
     * @var ProductInterface[]
     */
    protected $products = [];

    /**
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var boolean
     */
    protected $areSentProducts = true;

    /**
     * @var array
     */
    protected $results = array();

    /**
     * @var boolean
     */
    private $executed = false;

    /**
     * @param ProductRepositoryInterface $repository
     * @param ChannelManager             $channelManager
     * @param CompletenessManager        $completenessManager
     * @param MetricConverter            $metricConverter
     * @param EntityManager              $entityManager
     */
    public function __construct(
        ProductRepositoryInterface $repository,
        ChannelManager $channelManager,
        CompletenessManager $completenessManager,
        MetricConverter $metricConverter,
        EntityManager $entityManager
    ) {
        $this->entityManager       = $entityManager;
        $this->repository          = $repository;
        $this->channelManager      = $channelManager;
        $this->completenessManager = $completenessManager;
        $this->metricConverter     = $metricConverter;
    }

    /**
     * Set query used by the reader
     *
     * @param Doctrine\ORM\AbstractQuery $query
     *
     * @throws \InvalidArgumentException
     */
    public function setQuery($query)
    {
        if (!is_a($query, 'Doctrine\ORM\AbstractQuery', true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '$query must be a Doctrine\ORM\AbstractQuery instance, got "%s"',
                    is_object($query) ? get_class($query) : $query
                )
            );
        }
        $this->query = $query;
    }

    /**
     * Get query to execute
     *
     * @return Doctrine\ORM\AbstractQuery
     *
     * @throws ORMReaderException
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        if (!$this->executed) {
            $this->ids = $this->getIds();
        }

        if ($this->offset > count($this->ids) && $this->areSentProducts) {
            return null;
        } elseif ($this->areSentProducts) {
            $limit = $this->offset + self::LIMIT;
            $currentIds = array_slice($this->ids, $this->offset, $limit);
            $this->products = $this->repository->findByIds($currentIds);
            $this->offset = $limit;
        }

        if ($this->readIndex < count($this->products)) {
            $this->areSentProducts = false;
            $item = $this->products[$this->readIndex-1];
            $this->readIndex++;
        } else {
            $item = $this->products[$this->readIndex-1];
            $this->readIndex = 1;
            $this->areSentProducts = true;
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array(
            'channel' => array(
                'type'    => 'choice',
                'options' => array(
                    'choices'  => $this->channelManager->getChannelChoices(),
                    'required' => true,
                    'select2'  => true,
                    'label'    => 'pim_base_connector.export.channel.label',
                    'help'     => 'pim_base_connector.export.channel.help'
                )
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->executed = false;
        $this->query = false;
    }

    /**
     * Set channel
     * @param string $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * Get channel
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return array
     */
    protected function getIds()
    {
        $this->entityManager->clear();
        $this->executed = true;

        if (!is_object($this->channel)) {
            $this->channel = $this->channelManager->getChannelByCode($this->channel);
        }
        $this->query = $this->repository
            ->buildByChannelAndCompleteness($this->channel);

        $rootAlias = current($this->query->getRootAliases());
        $rootIdExpr = sprintf('%s.id', $rootAlias);

        $from = current($this->query->getDQLPart('from'));

        $this->query
            ->select($rootIdExpr)
            ->resetDQLPart('from')
            ->from($from->getFrom(), $from->getAlias(), $rootIdExpr)
            ->groupBy($rootIdExpr);

        $results = $this->query->getQuery()->getArrayResult();

        return array_keys($results);
    }
}
