<?php

declare(strict_types=1);

namespace LogiPhp;

use DOMDocument;
use DOMElement;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ReportCatalog
{
    public function __construct(private string $reportsDir)
    {
    }

    /**
     * Scans the reports directory and extracts metadata from each .lgx file.
     *
     * @param string[] $userRights Optional list of roles/rights held by the current user.
     * @return array<int, array<string, mixed>>
     */
    public function listReports(array $userRights = []): array
    {
        if (!is_dir($this->reportsDir)) {
            return [];
        }

        $catalog = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->reportsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'lgx') {
                continue;
            }

            $meta = $this->extractMetadata($file->getPathname());
            if ($meta === null || !$meta['showInMenu']) {
                continue;
            }

            // Enforce Logi SecurityRightID if specified
            if (!empty($meta['securityRights']) && empty(array_intersect($meta['securityRights'], $userRights))) {
                continue;
            }

            // Compute relative path/slug for URL routing (e.g., "Sales/CustomersReport")
            $relPath = substr($file->getPathname(), strlen(rtrim($this->reportsDir, '/\\')) + 1);
            $relPath = str_replace('\\', '/', $relPath);
            $meta['file'] = $relPath;
            $meta['slug'] = preg_replace('/\.lgx$/i', '', $relPath);

            $catalog[] = $meta;
        }

        // Sort by Category, then SortOrder, then Caption
        usort($catalog, static function (array $a, array $b): int {
            return [$a['category'], $a['sortOrder'], $a['caption']]
               <=> [$b['category'], $b['sortOrder'], $b['caption']];
        });

        return $catalog;
    }

    private function extractMetadata(string $filePath): ?array
    {
        $xml = file_get_contents($filePath);
        if ($xml === false) {
            return null;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            return null;
        }
        libxml_clear_errors();

        $root = $dom->documentElement;
        if (!$root instanceof DOMElement || $root->tagName !== 'Report') {
            return null;
        }

        $id = $root->getAttribute('ID') ?: pathinfo($filePath, PATHINFO_FILENAME);
        $caption = $root->getAttribute('Caption') ?: $root->getAttribute('Title') ?: $id;
        $showAttr = $root->getAttribute('ShowInMenu');
        $showInMenu = $showAttr === '' ? true : (strtolower($showAttr) === 'true');

        $rightsRaw = trim($root->getAttribute('SecurityRightID'));
        $securityRights = $rightsRaw !== ''
            ? array_map('trim', explode(',', $rightsRaw))
            : [];

        // Check optional <ReportMeta> child node
        $metaNode = $root->getElementsByTagName('ReportMeta')->item(0);
        $sortOrder = ($metaNode instanceof DOMElement && $metaNode->hasAttribute('SortOrder'))
            ? (int)$metaNode->getAttribute('SortOrder')
            : 100;
        $icon = ($metaNode instanceof DOMElement) ? $metaNode->getAttribute('Icon') : '';

        // Fallback category from subdirectory name if Category attribute isn't set
        $parentFolder = basename(dirname($filePath));
        $rootFolder = basename(rtrim($this->reportsDir, '/\\'));
        $defaultCategory = ($parentFolder !== $rootFolder) ? $parentFolder : 'General';

        return [
            'id'             => $id,
            'caption'        => $caption,
            'category'       => $root->getAttribute('Category') ?: $defaultCategory,
            'description'    => $root->getAttribute('Description'),
            'savedAt'        => $root->getAttribute('SavedAt'),
            'savedBy'        => $root->getAttribute('SavedBy'),
            'securityRights' => $securityRights,
            'showInMenu'     => $showInMenu,
            'sortOrder'      => $sortOrder,
            'icon'           => $icon,
        ];
    }
}