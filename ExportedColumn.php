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
class ExportedColumn implements ExportedColumnInterface
{
    private string $label;

    private string $propertyPath;

    private ChildMetadataInterface $metadata;

    public function __construct(string $label, string $propertyPath, ChildMetadataInterface $metadata)
    {
        $this->label = $label;
        $this->propertyPath = $propertyPath;
        $this->metadata = $metadata;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    public function getMetadata(): ChildMetadataInterface
    {
        return $this->metadata;
    }
}
