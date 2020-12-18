<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\Export\ViewTransformer;

use Klipper\Component\Export\ExportedColumnInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface ExportViewTransformerInterface
{
    /**
     * @param mixed $value
     *
     * @return null|mixed
     */
    public function transformValue(ExportedColumnInterface $column, $value);

    /**
     * @param mixed $value
     */
    public function support(ExportedColumnInterface $column, $value): bool;
}
