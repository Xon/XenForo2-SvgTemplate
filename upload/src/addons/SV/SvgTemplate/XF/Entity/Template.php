<?php
/**
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\SvgTemplate\XF\Entity;

use SV\SvgTemplate\XF\Template\Compiler;
use XF\Template\Compiler\Ast as TemplateCompilerAst;

/**
 * @since 2.3.0 rc5
 */
class Template extends XFCP_Template
{
    const SVG_TEMPLATE_NAME_SUFFIX_FOR_SV = '.svg';

    protected function isSvgTemplateForSv() : bool
    {
        $templateName = $this->title;
        $templateNameLength = \strlen($templateName);

        $templateSuffix = static::SVG_TEMPLATE_NAME_SUFFIX_FOR_SV;
        $templateSuffixLength = \strlen($templateSuffix);

        $suffixFound = \substr($templateName, $templateNameLength - $templateSuffixLength);

        return $suffixFound === $templateSuffix;
    }

    /**
     * @param string $template
     * @param bool $forceValid
     * @param TemplateCompilerAst|null $ast
     * @param null $error
     *
     * @return bool
     */
    protected function validateTemplateText($template, $forceValid = false, &$ast = null, &$error = null)
    {
        $app = $this->app();
        $isSvg = $this->isSvgTemplateForSv();
        if ($isSvg)
        {
            // shim the template compiler, so we don't need to compile the templates multiple times
            $originalTemplateCompiler = $app->container()->getOriginal('templateCompiler');
            $app->container()->set('templateCompiler', function() {
                return new Compiler();
            });
        }
        try
        {
            $isValidated = parent::validateTemplateText($template, $forceValid, $ast, $error);
        }
        finally
        {
            if ($isSvg)
            {
                /** @var Compiler $compiler */
                $compiler = $this->app()->templateCompiler();

                $app->container()->offsetUnset('templateCompiler');
                $app->container()->set('templateCompiler', $originalTemplateCompiler);
            }
        }


        if ($isSvg && $isValidated && $this->getOption('test_compile') && $ast)
        {
            $code = $compiler->previousCode ?? null;
            if (!$code)
            {
                $compiler = $this->app()->templateCompiler();
                $code = $compiler->compile($template);
            }

            /** @var \XF\Repository\User $userRepo */
            $userRepo = $this->repository('XF:User');
            $guestUser = $userRepo->getGuestUser();
            $output = \XF::asVisitor($guestUser, function() use ($code, $app) {

                /** @var \SV\SvgTemplate\svgRenderer $renderer */
                $rendererClass = $app->extendClass('SV\SvgTemplate\svgRenderer');
                $renderer = new $rendererClass($app, $app->templater(), null);

                return $renderer->renderTemplateRaw($code);
            });

            $isValidSvg = false;
            $exceptionMsg = \XF::phraseDeferred('svSvgTemplate_unknown_error');

            try
            {
                if ($output)
                {
                    $dom = new \DOMDocument();
                    $dom->loadXML('<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . $output, LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOXMLDECL);
                    $output = $dom->saveXML();
                    $isValidSvg = \is_string($output) && \strlen($output) > 0;
                }
            }
            catch (\Throwable $exception)
            {
                $exceptionMsg = $exception->getMessage();
                $exceptionMsgParts = \explode(': ', $exceptionMsg);
                $exceptionMsg = $exceptionMsgParts[1];
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