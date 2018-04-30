<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2018 Daniyal Hamid (https://designcise.com)
 * @license   https://github.com/designcise/bitframe/blob/master/LICENSE.md MIT License
 *
 * @author    Zend Framework
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-template/blob/master/LICENSE.md New BSD License
 */

namespace BitFrame\Renderer;

use \Closure;

use \League\Plates\Engine;
use \League\Plates\Template\Name;
use \League\Plates\Extension\URI;

use BitFrame\Renderer\{TemplateInterface, TemplatePath};
use BitFrame\Exception\FileNotReadableException;

/**
 * League Plates template renderer add-on.
 */
class PlatesRenderer implements TemplateInterface 
{
	/** @var Engine */
	private $template;
	
	/**
	 * @param string|null $templateDir
	 * @param string $templateExt (optional)
	 * @param array $data (optional)
	 *
	 * @see Engine::__construct()
	 */
	public function __construct(?string $templateDir, string $templateExt = 'tpl', array $data = []) 
	{
		$this->template = new Engine($templateDir, $templateExt);
		
		if (! empty($data)) {
			$this->addDefaultParam(self::TEMPLATE_ALL, $data);
		}
		
		return $this;
	}
	
	/**
	 * Render template.
	 *
	 * @param string $templateName
	 * @param array $data (optional)
	 *
	 * @return string
	 *
	 * @throws \BitFrame\Exception\FileNotReadableException
	 */
	public function render(string $templateName, array $data = []): string 
	{
		if ($templateName === '' || ! $this->template->exists($templateName)) {
			throw new FileNotReadableException($templateName);
		}
		
		// render main tpl
		$render = $this->template->render($templateName, $data);
		
		return $render;
    }
	
	/**
     * Proxies to the Plate Engine's `addData()` method.
     *
     * {@inheritDoc}
	 *
	 * @throws \InvalidArgumentException
     */
    public function addDefaultParam(string $templateName, $params)
    {
        if (! is_string($templateName) || empty($templateName)) {
            throw new \InvalidArgumentException(sprintf(
                '$templateName must be a non-empty string; received %s',
                is_object($templateName) ? get_class($templateName) : gettype($templateName)
            ));
        }

        if (! is_array($params) || empty($params)) {
            throw new \InvalidArgumentException(sprintf(
                '$param must be a non-empty string or array; received %s',
                is_object($params) ? get_class($params) : gettype($params)
            ));
        }


        if ($templateName === self::TEMPLATE_ALL) {
            $templateName = null;
        }

        $this->template->addData($params, $templateName);
    }
	
	/**
     * {@inheritDoc}
	 *
     * @return void
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
		if (! $namespace && ! $this->template->getDirectory()) {
            $this->template->setDirectory($path);
            return;
        }

        if (! $namespace) {
            trigger_error('Cannot add duplicate un-namespaced path in Plates template adapter', E_USER_WARNING);
            return;
        }

        $this->template->addFolder($namespace, $path, true);
    }
	
	/**
     * {@inheritDoc}
     */
    public function getPaths(): array
    {
		$paths = ($this->template->getDirectory()) ? [$this->getDefaultPath()] : [];

        foreach ($this->getPlatesFolders() as $folder) {
            $paths[] = new TemplatePath($folder->getPath(), $folder->getName());
        }
        return $paths;
    }
	
	/**
	 * Get Template Engine class.
	 *
	 * @return Engine
	 */
	public function getEngine(): Engine
	{
		return $this->template;
	}
	
	/**
     * Create and return a TemplatePath representing the default Plates directory.
     *
     * @return TemplatePath
     */
    private function getDefaultPath(): TemplatePath
    {
        return new TemplatePath($this->template->getDirectory());
    }

    /**
     * Return the internal array of plates folders.
     *
     * @return \League\Plates\Template\Folder[]
     */
    private function getPlatesFolders(): array
    {
        $folders = $this->template->getFolders();
        $r = new \ReflectionProperty($folders, 'folders');
        $r->setAccessible(true);
		
        return $r->getValue($folders);
    }
}