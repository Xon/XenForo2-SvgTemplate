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

    /** @noinspection PhpMissingReturnTypeInspection */
    public function getProperties()
    {
        if ($this->todoReplacement)
        {
            $this->injectSvgStylePropertyBits();
        }
        return parent::getProperties();
    }

    public function getVariationVariables(string $variation, bool $colors = false): array
    {
        if ($this->todoReplacement)
        {
            $this->injectSvgStylePropertyBits();
        }
        return parent::getVariationVariables($variation, $colors);
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

        $seen = [];
        $regexFunc = function (string $component) use ($templater, $templaterHelper, &$changes, &$seen) {
            return preg_replace_callback("/{{\s*getSvgUrl(?:as)?\s*\(\s*'([^']+)'\s*(?:,\s*'([^']+)'\s*)?\)\s*}}/iux", function ($match) use ($templater, $templaterHelper, &$changes, &$seen) {
                $extension = $match[2] ?? '';
                $template = $match[1];
                $output = $seen[$template] ?? null;
                if ($output === null)
                {
                    $output = $templaterHelper->fnGetSvgUrlAs($templater, $escape, $template, $extension);
                    $seen[$template] = $output;
                }
                $changes = $output !== $template;
                return $output;
            }, $component);
        };

        $variableKey = \XF::$versionId >= 2030000 ? static::VARIABLE_KEY : '';

        foreach($this->properties as &$property)
        {
            if (is_array($property))
            {
                foreach($property as &$component)
                {
                    if (is_string($component) && $component !== '')
                    {
                        $changes = false;
                        $data = $regexFunc($component);
                        if ($changes && $data !== null)
                        {
                            $component = $data;
                        }
                    }
                }
                $variants = $property[$variableKey] ?? null;
                if (is_array($variants))
                {
                    foreach($variants as $key => $variable)
                    {
                        if (is_string($variable) && $variable !== '')
                        {
                            $changes = false;
                            $data = $regexFunc($variable);
                            if ($changes && $data !== null)
                            {
                                $property[$variableKey][$key] = $data;
                            }
                        }
                    }
                }
            }
            else if (is_string($property) && $property !== '')
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