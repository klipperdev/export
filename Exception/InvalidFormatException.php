<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\Export\Exception;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class InvalidFormatException extends InvalidArgumentException
{
    private string $format;

    public function __construct(string $format, int $code = 0, ?\Throwable $previous = null)
    {
        $this->format = $format;
        $message = sprintf('The "%s" format to export the data is not valid', $format);

        parent::__construct($message, $code, $previous);
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
