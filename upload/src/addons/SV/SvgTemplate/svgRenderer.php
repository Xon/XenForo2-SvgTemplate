<?php
/**
 * @noinspection PhpMissingParentCallCommonInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate;

use DOMAttr;
use DOMDocument;
use DOMNode;
use Exception;
use Less_Parser;
use SV\RedisCache\RawResponseText;
use SV\RedisCache\Redis;
use SV\RedisCache\Repository\Redis as RedisRepository;
use SV\StandardLib\Helper;
use SV\SvgTemplate\Exception\UnableToRewriteSvgException;
use XF\App as BaseApp;
use XF\CssRenderer;
use XF\Entity\Template as TemplateEntity;
use XF\Http\ResponseStream;
use XF\Template\Templater;
use XF\Util\File;
use function gzdecode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_match, trim, strlen, is_array, reset, is_string, array_unique, sort, md5, implode, strval, gzencode, is_callable;

class svgRenderer extends CssRenderer
{
    /** @var int */
    public const SVG_CACHE_TIME = 3600; // 1 hour

    /** @var bool */
    protected $compactSvg = true;
    /** @var bool */
    protected $echoUncompressedData = false;
    /** @var bool */
    protected $isRenderingPng = false;
    /** @var int|null */
    protected $inputModifiedDate = null;
    /** @var Redis */
    protected $redisCache = null;

    public function __construct(BaseApp $app, Templater $templater)
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

        $this->redisCache = Helper::isAddOnActive('SV/RedisCache') ? RedisRepository::get()->getRedisObj($cache) : null;
    }

    public static function factory(BaseApp $app, ?Templater $templater = null): self
    {
        return Helper::newExtendedClass(svgRenderer::class, $app, $templater ?? $app->templater());
    }

    public function setTemplater(Templater $templater): void
    {
        $this->templater = $templater;
        Globals::templateHelper($this->templater)->automaticSvgUrlWriting = false;
    }

    protected function getRenderParams(): array
    {
        $params = parent::getRenderParams();

        $params['xf'] = Globals::templateHelper($this->templater)->getDefaultParam('xf');

        return $params;
    }

    public function isRenderingPng(): bool
    {
        return $this->isRenderingPng;
    }

    public function setForceRawCache(bool $value): void
    {
        $this->echoUncompressedData = $value;
    }

    public function getInputModifiedDate(): ?int
    {
        return $this->inputModifiedDate;
    }

    public function setInputModifiedDate(?int $value = null): void
    {
        $this->inputModifiedDate = $value;
    }

    protected function filterValidTemplates(array $templates): array
    {
        $pngSupported = Globals::templateHelper($this->templater)->svPngSupportEnabled ?? false;
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

    /**
     * @param array $templates
     * @return string|ResponseStream
     */
    protected function getFinalCachedOutput(array $templates)
    {
        $cache = $this->redisCache;
        if (!$this->allowCached || $cache === null || !($credis = $cache->getCredis(true)))
        {
            return parent::getFinalCachedOutput($templates);
        }

        $key = $cache->getNamespacedId($this->getFinalCacheKey($templates) . '_gz');
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
        return $output ? @gzdecode($output) : '';
    }

    protected function getFinalCacheKey(array $templates): string
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

    protected function cacheFinalOutput(array $templates, $output): void
    {
        $cache = $this->redisCache;
        if (!$this->allowCached || !$this->allowFinalCacheUpdate || $cache === null || !($credis = $cache->getCredis(false)))
        {
            parent::cacheFinalOutput($templates, $output);

            return;
        }

        $output = strval($output);

        $key = $cache->getNamespacedId($this->getFinalCacheKey($templates) . '_gz');
        $credis->hMSet($key, [
            'o' => $output ? gzencode($output, 9) : null,
            'l' => strlen($output),
        ]);
        $credis->expire($key, static::SVG_CACHE_TIME);
    }

    protected function getIndividualCachedTemplates(array $templates): array
    {
        // xf_css_cache is silly, so avoid the extra database hit
        return [];
    }

    protected function renderTemplates(array $templates, array $cached = [], ?array &$errors = null): string
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

    /** @noinspection PhpUnusedParameterInspection */
    public function renderTemplateRaw(string $templateName, string $templateCode): string
    {
        $templater = $this->templater;
        $tmpTemplateName = 'SVG-TEMP-COMPILE.SVG';
        $styleId = (int)$templater->getStyleId();
        $languageId = $templater->getLanguage()->getId();

        $template = Helper::instantiateEntity(TemplateEntity::class, [
            'template_id' => -1,
            'title' => $tmpTemplateName,
            'type' => 'public',
            'style_id' => $styleId,
            'addon_id' => 'SV/SvgTemplate',
            'template' => $templateCode,
        ]);
        $template->setReadOnly(true);

        $tmpFile = $template->getAbstractedCompiledTemplatePath($languageId, $styleId, true);
        File::writeToAbstractedPath($tmpFile, "<?php\n" . $templateCode);
        try
        {
            $output = $templater->renderTemplate('public:' . $tmpTemplateName, $this->renderParams, false);
            $output = trim($output);
            // always do rewrite/optimize, as this enables the less => css parsing in the <style> element
            if (strlen($output) !== 0)
            {
                $output = $this->rewriteSvg($tmpTemplateName, $output);
            }

            return $output;
        }
        finally
        {
            File::deleteFromAbstractedPath($tmpFile);
            Globals::templateHelper($this->templater)->uncacheTemplateData('public', $tmpTemplateName);
        }
    }

    public function renderTemplate($template, &$error = null, &$updateCache = true): ?string
    {
        if (!$this->templater->isKnownTemplate($template))
        {
            return null;
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

            return null;
        }
    }

    protected function getLessParser(): Less_Parser
    {
        if ($this->lessParser === null)
        {
            $options = [
                'compress' => $this->compactSvg,
                'indentation' => ' ',
            ];
            $this->lessParser = new Less_Parser($options);
        }

        return $this->lessParser;
    }

    protected function cleanNodeList(DOMNode $parentNode, string &$styling): void
    {
        $compactSvg = $this->compactSvg;
        // iterate backwards as this allows removing elements, as the list is "dynamic"
        $nodeList = $parentNode->childNodes;
        for ($i = $nodeList->length - 1; $i >= 0; $i--)
        {
            /** @var DOMNode $node */
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
                                /** @var DOMAttr $attribute */
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
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        try
        {
            $doc->loadXML($svg, LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOXMLDECL);
        }
        catch (Exception $e)
        {
            throw new UnableToRewriteSvgException($template, $e->getMessage(), 0, $e);
        }
        finally
        {
            libxml_clear_errors();
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