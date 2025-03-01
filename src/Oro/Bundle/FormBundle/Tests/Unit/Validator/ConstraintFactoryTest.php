<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Validator;

use Oro\Bundle\FormBundle\Validator\ConstraintFactory;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ConstraintFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param string $expected
     * @param string $name
     * @param mixed  $options
     *
     * @dataProvider createDataProvider
     */
    public function testCreate($expected, $name, $options)
    {
        $factory = new ConstraintFactory();
        $this->assertEquals($expected, $factory->create($name, $options));
    }

    public function createDataProvider(): array
    {
        return [
            'short name'        => [
                'expectedClass' => new NotBlank(),
                'name'          => 'NotBlank',
                'options'       => null,
            ],
            'custom class name' => [
                'expectedClass' => new Length(['min' => 2, 'max' => 255, 'allowEmptyString' => false]),
                'name'          => Length::class,
                'options'       => ['min' => 2, 'max' => 255],
            ],
        ];
    }

    public function testCreateInvalidConstraint()
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new ConstraintFactory();
        $factory->create('test');
    }

    /**
     * @param array $constraints
     * @param array $expected
     *
     * @dataProvider constraintsProvider
     */
    public function testParse($constraints, $expected)
    {
        $factory = new ConstraintFactory();
        $this->assertEquals($expected, $factory->parse($constraints));
    }

    public function constraintsProvider(): array
    {
        return [
            'empty'              => [
                'constraints' => [],
                'expected'    => []
            ],
            'constraint object'  => [
                'constraints' => [
                    new NotBlank()
                ],
                'expected'    => [
                    new NotBlank()
                ]
            ],
            'by name'            => [
                'constraints' => [
                    [
                        'NotBlank' => null
                    ]
                ],
                'expected'    => [
                    new NotBlank()
                ]
            ],
            'by full class name' => [
                'constraints' => [
                    [
                        'Symfony\Component\Validator\Constraints\Length' => [
                            'min' => 1,
                            'max' => 2,
                        ]
                    ]
                ],
                'expected'    => [
                    new Length(['min' => 1, 'max' => 2, 'allowEmptyString' => false])
                ]
            ],
        ];
    }

    /**
     * @param array $constraints
     *
     * @dataProvider invalidConstraintsProvider
     */
    public function testParseWithInvalidArgument($constraints)
    {
        $this->expectException(\InvalidArgumentException::class);
        $factory = new ConstraintFactory();
        $factory->parse($constraints);
    }

    public function invalidConstraintsProvider(): array
    {
        return [
            [
                'constraints' => [
                    'test'
                ]
            ],
            [
                'constraints' => [
                    ['test' => null]
                ]
            ],
            [
                'constraints' => [
                    ['Test\UndefinedClass' => null]
                ]
            ],
            [
                'constraints' => [
                    new \stdClass()
                ]
            ],
        ];
    }
}
