<?php

namespace Oro\Bundle\DistributionBundle\Tests\Unit\Routing;

use Oro\Bundle\DistributionBundle\Event\RouteCollectionEvent;
use Oro\Bundle\DistributionBundle\Routing\AbstractLoader;
use Oro\Bundle\DistributionBundle\Routing\SharedData;
use Oro\Component\Routing\Resolver\RouteOptionsResolverInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

abstract class AbstractLoaderTest extends \PHPUnit\Framework\TestCase
{
    /** @var KernelInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $kernel;

    /** @var RouteOptionsResolverInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $routeOptionsResolver;

    /** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $eventDispatcher;

    /** @var LoaderResolver */
    protected $loaderResolver;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->routeOptionsResolver = $this->createMock(RouteOptionsResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->loaderResolver = new LoaderResolver([new YamlFileLoader(new FileLocator())]);
    }

    protected function tearDown(): void
    {
        unset($this->kernel, $this->routeOptionsResolver, $this->eventDispatcher);
    }

    public function testSupportsFailed()
    {
        $this->assertFalse($this->getLoader()->supports(null, 'not_supported'));
    }

    /**
     * @dataProvider loadDataProvider
     */
    public function testLoad(array $expected)
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Fixtures';
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->expects($this->any())->method('getPath')->willReturn($dir);

        $this->kernel->expects($this->once())->method('getBundles')->willReturn([$bundle, $bundle]);

        $this->eventDispatcher->expects($this->once())->method('dispatch')->with(
            $this->callback(
                function (RouteCollectionEvent $event) use ($expected) {
                    $this->assertEquals($expected, $event->getCollection()->all());

                    return true;
                }
            ),
            $this->isType('string')
        );

        $this->assertEquals($expected, $this->getLoader()->load('file', 'type')->all());
    }

    public function testDispatchEventWithoutEventDispatcher()
    {
        $this->kernel->expects($this->once())->method('getBundles')->willReturn([]);
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->assertEquals(
            [],
            $this->getLoaderWithoutEventDispatcher()->load('file', 'type')->all()
        );
    }

    public function testLoadWithEmptyCache()
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Fixtures';
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->expects($this->any())->method('getPath')->willReturn($dir);
        $this->kernel->expects($this->once())->method('getBundles')->willReturn([$bundle]);

        $cache = $this->getMockBuilder(SharedData::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cache->expects($this->once())
            ->method('getRoutes')
            ->with($this->isType('string'))
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('setRoutes')
            ->with($this->isType('string'), $this->isInstanceOf(RouteCollection::class));

        $loader = $this->getLoader();
        $loader->setCache($cache);
        $loader->load('file', 'type')->all();
    }

    public function testLoadWithCachedData()
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Fixtures';
        $bundle = $this->createMock(BundleInterface::class);
        $bundle->expects($this->any())->method('getPath')->willReturn($dir);
        $this->kernel->expects($this->once())->method('getBundles')->willReturn([$bundle]);

        $cache = $this->getMockBuilder(SharedData::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cache->expects($this->once())
            ->method('getRoutes')
            ->with($this->isType('string'))
            ->willReturn(new RouteCollection());
        $cache->expects($this->never())
            ->method('setRoutes');

        $loader = $this->getLoader();
        $loader->setCache($cache);
        $loader->load('file', 'type')->all();
    }

    /**
     * @return AbstractLoader
     */
    abstract public function getLoader();

    /**
     * @return AbstractLoader
     */
    abstract public function getLoaderWithoutEventDispatcher();

    abstract public function loadDataProvider(): array;
}
