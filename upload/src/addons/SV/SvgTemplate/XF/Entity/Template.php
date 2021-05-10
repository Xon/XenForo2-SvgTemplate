<?php

namespace SV\SvgTemplate\XF\Entity;

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
        $isValidated = parent::validateTemplateText($template, $forceValid, $ast, $error);

        if ($isValidated && $this->isSvgTemplateForSv())
        {
            $dom = new \DOMDocument();

            $isValidSvg = false;
            $exceptionMsg = \XF::phraseDeferred('svSvgTemplate_unknown_error');

            try
            {
                $dom->loadXML($template);
                $isValidSvg = $dom->validate();
            }
            catch (\Exception $exception)
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