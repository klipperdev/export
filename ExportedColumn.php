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

    private ?string $transformer;

    public function __construct(string $propertyPath, ?string $label = null, ?string $transformer = null)
    {
        $this->label = $label ?: $propertyPath;
        $this->propertyPath = $propertyPath;
        $this->transformer = $transformer;
    }

    public static function create(string $propertyPath, ?string $label = null, ?string $transformer = null): self
    {
        return new static($propertyPath, $label, $transformer);
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPropertyPath(): string
    {
        return $this->propertyPath;
    }

    public function setTransformer(?string $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    public function getTransformer(): ?string
    {
        return $this->transformer;
    }
}
