<?php

declare(strict_types=1);

namespace LogiPhp;

class LogiContext
{
    public array $request = [];
    public array $session = [];
    public array $constants = [];
    public array $currentRow = [];

    public function __construct(array $request = [], array $session = [], array $constants = [])
    {
        $this->request = $request;
        $this->session = $session;
        $this->constants = $constants;
    }

    public function resolveTokens(string $input, bool $escapeHtml = true): string
    {
        if (!str_contains($input, '@')) {
            return $escapeHtml ? htmlspecialchars($input, ENT_QUOTES, 'UTF-8') : $input;
        }

        $resolved = preg_replace_callback(
            '/@(Data|Request|Session|Constant)\.([a-zA-Z0-9_]+)~/i',
            function (array $matches) use ($escapeHtml): string {
                $scope = strtolower($matches[1]);
                $key = $matches[2];

                $val = match ($scope) {
                    'data'     => $this->currentRow[$key] ?? '',
                    'request'  => $this->request[$key] ?? '',
                    'session'  => $this->session[$key] ?? '',
                    'constant' => $this->constants[$key] ?? '',
                    default    => '',
                };

                $strVal = (string)$val;
                return $escapeHtml ? htmlspecialchars($strVal, ENT_QUOTES, 'UTF-8') : $strVal;
            },
            $input
        );

        return $resolved ?? '';
    }

    public function getTableState(string $tableId): array
    {
        return [
            'sortCol' => $this->request["{$tableId}_sort"] ?? null,
            'sortDir' => strtoupper($this->request["{$tableId}_dir"] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC',
            'page'    => max(1, (int)($this->request["{$tableId}_page"] ?? 1)),
        ];
    }
}