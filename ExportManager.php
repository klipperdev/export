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
use Doctrine\ORM\Tools\Pagination\Paginator;
use Klipper\Component\Export\Exception\InvalidArgumentException;
use Klipper\Component\Export\Exception\InvalidFormatException;
use Klipper\Component\Export\Exception\RuntimeException;
use Klipper\Component\Metadata\Exception\ObjectMetadataNotFoundException;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Metadata\ObjectMetadataInterface;
use Klipper\Component\Security\Permission\PermissionManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ExportManager implements ExportManagerInterface
{
    private MetadataManagerInterface $metadataManager;

    private PropertyAccessorInterface $propertyAccessor;

    private ?PermissionManagerInterface $permissionManager;

    private int $batchSize;

    public function __construct(
        MetadataManagerInterface $metadataManager,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?PermissionManagerInterface $permissionManager = null,
        int $batchSize = 100
    ) {
        $this->metadataManager = $metadataManager;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->permissionManager = $permissionManager;
        $this->batchSize = $batchSize;
    }

    public function exportQuery($rootMetadata, Query $query, array $fields = [], string $format = 'xlsx'): ExportedDataInterface
    {
        $spreadsheet = new Spreadsheet();

        try {
            $rootMetadata = $rootMetadata instanceof ObjectMetadataInterface
                ? $rootMetadata
                : $this->metadataManager->getByName($rootMetadata);
            $writer = IOFactory::createWriter($spreadsheet, ucfirst($format));
            $mimeType = MimeTypes::getDefault()->getMimeTypes($format);

            if (empty($mimeType)) {
                throw new InvalidArgumentException(sprintf('The mime type cannot be found for the extension "%s"', $format));
            }
        } catch (ObjectMetadataNotFoundException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new InvalidFormatException($format, $e->getCode(), $e);
        }

        // Don't use the doctrine query iterator, because Iterating results is not possible with queries that fetch-join
        // a collection-valued association. The nature of such SQL result sets is not suitable for incremental hydration.
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $firstResult = 0;
            $line = 2;
            $endResult = false;

            foreach ($fields as $i => $field) {
                $sheet->setCellValueByColumnAndRow($i + 1, 1, $field);
            }

            while (!$endResult) {
                $paginationQuery = clone $query;
                $paginationQuery->setFirstResult($firstResult);
                $paginationQuery->setMaxResults($this->batchSize);
                $firstResult += $this->batchSize;

                $paginator = new Paginator($paginationQuery);
                $iterator = $paginator->getIterator();
                $endResult = 0 === $iterator->count();

                foreach ($iterator as $object) {
                    foreach ($fields as $i => $field) {
                        try {
                            $fieldValue = $this->propertyAccessor->getValue($object, $field);
                        } catch (UnexpectedTypeException $e) {
                            $fieldValue = null;
                        }

                        $sheet->setCellValueByColumnAndRow($i + 1, $line, $fieldValue);
                    }

                    ++$line;
                }
            }

            foreach ($fields as $i => $field) {
                $sheet->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
            }

            return new ExportedData($spreadsheet, $writer, $mimeType[0]);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
