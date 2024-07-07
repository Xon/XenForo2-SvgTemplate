<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF;

use SV\SvgTemplate\Globals;
use function is_callable, is_array, preg_replace_callback;

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
            $this->injectSvgStylePropertyBits();
        }
        return parent::getProperty($name, $fallback);
    }

    public function getPropertyVariation(string $name, string $variation,  $fallback = '')
    {
        if ($this->todoReplacement)
        {
            $this->injectSvgStylePropertyBits();
        }
        return parent::getPropertyVariation($name, $fallback);
    }

    public function getProperties()
    {
        if ($this->todoReplacement)
        {
            $this->injectSvgStylePropertyBits();
        }
        parent::getProperties();
    }

    public function setProperties(array $properties)
    {
        parent::setProperties($properties);
        $this->injectSvgStylePropertyBits();
    }

    public function injectSvgStylePropertyBits()
    {
        $this->todoReplacement = false;

        $app = \XF::app();
        $templater = $app->templater();
        $templaterHelper = Globals::templateHelper($templater);

        if (!is_callable([$templaterHelper,'fnGetSvgUrlAs']))
        {
            return;
        }
        $regex = "/{{\s*getSvgUrl(?:as)?\s*\(\s*'([^']+)'\s*(?:,\s*'([^']+)'\s*)?\)\s*}}/siux";

        foreach($this->properties as &$property)
        {
            if (is_array($property))
            {
                foreach($property as &$component)
                {
                    $changes = false;
                    $data = preg_replace_callback($regex, function ($match) use ($templater, $templaterHelper, &$changes) {
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
                $data = preg_replace_callback($regex, function ($match) use ($templater, $templaterHelper, &$changes) {
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