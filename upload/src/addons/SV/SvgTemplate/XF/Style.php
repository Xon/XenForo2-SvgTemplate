<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF;

use SV\StandardLib\TemplaterHelper;
use SV\SvgTemplate\Globals;
use SV\SvgTemplate\SV\StandardLib\TemplaterHelper as ExtendedTemplaterHelper;

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
        $templater = $app->templater();
        $templaterHelper = Globals::templateHelper($templater);

        if (!\is_callable([$templaterHelper,'fnGetSvgUrlAs']))
        {
            return;
        }
        $regex = "/{{\s*getSvgUrl(?:as)?\s*\(\s*'([^']+)'\s*(?:,\s*'([^']+)'\s*)?\)\s*}}/siux";

        foreach($this->properties as &$property)
        {
            if (\is_array($property))
            {
                foreach($property as &$component)
                {
                    $changes = false;
                    $data = \preg_replace_callback($regex, function ($match) use ($templater, $templaterHelper, &$changes) {
                        $extension = $match[2] ?? '';
                        $output = $templaterHelper->fnGetSvgUrlAs($templater, $escape, $match[1], $extension);
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
                $data = \preg_replace_callback($regex, function ($match) use ($templater, $templaterHelper, &$changes) {
                    $extension = $match[2] ?? '';
                    $output = $templaterHelper->fnGetSvgUrlAs($templater, $escape, $match[1], $extension);
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