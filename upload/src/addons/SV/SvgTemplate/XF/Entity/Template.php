<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF\Entity;

use DOMDocument;
use SV\StandardLib\Helper;
use SV\SvgTemplate\Exception\UnableToRewriteSvgException;
use SV\SvgTemplate\svgRenderer;
use SV\SvgTemplate\XF\Template\Compiler;
use Throwable;
use XF\Phrase;
use XF\Repository\User as UserRepository;
use XF\Template\Compiler\Ast as TemplateCompilerAst;
use function count;
use function strlen, is_string, explode;
use function substr_compare;

/**
 * @since 2.3.0 rc5
 * @extends \XF\Entity\Template
 */
class Template extends XFCP_Template
{
    public const SVG_TEMPLATE_NAME_SUFFIX_FOR_SV = '.svg';

    protected function isSvgTemplateForSv() : bool
    {
        $templateName = $this->title ?? '';
        $templateNameLength = strlen($templateName);
        if ($templateNameLength === 0)
        {
            return false;
        }

        $templateSuffix = static::SVG_TEMPLATE_NAME_SUFFIX_FOR_SV;
        $templateSuffixLength = strlen($templateSuffix);
        if ($templateNameLength <= $templateSuffixLength)
        {
            return false;
        }

        return substr_compare($templateName, $templateSuffix, -$templateSuffixLength) === 0;
    }

    /**
     * @param string $template
     * @param bool $forceValid
     * @param TemplateCompilerAst|null $ast
     * @param Phrase|null $error
     *
     * @return bool
     */
    protected function validateTemplateText($template, $forceValid = false, &$ast = null, &$error = null)
    {
        $app = \XF::app();
        if (!$this->isSvgTemplateForSv())
        {
            return parent::validateTemplateText($template, $forceValid, $ast, $error);
        }

        if (strlen($template) === 0)
        {
            // svg templates must not be empty
            $error = \XF::phrase('svSvgTemplate_template_can_not_be_empty', ['template' => "{$this->type}:{$this->title}"]);
            return false;
        }

        // shim the template compiler, so we don't need to compile the templates multiple times
        $originalTemplateCompiler = $app->container()->getOriginal('templateCompiler');
        $app->container()->set('templateCompiler', function() {
            return new Compiler();
        });
        try
        {
            $isValidated = parent::validateTemplateText($template, $forceValid, $ast, $error);
        }
        finally
        {
            /** @var Compiler $compiler */
            $compiler = \XF::app()->templateCompiler();

            $app->container()->offsetUnset('templateCompiler');
            $app->container()->set('templateCompiler', $originalTemplateCompiler);
        }

        if ($isValidated && $this->getOption('test_compile'))
        {
            if (!$ast)
            {
                // svg templates must not be empty
                $error = \XF::phrase('svSvgTemplate_template_can_not_be_empty', ['template' => "{$this->type}:{$this->title}"]);
                return false;
            }

            $code = $compiler->previousCode ?? null;
            if (!$code)
            {
                $compiler = \XF::app()->templateCompiler();
                $code = $compiler->compile($template);
            }

            $isValidSvg = false;
            $exceptionMsg = \XF::phraseDeferred('svSvgTemplate_unknown_error');

            try
            {
                $userRepo = Helper::repository(UserRepository::class);
                $guestUser = $userRepo->getGuestUser();
                $output = \XF::asVisitor($guestUser, function () use ($code, $app) {

                    $renderer = svgRenderer::factory(\XF::app());
                    $renderer->setStyle($app->style($this->style_id));

                    return $renderer->renderTemplateRaw($this->title, $code);
                });

                if ($output)
                {
                    $dom = new DOMDocument();
                    $dom->loadXML('<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . $output, LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOXMLDECL);
                    $output = $dom->saveXML();
                    $isValidSvg = is_string($output) && strlen($output) !== 0;
                }
            }
            catch (UnableToRewriteSvgException $exception)
            {
                $exceptionMsg = $exception->getMessage();
            }
            catch (Throwable $exception)
            {
                $exceptionMsg = $exception->getMessage();
                $exceptionMsgParts = explode(': ', $exceptionMsg);
                if (count($exceptionMsgParts) > 1)
                {
                    $exceptionMsg = $exceptionMsgParts[1];
                }
            }

            if (!$isValidSvg)
            {
                $error = $exceptionMsg . ' - ' . \XF::phrase('template_name:') . ' ' . "{$this->type}:{$this->title}";

                return false;
            }
        }

        return $isValidated;
    }
}