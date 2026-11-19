<?php

declare(strict_types=1);

namespace LogiPhp\Renderers;

use DOMElement;
use LogiPhp\ElementRendererInterface;
use LogiPhp\LogiContext;
use LogiPhp\LogiEngine;

class ReportRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $title = htmlspecialchars($node->getAttribute('ID') ?: 'Logi Report', ENT_QUOTES, 'UTF-8');
        $content = $engine->renderChildren($node, $context);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 24px; color: #222; }
                .ThemeTextLargest { font-size: 1.5rem; font-weight: 600; color: #1a202c; }
                .ThemeDataTable { border-collapse: collapse; font-size: 0.92rem; }
                .ThemeDataTable th { background-color: #f4f6f8; font-weight: 600; text-align: left; border-bottom: 2px solid #ccc; }
                .ThemeDataTable th a { color: inherit; text-decoration: none; display: flex; justify-content: space-between; align-items: center; }
                .ThemeDataTable th a:hover { text-decoration: underline; }
                .ThemeAlternatingRow { background-color: #f9fafb; }
                .ThemeAlignRight { text-align: right !important; }
                .ThemeAlignRight a { justify-content: flex-end; gap: 6px; }
                .logi-pager { margin-top: 12px; display: flex; gap: 6px; align-items: center; font-size: 0.9rem; }
                .logi-pager a, .logi-pager span { padding: 4px 10px; border: 1px solid #ccc; border-radius: 3px; text-decoration: none; color: #333; }
                .logi-pager span.active { background-color: #2b6cb0; color: #fff; border-color: #2b6cb0; font-weight: 600; }
            </style>
        </head>
        <body>
            {$content}
            <script>
            document.addEventListener('click', async (e) => {
                const link = e.target.closest('a[data-logi-ajax="true"]');
                if (!link) return;
                e.preventDefault();
                const targetId = link.getAttribute('data-logi-target');
                const url = new URL(link.href);
                url.searchParams.set('rdAjaxRefresh', targetId);

                const res = await fetch(url.toString());
                if (res.ok) {
                    const html = await res.text();
                    const container = document.getElementById(targetId + '_wrapper');
                    if (container) container.outerHTML = html;
                    window.history.pushState({}, '', link.href);
                }
            });
            </script>
        </body>
        </html>
        HTML;
    }
}

class DivisionRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $id = htmlspecialchars($node->getAttribute('ID'), ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($node->getAttribute('Class'), ENT_QUOTES, 'UTF-8');
        $tag = strtolower($node->getAttribute('HtmlDiv')) === 'true' ? 'div' : 'span';
        $inner = $engine->renderChildren($node, $context);

        $idAttr = $id !== '' ? " id=\"{$id}\"" : '';
        $classAttr = $class !== '' ? " class=\"{$class}\"" : '';

        return "<{$tag}{$idAttr}{$classAttr}>{$inner}</{$tag}>";
    }
}

class LabelRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $rawCaption = $node->getAttribute('Caption');
        $isHtml = strcasecmp($node->getAttribute('Format'), 'HTML') === 0;
        $caption = $context->resolveTokens($rawCaption, !$isHtml);

        $id = htmlspecialchars($node->getAttribute('ID'), ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($node->getAttribute('Class'), ENT_QUOTES, 'UTF-8');

        $idAttr = $id !== '' ? " id=\"{$id}\"" : '';
        $classAttr = $class !== '' ? " class=\"{$class}\"" : '';

        return "<span{$idAttr}{$classAttr}>{$caption}</span>";
    }
}

class LineBreakRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $count = max(1, (int)($node->getAttribute('LineCount') ?: 1));
        return str_repeat('<br>', $count);
    }
}