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

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ExportedColumn implements ExportedColumnInterface
{
    private string $label;

    private string $propertyPath;

    public function __construct(string $label, string $propertyPath)
    {
        $this->label = $label;
        $this->propertyPath = $propertyPath;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }
}
