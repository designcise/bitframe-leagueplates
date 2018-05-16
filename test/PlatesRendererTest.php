<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2018 Daniyal Hamid (https://designcise.com)
 *
 * @author    Zend Framework
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 *
 * @license   https://github.com/zendframework/zend-expressive-template/blob/master/LICENSE.md New BSD License
 */

namespace BitFrame\Test;

use \PHPUnit\Framework\TestCase;

use \League\Plates\Engine;

use \BitFrame\Renderer\{PlatesRenderer, TemplatePath};

/**
 * @covers \BitFrame\Renderer\PlatesRenderer
 */
class PlatesRendererTest extends TestCase
{
    /** @var \BitFrame\Renderer\PlatesRenderer */
    private $tpl;
    
    private static $assetDir = __DIR__ . '/Asset';
    
    public function setUp()
    {
        $this->tpl = new PlatesRenderer(self::$assetDir, 'tpl');
    }
    
    public function testCanProvideEngineAtInstantiation()
    {
        $renderer = new PlatesRenderer(null);
        $this->assertInstanceOf(PlatesRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }
    
    public function testCanAddPath()
    {
        $renderer = new PlatesRenderer(null);
        $renderer->addPath(self::$assetDir);
        $paths = $renderer->getPaths();
        
        $this->assertInternalType('array', $paths);
        $this->assertCount(1, $paths);
        $this->assertTemplatePath(self::$assetDir, $paths[0]);
        $this->assertTemplatePathString(self::$assetDir, $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
        
        return $renderer;
    }
    
    public function testNullDirectory()
    {
        $renderer = new PlatesRenderer(null, 'tpl');
        $paths = $renderer->getPaths();
        
        $this->assertEquals(null, $renderer->getEngine()->getDirectory());
        $this->assertCount(0, $paths);
    }
    
    public function testSetDefaultDirectory()
    {
        $dir = __DIR__;
        $renderer = new PlatesRenderer($dir);
        $paths = $renderer->getPaths();
        
        $this->assertEquals($dir, $renderer->getEngine()->getDirectory());
        $this->assertCount(1, $paths);
    }
    
    /**
     * @param PlatesRenderer $renderer
     * @depends testCanAddPath
     */
    public function testAddingSecondPathWithoutNamespaceIsANoopAndRaisesWarning($renderer)
    {
        $paths = $renderer->getPaths();
        $path  = array_shift($paths);
        
        $error = false;

        set_error_handler(function ($e, $message) use (&$error) {
            $error = true;
            $this->assertContains('duplicate', $message);
            return true;
        }, E_USER_WARNING);

        $renderer->addPath(__DIR__);
        restore_error_handler();

        $this->assertTrue($error, 'Error handler was not triggered when calling addPath() multiple times');

        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertCount(1, $paths);

        $test = array_shift($paths);
        $this->assertEqualTemplatePath($path, $test);
    }
    
    public function testCanAddPathWithNamespace()
    {
        $renderer = new PlatesRenderer(null);
        $renderer->addPath(self::$assetDir, 'test');
        $paths = $renderer->getPaths();
        
        $this->assertInternalType('array', $paths);
        $this->assertCount(1, $paths);
        $this->assertTemplatePath(self::$assetDir, $paths[0]);
        $this->assertTemplatePathString(self::$assetDir, $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }
    
    public function assertTemplatePath($path, TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert TemplatePath contained path %s', $path);
        $this->assertEquals($path, $templatePath->getPath(), $message);
    }
    
    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $renderer = $this->tpl;
        
        $name = 'BitFrame';
        $result = $renderer->render('plates', ['name' => $name]);
        $this->assertContains($name, $result);
        
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        $this->assertEquals($content, $result);
    }
    
    public function testCanRenderWithNullParams()
    {
        $renderer = $this->tpl;
        $result = $renderer->render('plates_null');
        $content = file_get_contents(self::$assetDir . '/plates_null.tpl');
        $this->assertEquals($content, $result);
    }
    
    /**
     * @group namespacing
     */
    public function testProperlyResolvesNamespacedTemplate()
    {
        $renderer = new PlatesRenderer(self::$assetDir, 'tpl');
        $renderer->addPath(self::$assetDir . '/test', 'test');
        $test = $renderer->render('test::namespaced');
        
        $expected = file_get_contents(self::$assetDir . '/test/namespaced.tpl');
        
        $this->assertSame($expected, $test);
    }
    
    public function testAddParameterToOneTemplate()
    {
        $renderer = new PlatesRenderer(self::$assetDir, 'tpl');
        
        $name = 'Plates';
        $renderer->addDefaultParam('plates', ['name' => $name]);
        
        $result = $renderer->render('plates');
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        
        $this->assertEquals($content, $result);
        
        // @fixme hack to work around https://github.com/thephpleague/plates/issues/60, remove if ever merged
        set_error_handler(function ($error, $message) {
            $this->assertContains('Undefined variable: name', $message);
            return true;
        }, E_NOTICE);
        
        $result = $renderer->render('plates2');
        restore_error_handler();
        $content = file_get_contents(self::$assetDir . '/plates2.tpl');
        $content = str_replace('<?= $name; ?>', '', $content);
        
        $this->assertEquals($content, $result);
    }
    
    public function testAddSharedParameters()
    {
        $renderer = new PlatesRenderer(self::$assetDir, 'tpl');
        
        $name = 'Plates';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, ['name' => $name]);
        
        $result = $renderer->render('plates');
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        $this->assertEquals($content, $result);
        
        $result = $renderer->render('plates2');
        $content = file_get_contents(self::$assetDir . '/plates2.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        
        $this->assertEquals($content, $result);
    }
    
    public function testOverwriteSharedGlobalParameters()
    {
        // define a globally available 'name' param to all templates
        $name = 'Plates';
        $data = ['name' => 'Plates'];
        $renderer = new PlatesRenderer(self::$assetDir, 'tpl', $data);
        
        $result = $renderer->render('plates');
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        
        $this->assertEquals($content, $result);
        
        // overwrite name using addDefaultParam
        $name = 'BitFrame';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, ['name' => $name]);
        
        $result = $renderer->render('plates');
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        
        $this->assertEquals($content, $result);
        
        // overwrite name using render
        $name = 'BitFrame Test2';
        $data = ['name' => $name];
        $result = $renderer->render('plates', $data);
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        
        $this->assertEquals($content, $result);
    }
    
    public function testOverwriteSharedParametersPerTemplate()
    {
        $renderer = new PlatesRenderer(self::$assetDir, 'tpl');
        
        $name = 'Plates';
        $name2 = 'BitFrame';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, ['name' => $name]);
        $renderer->addDefaultParam('plates2', ['name' => $name2]);
        
        $result = $renderer->render('plates');
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name, $content);
        
        $this->assertEquals($content, $result);
        
        $result = $renderer->render('plates2');
        $content = file_get_contents(self::$assetDir . '/plates2.tpl');
        $content = str_replace('<?= $name; ?>', $name2, $content);
        
        $this->assertEquals($content, $result);
    }

    public function testOverwriteSharedParametersAtRender()
    {
        $renderer = new PlatesRenderer(self::$assetDir, 'tpl');
        
        $name = 'Plates';
        $name2 = 'BitFrame';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, ['name' => $name]);
        
        $result = $renderer->render('plates', ['name' => $name2]);
        $content = file_get_contents(self::$assetDir . '/plates.tpl');
        $content = str_replace('<?= $name; ?>', $name2, $content);
        
        $this->assertEquals($content, $result);
    }
    
    
    public function assertTemplatePathString($path, TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert TemplatePath casts to string path %s', $path);
        $this->assertEquals($path, (string) $templatePath, $message);
    }
    
    public function assertTemplatePathNamespace($namespace, TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert TemplatePath namespace matched %s', var_export($namespace, 1));
        $this->assertEquals($namespace, $templatePath->getNamespace(), $message);
    }
    
    public function assertEmptyTemplatePathNamespace(TemplatePath $templatePath, $message = null)
    {
        $message = $message ?: 'Failed to assert TemplatePath namespace was empty';
        $this->assertEmpty($templatePath->getNamespace(), $message);
    }
    
    public function assertEqualTemplatePath(TemplatePath $expected, TemplatePath $received, $message = null)
    {
        $message = $message ?: 'Failed to assert TemplatePaths are equal';
        if ($expected->getPath() !== $received->getPath()
            || $expected->getNamespace() !== $received->getNamespace()
        ) {
            $this->fail($message);
        }
    }
    
    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $params Array of parameters to pass into method.
     *
     * @return mixed
     */
    public function invokeMethod(&$object, $methodName, array $params = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $params);
    }
}