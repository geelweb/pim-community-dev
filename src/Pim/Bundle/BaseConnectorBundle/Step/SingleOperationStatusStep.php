<?php

namespace Pim\Bundle\BaseConnectorBundle\Step;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Step\AbstractStep;
use Pim\Bundle\EnrichBundle\MassEditAction\Handler\SingleOperationStepHandler;

/**
 * Step for mass edit
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class SingleOperationStatusStep extends AbstractStep
{
    /** @var array */
    protected $configuration;

    /** @var SingleOperationStepHandler */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepExecution $stepExecution)
    {
        $this->handler->setStepExecution($stepExecution);
        $this->handler->execute($this->configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration(array $config)
    {
        $this->configuration = $config;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurableStepElements()
    {
        return [];
    }

    /**
     * @return SingleOperationStepHandler
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @param SingleOperationStepHandler $handler
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;
    }
}