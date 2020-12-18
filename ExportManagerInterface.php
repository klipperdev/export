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

use Doctrine\ORM\Query;
use Klipper\Component\Export\Exception\ExportNotFoundException;
use Klipper\Component\Export\Exception\InvalidArgumentException;
use Klipper\Component\Export\Exception\InvalidFormatException;
use Klipper\Component\Export\Exception\RuntimeException;
use Klipper\Component\Export\ViewTransformer\ExportViewTransformerInterface;
use Klipper\Component\Metadata\ObjectMetadataInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface ExportManagerInterface
{
    public function addViewTransformer(ExportViewTransformerInterface $viewTransformer): void;

    /**
     * Build the export data.
     *
     * @param ObjectMetadataInterface|string $rootMetadata
     * @param string[]                       $fields       The selected field or associations paths
     *                                                     By default, only the fields of the root metadata
     * @param bool                           $headerLabels Check if the first column display the translated labels
     *                                                     or the property paths
     *
     * @throws InvalidFormatException
     * @throws InvalidArgumentException
     * @throws ExportNotFoundException
     * @throws RuntimeException
     */
    public function exportQuery($rootMetadata, Query $query, array $fields = [], string $format = 'xlsx', bool $headerLabels = true): ExportedDataInterface;
}
