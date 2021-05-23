<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate;

use SV\RedisCache\RawResponseText;
use SV\RedisCache\Redis;
use SV\SvgTemplate\Exception\UnableToRewriteSvgException;
use XF\App;
use XF\CssRenderer;
use XF\Http\ResponseStream;
use XF\Template\Templater;

/**
 * Class svgRenderer
 *
 * @property \SV\SvgTemplate\XF\Template\Templater templater
 */
class svgRenderer extends CssRenderer
{
    const SVG_CACHE_TIME = 3600; // 1 hour

    protected $compactSvg = true;
    protected $echoUncompressedData = false;
    protected $isRenderingPng = false;

    public function __construct(App $app, Templater $templater, \Doctrine\Common\Cache\CacheProvider $cache = null)
    {
        if ($cache === null)
        {
            $cache = \XF::app()->cache('css');
        }
        parent::__construct($app, $templater, $cache);
        $this->templater->automaticSvgUrlWriting = false;

        $this->compactSvg = !\XF::$developmentMode;
        if ($this->useDevModeCache)
        {
            $this->compactSvg = true;
            $this->allowCached = true;
        }
    }

    public function setTemplater(Templater $templater)
    {
        $this->templater = $templater;
        $this->templater->automaticSvgUrlWriting = false;
    }

    protected function getRenderParams()
    {
        $params = parent::getRenderParams();

        $params['xf'] = $this->templater->getDefaultParam('xf');

        return $params;
    }

    public function isRenderingPng(): bool
    {
        return $this->isRenderingPng;
    }

    public function setForceRawCache(bool $value)
    {
        $this->echoUncompressedData = $value;
    }

