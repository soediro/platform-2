<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Engine\Orm;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\SearchBundle\Engine\Orm\BaseDriver;
use Oro\Bundle\SearchBundle\Query\Query;

abstract class AbstractPdoTest extends \PHPUnit_Framework_TestCase
{
    const JOIN_ALIAS = 'item';

    /** @var QueryBuilder */
    protected $qb;

    /** @var BaseDriver */
    protected $driver;

    protected function setUp()
    {
        /** @var EntityManagerInterface $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $this->qb = new QueryBuilder($em);
        $this->qb->from('OroTestFrameworkBundle:Item', static::JOIN_ALIAS);
    }

    /**
     * @expectedException \Oro\Bundle\SearchBundle\Exception\ExpressionSyntaxError
     * @expectedExceptionMessage Unsupported operator "test"
     */
    public function testAddFilteringFieldException()
    {
        $this->driver->addFilteringField(
            $this->qb,
            42,
            [
                'condition' => 'test',
                'fieldType' => Query::TYPE_INTEGER,
                'fieldName' => 'field'
            ]
        );
    }

    /**
     * @dataProvider addFilteringFieldProvider
     *
     * @param string $condition
     * @param string|array $fieldName
     * @param string $expectedWhere
     * @param string $expectedConditionString
     */
    public function testAddFilteringField($condition, $fieldName, $expectedWhere, $expectedConditionString)
    {
        $searchCondition = [
            'condition' => $condition,
            'fieldType' => Query::TYPE_INTEGER,
            'fieldName' => $fieldName
        ];

        $this->assertEquals($expectedWhere, $this->driver->addFilteringField($this->qb, 42, $searchCondition));
        $this->assertEquals(new ArrayCollection([new Parameter('field42', $fieldName)]), $this->qb->getParameters());
        $this->assertEquals([], $this->qb->getDQLPart('join'), 'Should not be any additional joins.');
    }

    /**
     * @return array
     */
    public function addFilteringFieldProvider()
    {
        return [
            Query::OPERATOR_EXISTS => [
                'condition' => Query::OPERATOR_EXISTS,
                'fieldName' => 'myTest_value1',
                'expectedWhere' => 'integerField42.id IS NOT NULL',
                'expectedConditionString' => 'integerField42.field = :field42'
            ],
            Query::OPERATOR_NOT_EXISTS => [
                'condition' => Query::OPERATOR_NOT_EXISTS,
                'fieldName' => 'myTest_value1',
                'expectedWhere' => 'integerField42.id IS NULL',
                'expectedConditionString' => 'integerField42.field = :field42'
            ],
        ];
    }
}
