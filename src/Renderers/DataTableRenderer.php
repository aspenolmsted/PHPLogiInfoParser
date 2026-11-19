<?php

declare(strict_types=1);

namespace LogiPhp\Renderers;

use DOMElement;
use DOMXPath;
use LogiPhp\ElementRendererInterface;
use LogiPhp\LogiContext;
use LogiPhp\LogiEngine;

class DataTableRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $tableId = $node->getAttribute('ID') ?: 'dtReport';
        $isAjax = strtolower($node->getAttribute('AjaxPaging')) === 'true';
        $altRowClass = htmlspecialchars($node->getAttribute('AltRowClass'), ENT_QUOTES, 'UTF-8');
        $tableClass = htmlspecialchars($node->getAttribute('Class'), ENT_QUOTES, 'UTF-8');
        $borderColor = htmlspecialchars($node->getAttribute('BorderColor') ?: '#CCCCCC', ENT_QUOTES, 'UTF-8');
        $cellPadding = (int)($node->getAttribute('CellPadding') ?: 4);
        $cellSpacing = (int)($node->getAttribute('CellSpacing') ?: 0);

        $width = $node->getAttribute('Width');
        $widthScale = $node->getAttribute('WidthScale') ?: 'px';
        $widthStyle = $width !== '' ? "width: {$width}{$widthScale};" : '';

        // 1. Fetch DataLayer rows
        $rows = $engine->executeDataLayer($node, $context);
        $state = $context->getTableState($tableId);

        // 2. Collect Column definitions & Sort metadata
        $columns = [];
        $sortConfigs = [];
        foreach ($xpath->query('./DataTableColumn', $node) as $colNode) {
            /** @var DOMElement $colNode */
            $sortNode = $xpath->query('./DataColumnSort', $colNode)->item(0);
            $sortInfo = null;
            if ($sortNode instanceof DOMElement) {
                $sortCol = $sortNode->getAttribute('DataColumn');
                $sortType = $sortNode->getAttribute('DataType') ?: 'Text';
                $sortInfo = ['column' => $sortCol, 'type' => $sortType];
                $sortConfigs[$sortCol] = $sortType;
            }

            $columns[] = [
                'node'   => $colNode,
                'id'     => $colNode->getAttribute('ID'),
                'header' => $colNode->getAttribute('Header'),
                'class'  => $colNode->getAttribute('Class'),
                'sort'   => $sortInfo,
            ];
        }

        // 3. Apply Active DataColumnSort
        if ($state['sortCol'] !== null && isset($sortConfigs[$state['sortCol']])) {
            $col = $state['sortCol'];
            $type = $sortConfigs[$col];
            $dirMultiplier = $state['sortDir'] === 'DESC' ? -1 : 1;

            usort($rows, static function (array $a, array $b) use ($col, $type, $dirMultiplier): int {
                $valA = $a[$col] ?? '';
                $valB = $b[$col] ?? '';

                if (strcasecmp($type, 'Number') === 0) {
                    return ((float)$valA <=> (float)$valB) * $dirMultiplier;
                }
                return strnatcasecmp((string)$valA, (string)$valB) * $dirMultiplier;
            });
        }

        // 4. Apply InteractivePaging
        $pagingNode = $xpath->query('./InteractivePaging', $node)->item(0);
        $totalRows = count($rows);
        $totalPages = 1;
        $currentPage = $state['page'];
        $pagingLocation = 'Bottom';

        if ($pagingNode instanceof DOMElement) {
            $pageSize = max(1, (int)($pagingNode->getAttribute('PageRowCount') ?: 20));
            $pagingLocation = $pagingNode->getAttribute('Location') ?: 'Bottom';
            $totalPages = max(1, (int)ceil($totalRows / $pageSize));
            $currentPage = min($currentPage, $totalPages);
            $offset = ($currentPage - 1) * $pageSize;
            $rows = array_slice($rows, $offset, $pageSize);
        }

        // 5. Render Table HTML
        $cellStyle = "border: 1px solid {$borderColor}; padding: {$cellPadding}px;";
        $html = "<div id=\"" . htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') . "_wrapper\">";

        $pagerHtml = ($pagingNode instanceof DOMElement && $totalPages > 1)
            ? $this->renderPager($tableId, $currentPage, $totalPages, $isAjax, $context->request)
            : '';

        if (in_array($pagingLocation, ['Top', 'Both'], true)) {
            $html .= $pagerHtml;
        }

        $html .= "<table id=\"" . htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') . "\" "
               . "class=\"{$tableClass}\" cellspacing=\"{$cellSpacing}\" "
               . "style=\"{$widthStyle} border-collapse: collapse;\">";

        // Header Row
        $html .= "<thead><tr>";
        foreach ($columns as $col) {
            $thClass = htmlspecialchars($col['class'], ENT_QUOTES, 'UTF-8');
            $headerText = $context->resolveTokens($col['header']);

            if ($col['sort'] !== null) {
                $sCol = $col['sort']['column'];
                $isCurrentSort = ($state['sortCol'] === $sCol);
                $nextDir = ($isCurrentSort && $state['sortDir'] === 'ASC') ? 'DESC' : 'ASC';
                $indicator = $isCurrentSort ? ($state['sortDir'] === 'ASC' ? ' ▲' : ' ▼') : '';

                $url = $this->buildUrl($context->request, [
                    "{$tableId}_sort" => $sCol,
                    "{$tableId}_dir"  => $nextDir,
                    "{$tableId}_page" => 1,
                ]);
                $ajaxAttr = $isAjax ? ' data-logi-ajax="true" data-logi-target="' . htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') . '"' : '';
                $headerHtml = "<a href=\"{$url}\"{$ajaxAttr}>{$headerText}{$indicator}</a>";
            } else {
                $headerHtml = $headerText;
            }

            $html .= "<th class=\"{$thClass}\" style=\"{$cellStyle}\">{$headerHtml}</th>";
        }
        $html .= "</tr></thead><tbody>";

        // Data Rows
        foreach ($rows as $idx => $row) {
            $context->currentRow = $row;
            $trClass = ($idx % 2 === 1 && $altRowClass !== '') ? " class=\"{$altRowClass}\"" : '';
            $html .= "<tr{$trClass}>";

            foreach ($columns as $col) {
                $tdClass = htmlspecialchars($col['class'], ENT_QUOTES, 'UTF-8');
                $cellContent = $engine->renderChildren($col['node'], $context);
                $html .= "<td class=\"{$tdClass}\" style=\"{$cellStyle}\">{$cellContent}</td>";
            }
            $html .= "</tr>";
        }

        $context->currentRow = [];
        $html .= "</tbody></table>";

        if (in_array($pagingLocation, ['Bottom', 'Both'], true)) {
            $html .= $pagerHtml;
        }

        $html .= "</div>";
        return $html;
    }

    private function renderPager(string $tableId, int $currentPage, int $totalPages, bool $isAjax, array $request): string
    {
        $ajaxAttr = $isAjax ? ' data-logi-ajax="true" data-logi-target="' . htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') . '"' : '';
        $html = '<div class="logi-pager">';

        if ($currentPage > 1) {
            $prevUrl = $this->buildUrl($request, ["{$tableId}_page" => $currentPage - 1]);
            $html .= "<a href=\"{$prevUrl}\"{$ajaxAttr}>&laquo; Prev</a>";
        }

        for ($p = 1; $p <= $totalPages; $p++) {
            if ($p === $currentPage) {
                $html .= "<span class=\"active\">{$p}</span>";
            } else {
                $pageUrl = $this->buildUrl($request, ["{$tableId}_page" => $p]);
                $html .= "<a href=\"{$pageUrl}\"{$ajaxAttr}>{$p}</a>";
            }
        }

        if ($currentPage < $totalPages) {
            $nextUrl = $this->buildUrl($request, ["{$tableId}_page" => $currentPage + 1]);
            $html .= "<a href=\"{$nextUrl}\"{$ajaxAttr}>Next &raquo;</a>";
        }

        $html .= '</div>';
        return $html;
    }

    private function buildUrl(array $currentParams, array $overrides): string
    {
        unset($currentParams['rdAjaxRefresh']);
        $merged = array_merge($currentParams, $overrides);
        return '?' . htmlspecialchars(http_build_query($merged), ENT_QUOTES, 'UTF-8');
    }
}