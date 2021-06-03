<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF;

use SV\SvgTemplate\Globals;

/**
 * Extends \XF\Style
 */
class Style extends XFCP_Style
{
    protected $todoReplacement = true;

    public function getProperty($name, $fallback = '')
    {
        if ($this->todoReplacement)
        {
            $this->injectStylePropertyBits();
        }
        return parent::getProperty($name, $fallback);
    }

    public function getCssProperty($name, $filters = null)
    {
        if ($this->todoReplacement)
        {
            $this->injectStylePropertyBits();
        }
        return parent::getCssProperty($name, $filters);
    }

    public function getProperties()
    {
        if ($this->todoReplacement)
        {
            $this->injectStylePropertyBits();
        }
        parent::getProperties();
    }

    public function setProperties(array $properties)
    {
        parent::setProperties($properties);
        $this->injectStylePropertyBits();
    }

    public function injectStylePropertyBits()
    {
        $this->todoReplacement = false;

        $app = \XF::app();
        $container = $app->container();
        if ($container->isCached('templater'))
        {
            /** @var \SV\SvgTemplate\XF\Template\Templater $templater */
            $templater = $app->templater();
        }
        else
        {
            $templater = Globals::$templater;
            Globals::$templater = null;
        }

        if (!$templater || !\is_callable([$templater,'fnGetSvgUrl']))
        {
            return;
        }

        foreach($this->properties as &$property)
        {
            if (\is_array($property))
            {
                foreach($property as &$component)
                {
                    $changes = false;
                    $data = \preg_replace_callback("/{{\s*getSvgUrl\s*\(\s*'([^']+)'\s*\)\s*}}/siux", function ($match) use ($templater, &$changes) {
                        $output = $templater->fnGetSvgUrl($templater, $escape, $match[1]);
                        $changes = $output !== $match[1];
                        return $output;
                    }, $component);
                    if ($changes && $data !== null)
                    {
                        $component = $data;
                    }
                }
            }
            else
            {
                $changes = false;
                $data = \preg_replace_callback("/{{\s*getSvgUrl\s*\(\s*'([^']+)'\s*\)\s*}}/siux", function ($match) use ($templater, &$changes) {
                    $output = $templater->fnGetSvgUrl($templater, $escape, $match[1]);
                    $changes = $output !== $match[1];
                    return $output;
                }, $property);
                if ($changes && $data !== null)
                {
                    $property = $data;
                }
            }
        }
    }
}