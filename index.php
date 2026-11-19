<?php

declare(strict_types=1);

require_once __DIR__ . '/src/ConnectionManager.php';
require_once __DIR__ . '/src/LogiContext.php';
require_once __DIR__ . '/src/LogiEngine.php';
require_once __DIR__ . '/src/ReportCatalog.php';
require_once __DIR__ . '/src/Renderers/LayoutRenderers.php';
require_once __DIR__ . '/src/Renderers/DataTableRenderer.php';
require_once __DIR__ . '/src/Renderers/ChartRenderer.php';

use LogiPhp\ConnectionManager;
use LogiPhp\LogiContext;
use LogiPhp\LogiEngine;
use LogiPhp\ReportCatalog;
use LogiPhp\Renderers\ChartCanvasRenderer;
use LogiPhp\Renderers\DataTableRenderer;
use LogiPhp\Renderers\DivisionRenderer;
use LogiPhp\Renderers\LabelRenderer;
use LogiPhp\Renderers\LineBreakRenderer;
use LogiPhp\Renderers\ReportRenderer;

session_start();
// 1. Load named connections and global constants from _Settings.lgx
$connections = new ConnectionManager();
$connections->loadSettingsFile(__DIR__ . '/_Settings.lgx');

// ... (Engine & ReportCatalog initialization stays the same) ...

// 6. Pass global constants from _Settings.lgx into LogiContext
$context = new LogiContext($_GET, $_SESSION, $connections->getConstants());

// 2. Initialize Engine & Register Logi Elements
$engine = new LogiEngine($connections);
$engine->registerRenderer('Report', new ReportRenderer());
$engine->registerRenderer('Division', new DivisionRenderer());
$engine->registerRenderer('Label', new LabelRenderer());
$engine->registerRenderer('LineBreak', new LineBreakRenderer());
$engine->registerRenderer('DataTable', new DataTableRenderer());

$chartRenderer = new ChartCanvasRenderer();
$engine->registerRenderer('ChartCanvas', $chartRenderer);
$engine->registerRenderer('Chart.XY', $chartRenderer);
$engine->registerRenderer('Chart', $chartRenderer);

// 3. Scan reports directory & resolve requested report
$reportsDir = __DIR__ . '/reports';
$catalog = new ReportCatalog($reportsDir);
$userRights = $_SESSION['rights'] ?? ['Admin', 'SalesUser'];
$requestedReport = $_GET['rdReport'] ?? null;

// 4. Render Report Directory Catalog if no ?rdReport= parameter is specified
if ($requestedReport === null || $requestedReport === '') {
    $reports = $catalog->listReports($userRights);
    $grouped = [];
    foreach ($reports as $report) {
        $grouped[$report['category']][] = $report;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logi PHP Report Portal</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 32px; color: #1a202c; background: #f7fafc; }
            .portal-container { max-width: 900px; margin: 0 auto; }
            h1 { font-size: 1.75rem; margin-bottom: 4px; }
            .subtitle { color: #4a5568; margin-bottom: 28px; font-size: 0.95rem; }
            .category-section { margin-bottom: 28px; }
            .category-title { font-size: 1.1rem; font-weight: 600; color: #2b6cb0; border-bottom: 2px solid #cbd5e0; padding-bottom: 6px; margin-bottom: 12px; }
            .report-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 14px; }
            .report-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; text-decoration: none; color: inherit; display: block; transition: border-color 0.15s, box-shadow 0.15s; }
            .report-card:hover { border-color: #2b6cb0; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06); }
            .report-card-title { font-weight: 600; font-size: 1.05rem; color: #2d3748; margin-bottom: 6px; }
            .report-card-desc { font-size: 0.88rem; color: #4a5568; line-height: 1.4; }
            .report-card-meta { margin-top: 10px; font-size: 0.78rem; color: #718096; }
            .empty-state { background: #fff; border: 1px solid #e2e8f0; padding: 24px; border-radius: 6px; color: #4a5568; }
        </style>
    </head>
    <body>
        <div class="portal-container">
            <h1>Reports Directory</h1>
            <p class="subtitle">Select a report definition from <code>/reports</code> to execute and render.</p>

            <?php if (empty($grouped)): ?>
                <div class="empty-state">
                    No accessible <code>.lgx</code> reports found in <code><?= htmlspecialchars($reportsDir, ENT_QUOTES, 'UTF-8') ?></code>.
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $category => $items): ?>
                    <div class="category-section">
                        <div class="category-title"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="report-grid">
                            <?php foreach ($items as $item): ?>
                                <a class="report-card" href="?rdReport=<?= urlencode($item['slug']) ?>">
                                    <div class="report-card-title"><?= htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ($item['description'] !== ''): ?>
                                        <div class="report-card-desc"><?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <div class="report-card-meta">
                                        ID: <?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($item['securityRights'])): ?>
                                            &bull; Roles: <?= htmlspecialchars(implode(', ', $item['securityRights']), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 5. Validate requested report path (prevent directory traversal)
$safeSlug = str_replace(['..', "\0"], '', (string)$requestedReport);
$reportsRoot = realpath($reportsDir);
$lgxPath = $reportsRoot !== false ? realpath($reportsRoot . DIRECTORY_SEPARATOR . $safeSlug . '.lgx') : false;

if ($lgxPath === false || $reportsRoot === false || !str_starts_with($lgxPath, $reportsRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:24px;">'
       . '<h2>Report Not Found</h2>'
       . '<p>The requested report does not exist. <a href="index.php">Return to Reports Directory</a></p>'
       . '</body></html>';
    exit;
}

// 6. Execute & Render Report (Full Page or AJAX Partial)
$context = new LogiContext($_GET, $_SESSION);
$ajaxTarget = $_GET['rdAjaxRefresh'] ?? null;

$output = $engine->renderReport($lgxPath, $context, $ajaxTarget);

// Inject a lightweight back-to-directory bar on full page renders
if ($ajaxTarget === null) {
    $navBar = '<div style="background:#edf2f7;border-bottom:1px solid #cbd5e0;padding:8px 24px;margin:-24px -24px 20px -24px;font-size:0.88rem;">'
            . '<a href="index.php" style="color:#2b6cb0;text-decoration:none;font-weight:600;">&larr; All Reports</a>'
            . '</div>';
    $output = preg_replace('/(<body[^>]*>)/i', '$1' . $navBar, $output, 1) ?? $output;
}

echo $output;