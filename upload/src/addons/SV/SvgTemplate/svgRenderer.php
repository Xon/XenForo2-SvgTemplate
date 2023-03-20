<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate;

use SV\RedisCache\RawResponseText;
use SV\RedisCache\Redis;
use SV\StandardLib\TemplaterHelper;
use SV\SvgTemplate\Exception\UnableToRewriteSvgException;
use XF\App;
use XF\CssRenderer;
use XF\Entity\Template;
use XF\Http\ResponseStream;
use XF\Template\Templater;
use XF\Util\File;
use function preg_match, trim, strlen, is_array, reset, is_string, array_unique, sort, md5, implode, strval, gzencode, is_callable;

/**
 * Class svgRenderer
 *
 */
class svgRenderer extends CssRenderer
{
    /** @var int */
    const SVG_CACHE_TIME = 3600; // 1 hour

    /** @var bool */
    protected $compactSvg = true;
    /** @var bool */
    protected $echoUncompressedData = false;
    /** @var bool */
    protected $isRenderingPng = false;
    /** @var int|null */
    protected $inputModifiedDate = null;

    public function __construct(App $app, Templater $templater)
    {
        $cache = \XF::app()->cache('css');
        parent::__construct($app, $templater, $cache);
        Globals::templateHelper($this->templater)->automaticSvgUrlWriting = false;

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
        Globals::templateHelper($this->templater)->automaticSvgUrlWriting = false;
    }

    protected function getRenderParams()
    {
        $params = parent::getRenderParams();

        $params['xf'] = Globals::templateHelper($this->templater)->getDefaultParam('xf');

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

    /**
     * @return int|null
     */
    public function getInputModifiedDate()
    {
        return $this->inputModifiedDate;
    }

    public function setInputModifiedDate(int $value = null)
    {
        $this->inputModifiedDate = $value;
    }

    protected function filterValidTemplates(array $templates)
    {
        /** @var \SV\SvgTemplate\SV\StandardLib\TemplaterHelper $templaterHelper */
        $templaterHelper = TemplaterHelper::get($this->templater);
        $pngSupported = $templaterHelper->svPngSupportEnabled ?? false;
        // only support rendering 1 svg/png at a time
        $checkedTemplates = [];
        foreach ($templates AS $template)
        {
            if (!preg_match('/^([a-z\d_]+:|)([a-z\d_]+?)(?:\.(svg|png)|)$/i', $template, $matches))
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
                    if (!$pngSupported)
                    {
                        return [];
                    }
                    $this->isRenderingPng = true;
                    break;
                default:
                    return [];
            }

            $date = $this->getInputModifiedDate();
            $styleModifiedDate = $this->style->getLastModified();
            if ($date === 0 || $date !== null && $styleModifiedDate && $date > $styleModifiedDate)
            {
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

        $redis = $cache;
        $key = $redis->getNamespacedId($this->getFinalCacheKey($templates) . '_gz');
        $data = $credis->hGetAll($key);
        if (!is_array($data))
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

        $redis = $cache;
        $key = $redis->getNamespacedId($this->getFinalCacheKey($templates) . '_gz');
        $credis->hMSet($key, [
            'o' => $output ? gzencode($output, 9) : null,
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
        $templateName = 'SVG-TEMP-COMPILE.SVG';

        /** @var Template $template */
        $template = $this->app->em()->create('XF:Template');
        $template->title = $templateName;
        $template->type = 'public';
        $template->style_id = (int)$templater->getStyleId();

        $tmpFile = $template->getAbstractedCompiledTemplatePath(0,$template->style_id);
        File::writeToAbstractedPath($tmpFile, "<?php\n" . $templateCode);
        try
        {
            $output = $templater->renderTemplate('public:' . $template, $this->renderParams, false);
            $output = trim($output);
            // always do rewrite/optimize, as this enables the less => css parsing in the <style> element
            if (strlen($output) !== 0)
            {
                $output = $this->rewriteSvg($templateName, $output);
            }

            return $output;
        }
        finally
        {
            File::deleteFromAbstractedPath($tmpFile);
            Globals::templateHelper($this->templater)->uncacheTemplateData('public', $templateName);
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
            $output = trim($output);
            // always do rewrite/optimize, as this enables the less => css parsing in the <style> element
            if (strlen($output) !== 0)
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
                        if (preg_match('#^(?:sodipodi:|inkscape:|metadata|desc)#ui', $nodeName))
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
                                if (preg_match('#^(?:sodipodi:|inkscape:)#ui', $attribute->name))
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
                        if (strlen($nodeText) !== 0)
                        {
                            $nodeText = trim($nodeText);
                            if (strlen($nodeText) === 0)
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
        // The svg format is just plain-text XML. so we can load, prune and save
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;
        \libxml_use_internal_errors(true);
        try
        {
            $doc->loadXML($svg, LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOXMLDECL);
        }
        catch (\Exception $e)
        {
            throw new UnableToRewriteSvgException($template, $e->getMessage(), 0, $e);
        }
        finally
        {
            \libxml_clear_errors();
        }

        $rootElement = $doc->documentElement;
        if (!$rootElement)
        {
            // invalid XML
            throw new UnableToRewriteSvgException($template, 'SVGs must be valid XML');
        }
        $doc->encoding = 'utf-8';
        $doc->formatOutput = false;

        $rootElement->removeAttribute('xml:space');
        $styling = '';
        $this->cleanNodeList($rootElement, $styling);

        // convert various styling blocks less => css
        $styling = trim($styling);
        if (strlen($styling) !== 0)
        {
            $parser = $this->getFreshLessParser();

            $output = $this->prepareLessForRendering($styling);
            if (is_callable([$this, 'getLessPrependForPrefix']))
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
                throw new UnableToRewriteSvgException($template, $e->getMessage(), 0, $e);
            }

            $rootElement->insertBefore($doc->createElement('style', $css), $rootElement->lastChild);
        }

        $cleanSvg = $doc->saveXML($rootElement);
        if (strlen($cleanSvg) === 0)
        {
            // failed for some reason, not returning as-is because it **might** contain funny stuff
            throw new UnableToRewriteSvgException($template);
        }

        return $cleanSvg;
    }
}