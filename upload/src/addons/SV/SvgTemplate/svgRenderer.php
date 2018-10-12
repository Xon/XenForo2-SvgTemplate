<?php

namespace SV\SvgTemplate;

use XF\CssRenderer;

class svgRenderer extends cssRenderer
{
    protected function filterValidTemplates(array $templates)
    {
        $checkedTemplates = [];
        foreach ($templates AS $key => $template)
        {
            if (preg_match('/^([a-z0-9_]+:|)([a-z0-9_]+?)(\.svg){0,1}$/i', $template, $matches))
            {
                if ($matches[1])
                {
                    $checkedTemplates[] = $template . '.svg';
                }
                else
                {
                    $checkedTemplates[] = 'public:' . $template . '.svg';
                }

                // only support rendering 1 svg at a time
                break;
            }
        }

        return $checkedTemplates;
    }

    protected function getFinalCacheKey(array $templates)
    {
        $elements = $this->getCacheKeyElements();

        $templates = array_unique($templates);
        sort($templates);

        return 'xfSvfCache_' . md5(
                'templates=' . implode(',', $templates)
                . 'style=' . $elements['style_id']
                . 'modified=' . $elements['style_last_modified']
                . 'language=' . $elements['language_id']
                . $elements['modifier']
            );
    }

    protected function renderTemplates(array $templates, array $cached = [], array &$errors = null)
    {
        $errors = [];
        $this->renderParams = $this->getRenderParams();

        $template = reset($templates);

        if (isset($cached[$template]))
        {
            return $cached[$template];
        }
        else
        {
            $rendered = $this->renderTemplate($template, $error);
            if (is_string($rendered))
            {
                if ($this->allowCached || $this->useDevModeCache)
                {
                    $this->cacheTemplate($template, $rendered);
                }

                return $rendered;
            }
            else if ($error)
            {
                $errors[$template] = $error;
            }
        }

        return '';
    }

    public function renderTemplate($template, &$error = null, &$updateCache = true)
    {
        if (!$this->templater->isKnownTemplate($template))
        {
            return false;
        }

        try
        {
            $this->lastRenderedTemplate = $template;

            $error = null;
            $output = $this->templater->renderTemplate($template, $this->renderParams, false);
            $updateCache = true;

            if (!$this->allowCached && $this->useDevModeCache && !$this->templater->hasWatcherActionedTemplates())
            {
                // if we haven't touched any templates in this pipeline, we can look for a cached template
                $rendered = $this->getIndividualCachedTemplate($template);
                if (is_string($rendered))
                {
                    $updateCache = false;
                    return $rendered;
                }
            }

            return trim($output);
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
            $error = $e->getMessage();
            return false;
        }
    }
}