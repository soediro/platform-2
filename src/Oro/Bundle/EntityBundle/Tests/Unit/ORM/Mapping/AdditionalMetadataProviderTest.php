<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM\Mapping;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Oro\Bundle\EntityBundle\ORM\Mapping\AdditionalMetadataProvider;

class AdditionalMetadataProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var AdditionalMetadataProvider */
    private $additionalMetadataProvider;

    /** @var ClassMetadataFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $metadataFactory;

    protected function setUp(): void
    {
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getMetadataFactory')
            ->willReturn($this->metadataFactory);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->any())
            ->method('getManager')
            ->willReturn($em);

        $this->additionalMetadataProvider = new AdditionalMetadataProvider(
            $registry,
            new ArrayCache()
        );
    }

    public function testGetInversedUnidirectionalAssociationMappings()
    {
        $entityMetadata = new ClassMetadata('Namespace\EntityName');

        $fooMetadata = new ClassMetadata('Namespace\\FooEntity');
        $fooMetadata->associationMappings = [
            'foo_association' => [
                'fieldName' => 'foo_association',
                'type' => ClassMetadata::ONE_TO_MANY,
                'targetEntity' => 'Namespace\EntityName',
            ],
        ];

        $barMetadata = new ClassMetadata('Namespace\\BarEntity');
        $barMetadata->associationMappings = [
            'bar_association' => [
                'fieldName' => 'bar_association',
                'type' => ClassMetadata::ONE_TO_MANY,
                'targetEntity' => 'Namespace\EntityName',
            ],
            'skipped_many_to_many' => [
                'fieldName' => 'skipped_many_to_many',
                'type' => ClassMetadata::MANY_TO_MANY,
                'targetEntity' => 'Namespace\EntityName'
            ],
            'skipped_mapped_by' => [
                'fieldName' => 'skipped_mapped_by',
                'mappedBy' => 'Namespace\EntityName',
                'targetEntity' => 'Namespace\EntityName',
            ],
        ];

        $fooBarMetadata = new ClassMetadata('Namespace\\FooBarEntity');
        $fooBarMetadata->associationMappings = [
            'bar_association' => [
                'fieldName' => 'bar_association',
                'type' => ClassMetadata::ONE_TO_ONE,
                'targetEntity' => 'Namespace\EntityName',
            ],
        ];

        $allMetadata = [
            $entityMetadata,
            $fooMetadata,
            $barMetadata,
            $fooBarMetadata,
        ];

        $this->metadataFactory->expects($this->once())
            ->method('getAllMetadata')
            ->willReturn($allMetadata);

        $expectedMetadata = [
            [
                'fieldName' => 'foo_association',
                'type' => ClassMetadata::ONE_TO_MANY,
                'targetEntity' => 'Namespace\\EntityName',
                'mappedBySourceEntity' => false,
                '_generatedFieldName' => 'Namespace_FooEntity_foo_association',
            ],
            [
                'fieldName' => 'bar_association',
                'type' => ClassMetadata::ONE_TO_MANY,
                'targetEntity' => 'Namespace\\EntityName',
                'mappedBySourceEntity' => false,
                '_generatedFieldName' => 'Namespace_BarEntity_bar_association',
            ],
            [
                'fieldName' => 'bar_association',
                'type' => ClassMetadata::ONE_TO_ONE,
                'targetEntity' => 'Namespace\\EntityName',
                'mappedBySourceEntity' => false,
                '_generatedFieldName' => 'Namespace_FooBarEntity_bar_association',
            ],
        ];

        $this->assertEquals(
            $expectedMetadata,
            $this->additionalMetadataProvider->getInversedUnidirectionalAssociationMappings('Namespace\EntityName')
        );
    }
}
