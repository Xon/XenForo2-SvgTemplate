<?php

namespace SV\SvgTemplate\XF;

use SV\SvgTemplate\Globals;
use function array_key_exists;
use function is_callable, is_array, preg_replace_callback;
use function is_string;

/**
 * @extends \XF\Style
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

    /**
     * XF2.3+
     */
    public function getPropertyVariation(string $name, string $variation,  $fallback = '')
    {
        if ($this->todoReplacement)
        {
            $this->injectSvgStylePropertyBits();
        }
        return parent::getPropertyVariation($name, $variation, $fallback);
    }

    public function getProperties()
    {
        if ($this->todoReplacement)
        {
            $this->injectSvgStylePropertyBits();
        }
        return parent::getProperties();
    }

    public function setProperties(array $properties)
    {
        parent::setProperties($properties);
        $this->injectSvgStylePropertyBits();
    }

    public function injectSvgStylePropertyBits(): void
    {
        $this->todoReplacement = false;

        $app = \XF::app();
        $templater = $app->templater();
        $templaterHelper = Globals::templateHelper($templater);

        if (!is_callable([$templaterHelper,'fnGetSvgUrlAs']))
        {
            return;
        }

        $regexFunc = function (string $component) use ($templater, $templaterHelper, &$changes) {
            return preg_replace_callback("/{{\s*getSvgUrl(?:as)?\s*\(\s*'([^']+)'\s*(?:,\s*'([^']+)'\s*)?\)\s*}}/iux", function ($match) use ($templater, $templaterHelper, &$changes) {
                $extension = $match[2] ?? '';
                $output = $templaterHelper->fnGetSvgUrlAs($templater, $escape, $match[1], $extension);
                $changes = $output !== $match[1];
                return $output;
            }, $component);
        };

        foreach($this->properties as &$property)
        {
            if (is_array($property))
            {
                foreach($property as &$component)
                {
                    if (is_string($component))
                    {
                        $changes = false;
                        $data = $regexFunc($component);
                        if ($changes && $data !== null)
                        {
                            $component = $data;
                        }
                    }
                }
                if (array_key_exists('variables', $property) && is_array($property['variables']))
                {
                    foreach($property['variables'] as &$variable)
                    {
                        if (is_string($variable))
                        {
                            $changes = false;
                            $data = $regexFunc($variable);
                            if ($changes && $data !== null)
                            {
                                $variable = $data;
                            }
                        }
                    }
                }
            }
            else if (is_string($property))
            {
                $changes = false;
                $data = $regexFunc($property);
                if ($changes && $data !== null)
                {
                    $property = $data;
                }
            }
        }
    }
}