    protected function filterValidTemplates(array $templates)
    {
        // only support rendering 1 svg/png at a time
        $checkedTemplates = [];
        foreach ($templates AS $template)
        {
            if (!preg_match('/^([a-z0-9_]+:|)([a-z0-9_]+?)(?:\.(svg|png)|)$/i', $template, $matches))
            {
                break;
            }

            $extension = $matches[3] ?? '';

            switch($extension)
            {
                case '':
                case 'svg':
                    break;
                case 'png':
                    if (!($this->templater->svPngSupportEnabled ?? false))
                    {
                        return [];
                    }
                    $this->isRenderingPng = true;
                    break;
                default:
                    return [];
            }

            $type = $matches[1] ?: 'public:';
            $checkedTemplates[] = $type . $matches[2] . '.svg';

            break;
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
            return '';
        }

        $output = $data['o'] ?? null; // gzencoded
        $length = $data['l'] ?? null;
        if ($output === null || $length === null)
        {
            return '';
        }

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
    protected function wrapOutput($output, $length): ResponseStream
    {
        // note; this is only called when SV/RedisCache add-on is installed, any type checks should be again ResponseStream.
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
        $credis->expire($key, static::SVG_CACHE_TIME);
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

    public function renderTemplateRaw($templateCode)
    {
        $templater = $this->templater;
        $template = 'SVG-TEMP-COMPILE.SVG';

        $tmpFile = $templater->getTemplateFilePath('public', $template);

        \file_put_contents($tmpFile, "<?php\n" . $templateCode);
        \XF\Util\Php::invalidateOpcodeCache($tmpFile);
        try
        {
            $output = $templater->renderTemplate('public:' . $template, $this->renderParams, false);
            $output = \utf8_trim($output);
            // always do rewrite/optimize, as this enables the less => css parsing in the <style> element
            if (\strlen($output))
            {
                $output = $this->rewriteSvg($template, $output);
            }

            return $output;
        }
        finally
        {
            @unlink($tmpFile);
            \XF\Util\Php::invalidateOpcodeCache($tmpFile);
            $templater->svUncacheTemplateData('public', $template);
        }
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
            $output = \utf8_trim($output);
            // always do rewrite/optimize, as this enables the less => css parsing in the <style> element
            if (\strlen($output))
            {
                $output = $this->rewriteSvg($template, $output);
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

    /**
     * @return \Less_Parser
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function getLessParser()
    {
        if (!$this->lessParser)
        {
            $options = [
                'compress' => $this->compactSvg,
                'indentation' => ' ',
            ];
            $this->lessParser = new \Less_Parser($options);
        }

        return $this->lessParser;
    }

    protected function cleanNodeList(\DOMNode $parentNode, string &$styling)
    {
        $compactSvg = $this->compactSvg;
        // iterate backwards as this allows removing elements, as the list is "dynamic"
        $nodeList = $parentNode->childNodes;
        for ($i = $nodeList->length - 1; $i >= 0; $i--)
        {
            /** @var \DOMNode $node */
            $node = $nodeList->item($i);
            $checkChildren = true;
            switch ($node->nodeType)
            {
                case XML_ELEMENT_NODE:
                    $nodeName = $node->nodeName;
                    if ($nodeName === 'style')
                    {
                        $styling .= "\n" . $node->textContent;
                        $node->parentNode->removeChild($node);
                        $checkChildren = false;
                        break;
                    }
                    else if ($compactSvg)
                    {
                        if (\preg_match("#^(?:sodipodi:|inkscape:|metadata|desc)#usi", $nodeName))
                        {
                            $node->parentNode->removeChild($node);
                            $checkChildren = false;
                        }
                        else if ($node->hasAttributes())
                        {
                            $attributes = $node->attributes;
                            for ($i2 = $attributes->length - 1; $i2 >= 0; $i2--)
                            {
                                /** @var \DOMAttr $attribute */
                                $attribute = $attributes->item($i2);
                                if (\preg_match("#^(?:sodipodi:|inkscape:)#usi", $attribute->name))
                                {
                                    $attributes->removeNamedItem();
                                }
                            }
                        }
                    }
                    break;
                case XML_TEXT_NODE:
                    if ($compactSvg && !$node->hasAttributes())
                    {
                        $nodeText = $node->textContent;
                        if (\strlen($nodeText))
                        {
                            $nodeText = trim($nodeText);
                            if (!\strlen($nodeText))
                            {
                                $node->parentNode->removeChild($node);
                                $checkChildren = false;
                            }
                        }
                    }
                    break;
                case XML_COMMENT_NODE:
                    $node->parentNode->removeChild($node);
                    $checkChildren = false;
                    break;
            }

            if ($checkChildren)
            {
                $this->cleanNodeList($node, $styling);
            }
        }
    }

    protected function rewriteSvg(string $template, string $svg): string
    {
        // An svg is just plain-text XML. so we can load, prune and save
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        try
        {
            $doc->loadXML($svg, LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOXMLDECL);
        }
        catch (\Exception $e)
        {
            throw new UnableToRewriteSvgException($template, 0, $e);
        }
        finally
        {
            libxml_clear_errors();
        }

        $rootElement = $doc->documentElement;
        $doc->encoding = 'utf-8';
        $doc->formatOutput = false;

        $rootElement->removeAttribute('xml:space');
        $styling = '';
        $this->cleanNodeList($rootElement, $styling);

        // convert various styling blocks less => css
        $styling = \utf8_trim($styling);
        if (\strlen($styling))
        {
            $parser = $this->getFreshLessParser();

            $output = $this->prepareLessForRendering($styling);
            if (\is_callable([$this, 'getLessPrependForPrefix']))
            {
                $output = $this->getLessPrepend() . $this->getLessPrependForPrefix($template) . $output;
            }
            else
            {
                $output = $this->getLessPrepend() . $output;
            }

            try
            {
                $css = $parser->parse($output)->getCss();
            }
            catch (\Exception $e)
            {
                throw new UnableToRewriteSvgException($template, 0, $e);
            }

            $rootElement->insertBefore($doc->createElement('style', $css), $rootElement->lastChild);
        }

        $cleanSvg = $doc->saveXML($rootElement);
        if (!\strlen($cleanSvg))
        {
            // failed for some reason, not returning as-is because it **might** contain funny stuff
            throw new UnableToRewriteSvgException($template);
        }

        return $cleanSvg;
    }
}