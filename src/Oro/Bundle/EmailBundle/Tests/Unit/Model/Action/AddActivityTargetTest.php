<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Model\Action;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\ActivityListBundle\Provider\ActivityListChainProvider;
use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\Manager\EmailActivityManager;
use Oro\Bundle\EmailBundle\Model\Action\AddActivityTarget;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\Testing\ReflectionUtil;

class AddActivityTargetTest extends \PHPUnit\Framework\TestCase
{
    /** @var AddActivityTarget */
    protected $action;

    /** @var ContextAccessor */
    protected $contextAccessor;

    /** @var EmailActivityManager */
    protected $emailActivityManager;

    /** @var ActivityListChainProvider */
    protected $activityListChainProvider;

    /** @var EntityManager */
    protected $entityManager;

    protected function setUp(): void
    {
        $this->contextAccessor = $this->createMock('Oro\Component\ConfigExpression\ContextAccessor');

        $this->emailActivityManager = $this->getMockBuilder(
            'Oro\Bundle\EmailBundle\Entity\Manager\EmailActivityManager'
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->activityListChainProvider = $this->getMockBuilder(
            'Oro\Bundle\ActivityListBundle\Provider\ActivityListChainProvider'
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->entityManager = $this->getMockBuilder(
            'Doctrine\ORM\EntityManager'
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->action = new AddActivityTarget(
            $this->contextAccessor,
            $this->emailActivityManager,
            $this->activityListChainProvider,
            $this->entityManager
        );

        $this->action->setDispatcher($this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
    }

    public function testInitializeWithNamedOptions()
    {
        $options = [
            'email' => '$.email',
            'target_entity' => '$.target_entity',
            'attribute' => '$.attribute'
        ];

        $this->action->initialize($options);

        $this->assertEquals('$.email', ReflectionUtil::getPropertyValue($this->action, 'activityEntity'));
        $this->assertEquals('$.target_entity', ReflectionUtil::getPropertyValue($this->action, 'targetEntity'));
        $this->assertEquals('$.attribute', ReflectionUtil::getPropertyValue($this->action, 'attribute'));
    }

    public function testInitializeWithArrayOptions()
    {
        $options = [
            '$.email',
            '$.target_entity',
            '$.attribute'
        ];

        $this->action->initialize($options);

        $this->assertEquals('$.email', ReflectionUtil::getPropertyValue($this->action, 'activityEntity'));
        $this->assertEquals('$.target_entity', ReflectionUtil::getPropertyValue($this->action, 'targetEntity'));
        $this->assertEquals('$.attribute', ReflectionUtil::getPropertyValue($this->action, 'attribute'));
    }

    public function testInitializeWithNamedOptionsAndMissingAttribute()
    {
        $options = [
            'email' => '$.email',
            'target_entity' => '$.target_entity'
        ];

        $this->action->initialize($options);

        $this->assertEquals('$.email', ReflectionUtil::getPropertyValue($this->action, 'activityEntity'));
        $this->assertEquals('$.target_entity', ReflectionUtil::getPropertyValue($this->action, 'targetEntity'));
        $this->assertNull(ReflectionUtil::getPropertyValue($this->action, 'attribute'));
    }

    public function testInitializeWithArrayOptionsAndMissingAttribute()
    {
        $options = [
            '$.email',
            '$.target_entity'
        ];

        $this->action->initialize($options);

        $this->assertEquals('$.email', ReflectionUtil::getPropertyValue($this->action, 'activityEntity'));
        $this->assertEquals('$.target_entity', ReflectionUtil::getPropertyValue($this->action, 'targetEntity'));
        $this->assertNull(ReflectionUtil::getPropertyValue($this->action, 'attribute'));
    }

    public function testInitializeWithMissingRequiredOption()
    {
        $this->expectException(\Oro\Component\Action\Exception\InvalidParameterException::class);
        $options = [
            'email' => '$.email',
        ];

        $this->action->initialize($options);
    }

    public function testExecuteActionWithAttribute()
    {
        $options = [
            'email' => '$.email',
            'target_entity' => '$.target_entity',
            'attribute' => '$.attribute'
        ];

        $fakeContext = ['fake', 'things', 'are', 'here'];

        $this->contextAccessor->expects($this->at(0))
            ->method('getValue')
            ->with(
                $this->equalTo($fakeContext),
                $this->equalTo('$.email')
            )->will($this->returnValue($email = new Email()));

        $this->contextAccessor->expects($this->at(1))
            ->method('getValue')
            ->with(
                $this->equalTo($fakeContext),
                $this->equalTo('$.target_entity')
            )->will($this->returnValue($target = new User()));

        $this->contextAccessor->expects($this->at(2))
            ->method('setValue')
            ->with(
                $this->equalTo($fakeContext),
                $this->equalTo('$.attribute'),
                $this->equalTo(true)
            );

        $this->emailActivityManager->expects($this->once())
            ->method('addAssociation')
            ->with(
                $this->equalTo($email),
                $this->equalTo($target)
            )->will($this->returnValue(true));

        $this->action->initialize($options);
        $this->action->execute($fakeContext);
    }

    public function testExecuteActionWithoutAttribute()
    {
        $options = [
            'email' => '$.email',
            'target_entity' => '$.target_entity'
        ];

        $fakeContext = ['fake', 'things', 'are', 'here'];

        $this->contextAccessor->expects($this->at(0))
            ->method('getValue')
            ->with(
                $this->equalTo($fakeContext),
                $this->equalTo('$.email')
            )->will($this->returnValue($email = new Email()));

        $this->contextAccessor->expects($this->at(1))
            ->method('getValue')
            ->with(
                $this->equalTo($fakeContext),
                $this->equalTo('$.target_entity')
            )->will($this->returnValue($target = new User()));

        $this->contextAccessor->expects($this->never())
            ->method('setValue');

        $this->emailActivityManager->expects($this->once())
            ->method('addAssociation')
            ->with(
                $this->equalTo($email),
                $this->equalTo($target)
            )->will($this->returnValue(true));

        $this->action->initialize($options);
        $this->action->execute($fakeContext);
    }
}
