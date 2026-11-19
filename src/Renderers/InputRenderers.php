<?php

declare(strict_types=1);

namespace LogiPhp\Renderers;

use DOMElement;
use DOMXPath;
use LogiPhp\ElementRendererInterface;
use LogiPhp\LogiContext;
use LogiPhp\LogiEngine;

class FormRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $id = htmlspecialchars($node->getAttribute('ID') ?: 'frmLogi', ENT_QUOTES, 'UTF-8');
        $method = strtoupper($node->getAttribute('Method') ?: 'GET') === 'POST' ? 'POST' : 'GET';
        $action = htmlspecialchars($node->getAttribute('Action') ?: '', ENT_QUOTES, 'UTF-8');
        $inner = $engine->renderChildren($node, $context);

        return "<form id=\"{$id}\" method=\"{$method}\" action=\"{$action}\" style=\"display:flex;gap:12px;align-items:center;flex-wrap:wrap;background:#f7fafc;padding:12px;border:1px solid #e2e8f0;border-radius:6px;\">{$inner}</form>";
    }
}

class InputHiddenRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $rawId = $node->getAttribute('ID');
        $id = htmlspecialchars($rawId, ENT_QUOTES, 'UTF-8');
        $val = (string)($context->request[$rawId] ?? $context->resolveTokens($node->getAttribute('DefaultValue'), false));
        $escapedVal = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

        return "<input type=\"hidden\" id=\"{$id}\" name=\"{$id}\" value=\"{$escapedVal}\" />";
    }
}

class InputSelectListRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $rawId = $node->getAttribute('ID');
        $id = htmlspecialchars($rawId, ENT_QUOTES, 'UTF-8');
        $caption = $context->resolveTokens($node->getAttribute('Caption'));
        $captionCol = $node->getAttribute('OptionCaptionColumn');
        $valueCol = $node->getAttribute('OptionValueColumn');
        $includeBlank = strtolower($node->getAttribute('IncludeBlank')) === 'true';
        $blankCaption = htmlspecialchars($node->getAttribute('IncludeBlankCaption') ?: '-- All --', ENT_QUOTES, 'UTF-8');
        $selectedVal = (string)($context->request[$rawId] ?? $context->resolveTokens($node->getAttribute('DefaultValue'), false));

        // Check for Logi <EventHandler DhtmlEvent="onchange"> child
        $onChangeAttr = '';
        $eventNode = $xpath->query('./EventHandler[@DhtmlEvent="onchange"]', $node)->item(0);
        if ($eventNode instanceof DOMElement) {
            $actionNode = $xpath->query('./Action | ./*[starts-with(name(), "Action.")]', $eventNode)->item(0);
            if ($actionNode instanceof DOMElement) {
                $actionType = $actionNode->tagName === 'Action'
                    ? $actionNode->getAttribute('Type')
                    : substr($actionNode->tagName, strlen('Action.'));

                if (strcasecmp($actionType, 'RefreshElement') === 0) {
                    $targetId = htmlspecialchars($actionNode->getAttribute('ElementID'), ENT_QUOTES, 'UTF-8');
                    $onChangeAttr = " onchange=\"logiRefreshElement(this.form, '{$targetId}')\"";
                } else {
                    $onChangeAttr = ' onchange="this.form.submit()"';
                }
            }
        }

        $rows = $engine->executeDataLayer($node, $context);
        $html = '';

        if ($caption !== '') {
            $html .= "<label for=\"{$id}\" style=\"margin-right:6px;font-weight:600;\">{$caption}</label>";
        }

        $html .= "<select id=\"{$id}\" name=\"{$id}\"{$onChangeAttr} style=\"padding:4px 8px;border:1px solid #cbd5e0;border-radius:4px;\">";

        if ($includeBlank) {
            $html .= "<option value=\"\">{$blankCaption}</option>";
        }

        foreach ($rows as $row) {
            $val = (string)($row[$valueCol] ?? '');
            $cap = (string)($row[$captionCol] ?? $val);
            $selected = ($val === $selectedVal) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . "\"{$selected}>"
                   . htmlspecialchars($cap, ENT_QUOTES, 'UTF-8')
                   . '</option>';
        }

        return $html . '</select>';
    }
}

class InputTextRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $rawId = $node->getAttribute('ID');
        $id = htmlspecialchars($rawId, ENT_QUOTES, 'UTF-8');
        $caption = $context->resolveTokens($node->getAttribute('Caption'));
        $val = (string)($context->request[$rawId] ?? $context->resolveTokens($node->getAttribute('DefaultValue'), false));
        $escapedVal = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

        $html = '';
        if ($caption !== '') {
            $html .= "<label for=\"{$id}\" style=\"margin-right:6px;font-weight:600;\">{$caption}</label>";
        }

        return $html . "<input type=\"text\" id=\"{$id}\" name=\"{$id}\" value=\"{$escapedVal}\" style=\"padding:4px 8px;border:1px solid #cbd5e0;border-radius:4px;\" />";
    }
}

class ButtonRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $id = htmlspecialchars($node->getAttribute('ID') ?: 'btnSubmit', ENT_QUOTES, 'UTF-8');
        $caption = $context->resolveTokens($node->getAttribute('Caption') ?: 'Submit');
        $class = htmlspecialchars($node->getAttribute('Class'), ENT_QUOTES, 'UTF-8');

        return "<button type=\"submit\" id=\"{$id}\" class=\"{$class}\" style=\"padding:5px 12px;background:#2b6cb0;color:#fff;border:none;border-radius:4px;cursor:pointer;\">{$caption}</button>";
    }
}