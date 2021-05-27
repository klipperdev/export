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
interface ExportedColumnInterface
{
    /**
     * @return static
     */
    public static function create(string $propertyPath, ?string $label = null, ?string $transformer = null);

    /**
     * @return static
     */
    public function setLabel(string $label);

    public function getLabel(): string;

    public function getPropertyPath(): string;

    /**
     * @return static
     */
    public function setTransformer(?string $transformer);

    public function getTransformer(): ?string;
}
