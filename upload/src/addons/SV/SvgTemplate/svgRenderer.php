<?php

namespace SV\SvgTemplate;

use SV\RedisCache\RawResponseText;
use SV\RedisCache\Redis;
use XF\App;
use XF\CssRenderer;
use XF\Http\ResponseStream;
use XF\Template\Templater;

class svgRenderer extends CssRenderer
{
    protected $echoUncompressedData = false;

    public function __construct(App $app, Templater $templater, \Doctrine\Common\Cache\CacheProvider $cache = null)
    {
        if ($cache === null)
        {
            $cache = \XF::app()->cache('css');
        }
        parent::__construct($app, $templater, $cache);

        if ($this->useDevModeCache)
        {
            $this->allowCached = true;
        }
    }

    /**
     * @param bool $value
     */
    public function setForceRawCache($value)
    {
        $this->echoUncompressedData = $value;
    }

    protected function filterValidTemplates(array $templates)
    {
        $checkedTemplates = [];
        foreach ($templates AS $template)
        {
            if (preg_match('/^([a-z0-9_]+:|)([a-z0-9_]+?)(?:\.svg|)$/i', $template, $matches))
            {
                $type = $matches[1] ?: 'public:';
                $checkedTemplates[] = $type . $matches[2] . '.svg';

                // only support rendering 1 svg at a time
                break;
            }
        }

        return $checkedTemplates;
    }

    protected function getFinalCachedOutput(array $templates)
    {
        $cache = $this->cache;
        if (!$this->allowCached || !($cache instanceof Redis) || !($credis = $cache->getCredis(true)))
        {
            return parent::getFinalCachedOutput($templates);
        }

        $key = $cache->getNamespacedId($this->getFinalCacheKey($templates) . '_gz');
        $data = $credis->hGetAll($key);
        if (empty($data))
        {
            return false;
        }

        $output = $data['o']; // gzencoded
        $length = $data['l'];

        if ($this->echoUncompressedData)
        {
            return $this->wrapOutput($output, $length);
        }

        // client doesn't support compression, so decompress before sending it
        return $output ? @\gzdecode($output) : '';
    }

    protected function getFinalCacheKey(array $templates)
    {
        $elements = $this->getCacheKeyElements();

        $templates = array_unique($templates);
        sort($templates);

        return 'xfSvgCache_' . md5(
                'templates=' . implode(',', $templates)
                . 'style=' . $elements['style_id']
                . 'modified=' . $elements['style_last_modified']
                . 'language=' . $elements['language_id']
                . $elements['modifier']
            );
    }

    /**
     * @param $output
     * @param $length
     * @return ResponseStream
     */
    protected function wrapOutput($output, $length)
    {
        return new RawResponseText($length ? $output : '', $length);
    }

    protected function cacheFinalOutput(array $templates, $output)
    {
        $cache = $this->cache;
        if (!$this->allowCached || !$this->allowFinalCacheUpdate || !($cache instanceof Redis) || !($credis = $cache->getCredis(false)))
        {
            parent::cacheFinalOutput($templates, $output);

            return;
        }

        $output = strval($output);

        $key = $cache->getNamespacedId($this->getFinalCacheKey($templates) . '_gz');
        $credis->hMSet($key, [
            'o' => $output ? \gzencode($output, 9) : null,
            'l' => strlen($output),
        ]);
        $credis->expire($key, 3600);
    }

    protected function getIndividualCachedTemplates(array $templates)
    {
        // xf_css_cache is silly, so avoid the extra database hit
        return [];
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
            $output = trim($output);
            if ($output && $this->cache && $this->allowCached)
            {
                $output = $this->optimizeSvg($output);
            }

            return $output;
        }
        catch (\Exception $e)
        {
            \XF::logException($e);
            $error = $e->getMessage();

            return false;
        }
    }

    protected function optimizeSvg($svg)
    {
        return $svg;
    }
}