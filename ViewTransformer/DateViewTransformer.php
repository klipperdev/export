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
class DateViewTransformer implements ExportViewTransformerInterface
{
    public function transformValue(ExportedColumnInterface $column, $value)
    {
        $formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE,
            date_default_timezone_get(),
            \IntlDateFormatter::GREGORIAN,
            null
        );

        return $formatter->format($value);
    }

    public function support(ExportedColumnInterface $column, $value): bool
    {
        return 'date' === $column->getTransformer() && $value instanceof \DateTime;
    }
}
