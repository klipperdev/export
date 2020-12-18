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

use Klipper\Component\Metadata\ChildMetadataInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface ExportedColumnInterface
{
    public function getLabel(): string;

    public function getPropertyPath(): string;

    public function getMetadata(): ChildMetadataInterface;
}
