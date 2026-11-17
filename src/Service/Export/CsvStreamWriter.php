<?php
declare(strict_types=1);

namespace App\Service\Export;

use RuntimeException;

/**
 * CSV point-virgule, BOM UTF-8, échappement RFC 4180.
 * $escape vide : le backslash par défaut de PHP casse un nom qui finit par \".
 */
final class CsvStreamWriter
{
    /**
     * @param resource $handle
     */
    private function __construct(private $handle)
    {
    }

    public static function open(): self
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('Impossible d\'ouvrir le flux CSV.');
        }
        fwrite($handle, "\xEF\xBB\xBF");

        return new self($handle);
    }

    /**
     * @param list<int|string|null> $row
     */
    public function write(array $row): void
    {
        $fields = [];
        foreach ($row as $value) {
            $fields[] = $value === null ? '' : (string)$value;
        }
        $written = fputcsv($this->handle, $fields, ';', '"', '');
        if ($written === false) {
            throw new RuntimeException('Écriture CSV impossible.');
        }
    }

    /**
     * @return resource
     */
    public function detach(): mixed
    {
        rewind($this->handle);

        return $this->handle;
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
