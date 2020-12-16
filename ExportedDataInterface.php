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
interface ExportedDataInterface
{
    public function getSpreadsheet(): Spreadsheet;

    public function getWriter(): IWriter;

    public function getMimeType(): string;
}
