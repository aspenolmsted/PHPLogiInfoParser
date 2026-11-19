<?php

declare(strict_types=1);

namespace LogiPhp;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use RuntimeException;

interface ElementRendererInterface
{
    public function render(DOMElement $node, LogiEngine $engine, LogiContext $context): string;
}

class LogiEngine
{
    /** @var array<string, ElementRendererInterface> */
    private array $renderers = [];
    private ConnectionManager $connections;

    public function __construct(ConnectionManager $connections)
    {
        $this->connections = $connections;
    }

    public function registerRenderer(string $elementName, ElementRendererInterface $renderer): void
    {
        $this->renderers[$elementName] = $renderer;
    }

    public function getConnections(): ConnectionManager
    {
        return $this->connections;
    }

    public function renderReport(string $lgxPath, LogiContext $context, ?string $targetElementId = null): string
    {
        if (!is_file($lgxPath)) {
            throw new RuntimeException("LGX file not found at: {$lgxPath}");
        }

        $xmlContent = file_get_contents($lgxPath);
        if ($xmlContent === false) {
            throw new RuntimeException("Unable to read LGX file: {$lgxPath}");
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (!$dom->loadXML($xmlContent)) {
            $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException("XML parse error in {$lgxPath}: " . implode('; ', $errors));
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Apply <DefaultRequestParams> fallback values when missing or empty in $context->request
        foreach ($xpath->query('//DefaultRequestParams') as $defaultNode) {
            if ($defaultNode instanceof DOMElement) {
                foreach ($defaultNode->attributes as $attr) {
                    if (!isset($context->request[$attr->nodeName]) || $context->request[$attr->nodeName] === '') {
                        $context->request[$attr->nodeName] = $attr->nodeValue;
                    }
                }
            }
        }

        // Support AJAX partial refresh for specific element IDs (e.g., dtCustomers, dtProducts)
        if ($targetElementId !== null) {
            $nodes = $xpath->query("//*[@ID='{$targetElementId}']");
            if ($nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
                return $this->renderNode($nodes->item(0), $context);
            }
            throw new RuntimeException("Target element ID '{$targetElementId}' not found.");
        }

        return $this->renderNode($dom->documentElement, $context);
    }

    public function renderNode(DOMElement $node, LogiContext $context): string
    {
        if (strtolower($node->getAttribute('IdeDisplay')) === 'false') {
            return '';
        }

        $tag = $node->tagName;

        // Ignore design-time, catalog metadata, and parameter declaration nodes during visual rendering
        if (in_array($tag, ['ideTestParams', 'ReportMeta', 'DefaultRequestParams'], true)) {
            return '';
        }

        if (isset($this->renderers[$tag])) {
            return $this->renderers[$tag]->render($node, $this, $context);
        }

        return $this->renderChildren($node, $context);
    }

    public function renderChildren(DOMElement $node, LogiContext $context): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $html .= $this->renderNode($child, $context);
            }
        }
        return $html;
    }

    /**
     * Executes child DataLayer (SQL or Static) with optional <SqlParameters> PDO bindings.
     */
    public function executeDataLayer(DOMElement $parentNode, LogiContext $context): array
    {
        $xpath = new DOMXPath($parentNode->ownerDocument);
        $layers = $xpath->query('./DataLayer | ./*[starts-with(name(), "DataLayer.")]', $parentNode);

        if ($layers->length === 0) {
            return [];
        }

        /** @var DOMElement $layer */
        $layer = $layers->item(0);
        $type = $layer->tagName === 'DataLayer'
            ? $layer->getAttribute('Type')
            : substr($layer->tagName, strlen('DataLayer.'));

        $rows = [];

        if (strcasecmp($type, 'SQL') === 0) {
            $connId = $layer->getAttribute('ConnectionID');
            $pdo = $this->connections->get($connId);

            // Resolve tokens without HTML-escaping SQL syntax
            $sql = $context->resolveTokens($layer->getAttribute('Source'), false);
            $paramNodes = $xpath->query('./SqlParameters/SqlParameter', $layer);

            if ($paramNodes->length > 0) {
                $stmt = $pdo->prepare($sql);
                foreach ($paramNodes as $pNode) {
                    if ($pNode instanceof DOMElement) {
                        $paramName = ltrim($pNode->getAttribute('ID'), ':@');
                        $rawVal = $context->resolveTokens($pNode->getAttribute('Value'), false);
                        $pdoType = strcasecmp($pNode->getAttribute('DataType'), 'Number') === 0
                            ? \PDO::PARAM_INT
                            : \PDO::PARAM_STR;
                        $boundVal = $pdoType === PDO::PARAM_INT ? (int)$rawVal : $rawVal;
                        $stmt->bindValue(':' . $paramName, $boundVal, $pdoType);
                    }
                }
                $stmt->execute();
                $rows = $stmt->fetchAll();
            } else {
                $stmt = $pdo->query($sql);
                $rows = $stmt ? $stmt->fetchAll() : [];
            }
        } elseif (strcasecmp($type, 'Static') === 0) {
            foreach ($xpath->query('./StaticDataRow', $layer) as $staticRow) {
                $row = [];
                foreach ($staticRow->attributes as $attr) {
                    $row[$attr->nodeName] = $attr->nodeValue;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }
}