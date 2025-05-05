<?php

namespace SV\SvgTemplate\XF\Template\Exception;

use Throwable;

/**
 * @since 2.3.0 rc1
 */
class UnsupportedExtensionProvidedException extends \InvalidArgumentException
{
    /**
     * @var string
     */
    protected $templateName;

    public function __construct(string $templateName, $code = 0, ?Throwable $previous = null)
    {
        $this->templateName = $templateName;

        parent::__construct('Unsupported extension provided:'.$templateName, $code, $previous);
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }
}