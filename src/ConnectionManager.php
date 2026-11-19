<?php

declare(strict_types=1);

namespace LogiPhp;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use RuntimeException;

class ConnectionManager
{
    /** @var array<string, PDO> */
    private array $activeConnections = [];

    /** @var array<string, callable|array<string, string>> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $constants = [];

    /**
     * Loads <Connections> and <Constants> from a Logi Info _Settings.lgx file.
     */
    public function loadSettingsFile(string $settingsPath): void
    {
        if (!is_file($settingsPath)) {
            throw new RuntimeException("Logi settings file not found at: {$settingsPath}");
        }

        $xml = file_get_contents($settingsPath);
        if ($xml === false) {
            throw new RuntimeException("Unable to read Logi settings file: {$settingsPath}");
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException("XML parse error in {$settingsPath}: " . implode('; ', $errors));
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // 1. Parse <Constant ID="..." Value="..." />
        foreach ($xpath->query('//Constants/Constant') as $constNode) {
            if ($constNode instanceof DOMElement) {
                $id = $constNode->getAttribute('ID');
                if ($id !== '') {
                    $this->constants[$id] = $constNode->getAttribute('Value');
                }
            }
        }

        // 2. Parse <Connection Type="..." ID="..." /> and <Connection.MySQL ID="..." />
        foreach ($xpath->query('//Connections/*[self::Connection or starts-with(name(), "Connection.")]') as $connNode) {
            if (!$connNode instanceof DOMElement) {
                continue;
            }

            $id = $connNode->getAttribute('ID');
            if ($id === '') {
                continue;
            }

            $type = $connNode->tagName === 'Connection'
                ? $connNode->getAttribute('Type')
                : substr($connNode->tagName, strlen('Connection.'));

            $config = ['Type' => $type];
            foreach ($connNode->attributes as $attr) {
                $config[$attr->nodeName] = $attr->nodeValue;
            }

            $this->definitions[$id] = $config;
        }
    }

    /**
     * Programmatically register a PDO instance or factory closure.
     */
    public function register(string $connectionId, PDO|callable $connection): void
    {
        if ($connection instanceof PDO) {
            $this->configurePdo($connection);
            $this->activeConnections[$connectionId] = $connection;
        } else {
            $this->definitions[$connectionId] = $connection;
        }
    }

    public function get(string $connectionId): PDO
    {
        if (isset($this->activeConnections[$connectionId])) {
            return $this->activeConnections[$connectionId];
        }

        if (!isset($this->definitions[$connectionId])) {
            throw new RuntimeException("Logi ConnectionID '{$connectionId}' is not defined in _Settings.lgx.");
        }

        $def = $this->definitions[$connectionId];
        $pdo = is_callable($def) ? $def() : $this->createPdoFromConfig($def);

        $this->configurePdo($pdo);
        $this->activeConnections[$connectionId] = $pdo;

        return $pdo;
    }

    /**
     * Returns global constants loaded from _Settings.lgx for @Constant.*~ token resolution.
     *
     * @return array<string, string>
     */
    public function getConstants(): array
    {
        return $this->constants;
    }

    private function createPdoFromConfig(array $cfg): PDO
    {
        $type = strtolower($cfg['Type'] ?? 'mysql');
        $server = $cfg['Server'] ?? $cfg['Host'] ?? '127.0.0.1';
        $database = $cfg['Database'] ?? $cfg['DbName'] ?? '';
        $user = $cfg['User'] ?? $cfg['Username'] ?? '';
        $password = $cfg['Password'] ?? '';
        $charset = $cfg['Charset'] ?? 'utf8mb4';

        return match ($type) {
            'mysql', 'mariadb' => new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $server, $cfg['Port'] ?? '3306', $database, $charset),
                $user,
                $password
            ),
            'postgresql', 'postgres', 'pgsql' => new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=%s', $server, $cfg['Port'] ?? '5432', $database),
                $user,
                $password
            ),
            'sqlserver', 'mssql' => new PDO(
                sprintf('sqlsrv:Server=%s%s;Database=%s', $server, isset($cfg['Port']) ? ',' . $cfg['Port'] : '', $database),
                $user,
                $password
            ),
            'sqlite' => new PDO('sqlite:' . $database),
            'pdo' => new PDO($cfg['Dsn'] ?? '', $user, $password),
            default => throw new RuntimeException("Unsupported Logi connection type: '{$cfg['Type']}'."),
        };
    }

    private function configurePdo(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
}