<?php

declare(strict_types=1);

namespace LogiPhp\Renderers;

use DOMElement;
use DOMXPath;
use LogiPhp\ElementRendererInterface;
use LogiPhp\LogiContext;
use LogiPhp\LogiEngine;

class ChartCanvasRenderer implements ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $chartId = htmlspecialchars($node->getAttribute('ID') ?: 'chartCanvas', ENT_QUOTES, 'UTF-8');
        $width = max(200, (int)($node->getAttribute('ChartWidth') ?: 650));
        $height = max(150, (int)($node->getAttribute('ChartHeight') ?: 350));
        $caption = $context->resolveTokens($node->getAttribute('ChartCaption'));

        // 1. Locate Series element (supports <Series Type="Bar"> and <Series.Bar>)
        $seriesNode = $xpath->query('./Series | ./*[starts-with(name(), "Series.")]', $node)->item(0);
        if (!$seriesNode instanceof DOMElement) {
            return "<div id=\"{$chartId}\" style=\"border:1px solid #e53e3e;padding:12px;color:#c53030;\">ChartCanvas '{$chartId}': Missing Series element.</div>";
        }

        // 2. Execute DataLayer attached to ChartCanvas or Series
        $rows = $engine->executeDataLayer($node, $context);
        if (empty($rows)) {
            $rows = $engine->executeDataLayer($seriesNode, $context);
        }

        if (empty($rows)) {
            return "<div id=\"{$chartId}\" style=\"width:{$width}px;height:120px;border:1px solid #cbd5e0;display:flex;align-items:center;justify-content:center;color:#4a5568;margin:12px 0;\">"
                 . "No data returned for chart '{$chartId}'."
                 . "</div>";
        }

        $xCol = $seriesNode->getAttribute('ChartXDataColumn') ?: $seriesNode->getAttribute('XDataColumn');
        $yCol = $seriesNode->getAttribute('ChartYDataColumn') ?: $seriesNode->getAttribute('YDataColumn');
        $barColor = htmlspecialchars($seriesNode->getAttribute('Color') ?: '#2b6cb0', ENT_QUOTES, 'UTF-8');

        $dataLabelsNode = $xpath->query('./DataLabels', $seriesNode)->item(0);
        $showDataLabels = $dataLabelsNode instanceof DOMElement;

        // 3. Axis metadata
        $xAxisNode = $xpath->query('./ChartXAxis', $node)->item(0);
        $yAxisNode = $xpath->query('./ChartYAxis', $node)->item(0);
        $xAxisCaption = $xAxisNode instanceof DOMElement ? $context->resolveTokens($xAxisNode->getAttribute('AxisCaption')) : '';
        $yAxisCaption = $yAxisNode instanceof DOMElement ? $context->resolveTokens($yAxisNode->getAttribute('AxisCaption')) : '';

        // 4. Compute scales and layout bounds
        $values = array_map(fn(array $r): float => (float)$this->getRowValue($r, $yCol, 0), $rows);
        $maxVal = max($values);
        $maxVal = $maxVal > 0 ? $maxVal : 1.0;

        $step = $this->calculateNiceStep($maxVal, 5);
        $yMax = ceil($maxVal / $step) * $step;

        $padTop = $caption !== '' ? 45 : 25;
        $padRight = 25;
        $padBottom = $xAxisCaption !== '' ? 65 : 45;
        $padLeft = $yAxisCaption !== '' ? 70 : 50;

        $plotW = $width - $padLeft - $padRight;
        $plotH = $height - $padTop - $padBottom;

        // 5. Build SVG
        $svg = "<div id=\"{$chartId}_wrapper\" class=\"logi-chart-container\" style=\"margin: 16px 0;\">";
        $svg .= "<svg id=\"{$chartId}\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\" "
              . "style=\"border: 1px solid #cbd5e0; background: #fff; display: block;\">";

        if ($caption !== '') {
            $titleX = $width / 2;
            $svg .= "<text x=\"{$titleX}\" y=\"24\" text-anchor=\"middle\" font-size=\"15\" font-weight=\"600\" fill=\"#1a202c\">{$caption}</text>";
        }

        for ($v = 0; $v <= $yMax + ($step * 0.001); $v += $step) {
            $ratio = $v / $yMax;
            $yPos = $padTop + $plotH - ($ratio * $plotH);
            $label = htmlspecialchars((string)round($v, 2), ENT_QUOTES, 'UTF-8');

            $svg .= "<line x1=\"{$padLeft}\" y1=\"{$yPos}\" x2=\"" . ($padLeft + $plotW) . "\" y2=\"{$yPos}\" stroke=\"#edf2f7\" stroke-width=\"1\" />";
            $svg .= "<text x=\"" . ($padLeft - 8) . "\" y=\"" . ($yPos + 4) . "\" text-anchor=\"end\" font-size=\"11\" fill=\"#4a5568\">{$label}</text>";
        }

        $axisBottomY = $padTop + $plotH;
        $svg .= "<line x1=\"{$padLeft}\" y1=\"{$padTop}\" x2=\"{$padLeft}\" y2=\"{$axisBottomY}\" stroke=\"#a0aec0\" stroke-width=\"1.2\" />";
        $svg .= "<line x1=\"{$padLeft}\" y1=\"{$axisBottomY}\" x2=\"" . ($padLeft + $plotW) . "\" y2=\"{$axisBottomY}\" stroke=\"#a0aec0\" stroke-width=\"1.2\" />";

        $count = count($rows);
        $slotWidth = $plotW / $count;
        $barWidth = min(56, $slotWidth * 0.62);

        foreach ($rows as $i => $row) {
            $rawX = (string)$this->getRowValue($row, $xCol, '');
            $val = (float)$this->getRowValue($row, $yCol, 0);

            $barHeight = max(0, ($val / $yMax) * $plotH);
            $barX = $padLeft + ($i * $slotWidth) + (($slotWidth - $barWidth) / 2);
            $barY = $axisBottomY - $barHeight;
            $centerX = $barX + ($barWidth / 2);

            $escapedX = htmlspecialchars($rawX, ENT_QUOTES, 'UTF-8');
            $escapedVal = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');

            $svg .= "<g class=\"logi-bar\">"
                  . "<title>{$escapedX}: {$escapedVal}</title>"
                  . "<rect x=\"{$barX}\" y=\"{$barY}\" width=\"{$barWidth}\" height=\"{$barHeight}\" fill=\"{$barColor}\" rx=\"2\" />";

            if ($showDataLabels) {
                $labelY = max($padTop + 12, $barY - 5);
                $svg .= "<text x=\"{$centerX}\" y=\"{$labelY}\" text-anchor=\"middle\" font-size=\"11\" font-weight=\"600\" fill=\"#2d3748\">{$escapedVal}</text>";
            }

            $svg .= "<text x=\"{$centerX}\" y=\"" . ($axisBottomY + 18) . "\" text-anchor=\"middle\" font-size=\"11\" fill=\"#4a5568\">{$escapedX}</text>";
            $svg .= "</g>";
        }

        if ($xAxisCaption !== '') {
            $xCaptionX = $padLeft + ($plotW / 2);
            $xCaptionY = $height - 12;
            $svg .= "<text x=\"{$xCaptionX}\" y=\"{$xCaptionY}\" text-anchor=\"middle\" font-size=\"12\" font-weight=\"600\" fill=\"#2d3748\">{$xAxisCaption}</text>";
        }

        if ($yAxisCaption !== '') {
            $yCaptionY = $padTop + ($plotH / 2);
            $svg .= "<text x=\"18\" y=\"{$yCaptionY}\" text-anchor=\"middle\" font-size=\"12\" font-weight=\"600\" fill=\"#2d3748\" "
                  . "transform=\"rotate(-90, 18, {$yCaptionY})\">{$yAxisCaption}</text>";
        }

        $svg .= "</svg></div>";
        return $svg;
    }

    private function getRowValue(array $row, string $column, mixed $default): mixed
    {
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        $lower = strtolower($column);
        foreach ($row as $k => $v) {
            if (strtolower((string)$k) === $lower) {
                return $v;
            }
        }
        return $default;
    }

    private function calculateNiceStep(float $maxVal, int $targetTicks): float
    {
        $roughStep = $maxVal / $targetTicks;
        $magnitude = pow(10, floor(log10($roughStep)));
        $residual = $roughStep / $magnitude;

        if ($residual <= 1.5) {
            $nice = 1;
        } elseif ($residual <= 3) {
            $nice = 2;
        } elseif ($residual <= 7) {
            $nice = 5;
        } else {
            $nice = 10;
        }

        return $nice * $magnitude;
    }
}