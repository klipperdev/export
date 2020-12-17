<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ExportedData implements ExportedDataInterface
{
    private Spreadsheet $spreadsheet;

    private IWriter $writer;

    private string $mimeType;

    /**
     * @var ExportedColumnInterface[]
     */
    private array $columns;

    /**
     * @param ExportedColumnInterface[] $columns
     */
    public function __construct(Spreadsheet $spreadsheet, IWriter $writer, string $mimeType, array $columns)
    {
        $this->spreadsheet = $spreadsheet;
        $this->writer = $writer;
        $this->mimeType = $mimeType;
        $this->columns = $columns;
    }

    public function getSpreadsheet(): Spreadsheet
    {
        return $this->spreadsheet;
    }

    public function getWriter(): IWriter
    {
        return $this->writer;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }
}
