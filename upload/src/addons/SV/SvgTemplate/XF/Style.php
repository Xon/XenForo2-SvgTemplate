<?php

namespace SV\SvgTemplate\XF;


/**
 * Extends \XF\Style
 */
class Style extends XFCP_Style
{
    protected $todoReplacement = true;

    public function __construct($id, array $properties, $lastModified = null, array $options = null)
    {
        parent::__construct($id, $properties, $lastModified, $options);

    }

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

        /** @var \SV\SvgTemplate\XF\Template\Templater $templater */
        $templater = \XF::app()->templater();
        if (!\is_callable([$templater,'fnGetSvgUrl']))
        {
            return;
        }

        foreach($this->properties as $key => &$property)
        {
            if (is_array($property))
            {
                foreach($property as $key2 => &$component)
                {
                    $changes = false;
                    $data = preg_replace_callback("/{{\s*getSvgUrl\s*\(\s*'([^']+)'\s*\)\s*}}/siux", function ($match) use ($templater, &$changes) {
                        $output = $templater->fnGetSvgUrl($templater, $escape, $match[1]);
                        $changes = $output != $match[1];
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
                $data = preg_replace_callback("/{{\s*getSvgUrl\s*\(\s*'([^']+)'\s*\)\s*}}/siux", function ($match) use ($templater, &$changes) {
                    $output = $templater->fnGetSvgUrl(\XF::app()->templater(), $escape, $match[1]);
                    $changes = $output != $match[1];
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