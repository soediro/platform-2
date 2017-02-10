<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Extension;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\ActionBundle\Button\ButtonContext;
use Oro\Bundle\ActionBundle\Provider\CurrentApplicationProviderInterface;
use Oro\Bundle\ActionBundle\Provider\RouteProviderInterface;

use Oro\Bundle\WorkflowBundle\Extension\AbstractButtonProviderExtension;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\TransitionManager;
use Oro\Bundle\WorkflowBundle\Model\WorkflowRegistry;

abstract class AbstractTransitionButtonProviderExtensionTestCase extends \PHPUnit_Framework_TestCase
{
    /** @var WorkflowRegistry|\PHPUnit_Framework_MockObject_MockObject */
    protected $workflowRegistry;

    /** @var RouteProviderInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $routeProvider;

    /** @var AbstractButtonProviderExtension */
    protected $extension;

    /** @var  CurrentApplicationProviderInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $applicationProvider;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->workflowRegistry = $this->getMockBuilder(WorkflowRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->routeProvider = $this->createMock(RouteProviderInterface::class);

        $this->applicationProvider = $this->getMockBuilder(CurrentApplicationProviderInterface::class)
            ->disableOriginalConstructor()->getMock();

        $this->extension = $this->createExtension();
        $this->extension->setApplicationProvider($this->applicationProvider);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($this->workflowRegistry, $this->routeProvider, $this->extension, $this->applicationProvider);
    }


    /**
     * @return string
     */
    abstract protected function getApplication();

    /**
     * @return AbstractButtonProviderExtension
     */
    abstract protected function createExtension();

    /**
     * @param string $entityClass
     *
     * @return ButtonContext
     */
    protected function getButtonContext($entityClass)
    {
        $context = new ButtonContext();
        $context->setEntity($entityClass)
            ->setEnabled(true)
            ->setUnavailableHidden(false);

        return $context;
    }

    /**
     * @param array $transitions
     * @param string $method
     *
     * @return TransitionManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getTransitionManager(array $transitions, $method)
    {
        $manager = $this->createMock(TransitionManager::class);
        $manager->expects($this->any())
            ->method($method)
            ->willReturn(new ArrayCollection($transitions));

        return $manager;
    }

    /**
     * @param string $name
     *
     * @return Transition
     */
    protected function getTransition($name)
    {
        $transition = new Transition();
        $transition->setName($name);

        return $transition;
    }
}
