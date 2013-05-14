<?php

use Mockery as m;
use Basset\Asset;
use Basset\Factory\FilterFactory;

class AssetTest extends PHPUnit_Framework_TestCase {


    public function tearDown()
    {
        m::close();
    }


    public function setUp()
    {
        $this->files = m::mock('Illuminate\Filesystem\Filesystem');
        $this->filter = m::mock('Basset\Factory\FilterFactory', array(array(), array(), 'testing'))->shouldDeferMissing();

        $this->files->shouldReceive('lastModified')->with('path/to/public/foo/bar.sass')->andReturn('1368422603');

        $this->asset = new Asset($this->files, $this->filter, 'path/to/public/foo/bar.sass', 'foo/bar.sass');
        $this->asset->setOrder(1);
        $this->asset->setGroup('stylesheets');
    }


    public function testGetAssetProperties()
    {
        $this->assertEquals('foo/bar.sass', $this->asset->getRelativePath());
        $this->assertEquals('path/to/public/foo/bar.sass', $this->asset->getAbsolutePath());
        $this->assertEquals('foo/bar.css', $this->asset->getUsablePath());
        $this->assertEquals('foo/bar-2a4bdbebcbf798cb0b59078d98136e3d.css', $this->asset->getFingerprintedPath());
        $this->assertEquals('css', $this->asset->getUsableExtension());
        $this->assertInstanceOf('Illuminate\Support\Collection', $this->asset->getFilters());
        $this->assertEquals('stylesheets', $this->asset->getGroup());
    }


    public function testAssetsCanBeExcluded()
    {
        $this->assertTrue($this->asset->exclude()->isExcluded());
    }


    public function testCheckingOfAssetGroup()
    {
        $this->assertTrue($this->asset->isStylesheet());
        $this->assertFalse($this->asset->isJavascript());
    }


    public function testCheckingOfAssetGroupWhenNoGroupSupplied()
    {
        $this->asset->setGroup(null);
        $this->assertTrue($this->asset->isStylesheet());
    }


    public function testAssetCanBeRemotelyHosted()
    {
        $asset = new Asset($this->files, $this->filter, 'http://foo.com/bar.css', 'http://foo.com/bar.css');

        $this->assertTrue($asset->isRemote());
    }


    public function testAssetCanBeRemotelyHostedWithRelativeProtocol()
    {
        $asset = new Asset($this->files, $this->filter, '//foo.com/bar.css', '//foo.com/bar.css');

        $this->assertTrue($asset->isRemote());
    }


    public function testSettingCustomOrderOfAsset()
    {
        $this->asset->first();
        $this->assertEquals(1, $this->asset->getOrder());

        $this->asset->second();
        $this->assertEquals(2, $this->asset->getOrder());

        $this->asset->third();
        $this->assertEquals(3, $this->asset->getOrder());

        $this->asset->order(10);
        $this->assertEquals(10, $this->asset->getOrder());
    }


    public function testFiltersAreAppliedToAssets()
    {
        $this->filter->shouldReceive('make')->once()->with('FooFilter')->andReturn($filter = m::mock('Basset\Filter\Filter'));
        
        $filter->shouldReceive('setResource')->once()->with($this->asset)->andReturn(m::self());
        $filter->shouldReceive('getFilter')->once()->andReturn('FooFilter');

        $this->asset->apply('FooFilter');

        $filters = $this->asset->getFilters();
        
        $this->assertArrayHasKey('FooFilter', $filters->all());
        $this->assertInstanceOf('Basset\Filter\Filter', $filters['FooFilter']);
    }


    public function testFiltersArePreparedCorrectly()
    {
        $fooFilter = m::mock('Basset\Filter\Filter', array('FooFilter', array(), 'testing'))->shouldDeferMissing();
        $fooFilterInstance = m::mock('stdClass, Assetic\Filter\FilterInterface');
        $fooFilter->shouldReceive('getClassName')->once()->andReturn($fooFilterInstance);

        $barFilter = m::mock('Basset\Filter\Filter', array('BarFilter', array(), 'testing'))->shouldDeferMissing();
        $barFilter->shouldReceive('getClassName')->once()->andReturn(m::mock('stdClass, Assetic\Filter\FilterInterface'));

        $bazFilter = m::mock('Basset\Filter\Filter', array('BazFilter', array(), 'testing'))->shouldDeferMissing();
        $bazFilter->shouldReceive('getClassName')->once()->andReturn(m::mock('stdClass, Assetic\Filter\FilterInterface'));

        $quxFilter = m::mock('Basset\Filter\Filter', array('QuxFilter', array(), 'testing'))->shouldDeferMissing();
        $quxFilter->shouldReceive('getClassName')->once()->andReturn(m::mock('stdClass, Assetic\Filter\FilterInterface'));

        $vanFilter = m::mock('Basset\Filter\Filter', array('VanFilter', array(), 'testing'))->shouldDeferMissing();
        $vanFilterInstance = m::mock('stdClass, Assetic\Filter\FilterInterface');
        $vanFilter->shouldReceive('getClassName')->once()->andReturn($vanFilterInstance);

        $this->asset->apply($fooFilter);
        $this->asset->apply($barFilter)->whenAssetIsJavascript();
        $this->asset->apply($bazFilter)->whenEnvironmentIs('production');
        $this->asset->apply($quxFilter)->whenAssetIs('*.js');
        $this->asset->apply($vanFilter)->whenAssetIs('*.sass');

        $filters = $this->asset->prepareFilters();

        $this->assertTrue($filters->has('FooFilter'));
        $this->assertTrue($filters->has('VanFilter'));
        $this->assertFalse($filters->has('BarFilter'));
        $this->assertFalse($filters->has('BazFilter'));
        $this->assertFalse($filters->has('QuxFilter'));
    }


    public function testAssetIsBuiltCorrectly()
    {
        $contents = 'html { background-color: #fff; }';

        $instantiatedFilter = m::mock('Assetic\Filter\FilterInterface');
        $instantiatedFilter->shouldReceive('filterLoad')->once()->andReturn(null);
        $instantiatedFilter->shouldReceive('filterDump')->once()->andReturnUsing(function($asset) use ($contents)
        {
            $asset->setContent(str_replace('html', 'body', $contents));
        });

        $filter = m::mock('Basset\Filter\Filter')->shouldDeferMissing();
        $filter->shouldReceive('setResource')->once()->with($this->asset)->andReturn(m::self());
        $filter->shouldReceive('getFilter')->once()->andReturn('BodyFilter');
        $filter->shouldReceive('getInstance')->once()->andReturn($instantiatedFilter);


        $config = m::mock('Illuminate\Config\Repository');

        $this->files->shouldReceive('getRemote')->once()->with('path/to/public/foo/bar.sass')->andReturn($contents);

        $this->asset->apply($filter);

        $this->assertEquals('body { background-color: #fff; }', $this->asset->build());
    }


}