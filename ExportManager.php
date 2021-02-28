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
use Klipper\Component\Export\Exception\ExportNotFoundException;
use Klipper\Component\Export\Exception\InvalidArgumentException;
use Klipper\Component\Export\Exception\InvalidFormatException;
use Klipper\Component\Export\Exception\RuntimeException;
use Klipper\Component\Export\ViewTransformer\ExportViewTransformerInterface;
use Klipper\Component\Metadata\AssociationMetadataInterface;
use Klipper\Component\Metadata\Exception\ObjectMetadataNotFoundException;
use Klipper\Component\Metadata\FieldMetadataInterface;
use Klipper\Component\Metadata\MetadataInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Metadata\ObjectMetadataInterface;
use Klipper\Component\Metadata\Util\MetadataUtil;
use Klipper\Component\Security\Permission\FieldVote;
use Klipper\Component\Security\Permission\PermVote;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use function Symfony\Component\String\b;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ExportManager implements ExportManagerInterface
{
    private const DEFAULT_ROW_HEIGHT = 15;

    private MetadataManagerInterface $metadataManager;

    private TranslatorInterface $translator;

    private PropertyAccessorInterface $propertyAccessor;

    private ?AuthorizationCheckerInterface $authChecker;

    private int $batchSize;

    /**
     * @var ExportViewTransformerInterface[]
     */
    private array $viewTransformers = [];

    public function __construct(
        MetadataManagerInterface $metadataManager,
        TranslatorInterface $translator,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?AuthorizationCheckerInterface $authChecker = null,
        int $batchSize = 100
    ) {
        $this->metadataManager = $metadataManager;
        $this->translator = $translator;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->authChecker = $authChecker;
        $this->batchSize = $batchSize;
    }

    public function addViewTransformer(ExportViewTransformerInterface $viewTransformer): void
    {
        $this->viewTransformers[] = $viewTransformer;
    }

    public function exportQuery($rootMetadata, Query $query, array $fields = [], string $format = 'xlsx', bool $headerLabels = true): ExportedDataInterface
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
            throw new ExportNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new InvalidFormatException($format, $e->getCode(), $e);
        }

        if (!$this->isObjectExportable($rootMetadata)) {
            throw new ExportNotFoundException(sprintf('The %s resource is not found', $rootMetadata->getName()));
        }

        // Don't use the doctrine query iterator, because Iterating results is not possible with queries that fetch-join
        // a collection-valued association. The nature of such SQL result sets is not suitable for incremental hydration.
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $firstResult = 0;
            $line = 2;
            $endResult = false;
            $columns = $this->getExportedColumns($rootMetadata, $fields);

            foreach ($columns as $i => $column) {
                $sheet->setCellValueByColumnAndRow(
                    $i + 1,
                    1,
                    $headerLabels ? $column->getLabel() : $column->getPropertyPath()
                );
            }

            while (!$endResult) {
                $query->setFirstResult($firstResult);
                $query->setMaxResults($this->batchSize);
                $firstResult += $this->batchSize;

                $paginator = new Paginator($query);
                $iterator = $paginator->getIterator();
                $endResult = 0 === $iterator->count();

                foreach ($iterator as $object) {
                    foreach ($columns as $i => $column) {
                        $columnValue = $this->getColumnValue($column, $object);

                        if (\is_array($columnValue)) {
                            foreach ($columnValue as $colVal) {
                                // Replace value to force the auto conversion of value by sheet
                                $prevVal = $sheet->getCellByColumnAndRow($i + 1, $line)->getValue();
                                $sheet->setCellValueByColumnAndRow($i + 1, $line, $colVal);

                                if (!empty($prevVal)) {
                                    $colVal = $prevVal.PHP_EOL.$sheet->getCellByColumnAndRow($i + 1, $line)->getValue();
                                    $sheet->setCellValueByColumnAndRow($i + 1, $line, $colVal);
                                }
                            }
                        } else {
                            $sheet->setCellValueByColumnAndRow($i + 1, $line, $columnValue);
                        }

                        $rowDim = $sheet->getRowDimension($line);
                        $finalVal = $sheet->getCellByColumnAndRow($i + 1, $line)->getValue();
                        $lineHeight = self::DEFAULT_ROW_HEIGHT;

                        if (\is_string($finalVal)) {
                            $lineHeight = self::DEFAULT_ROW_HEIGHT * \count(b($finalVal)->split("\n"));
                        }

                        if ($lineHeight > $rowDim->getRowHeight()) {
                            $rowDim->setRowHeight($lineHeight);
                        }
                    }

                    ++$line;
                }
            }

            foreach ($columns as $i => $column) {
                $sheet->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
            }

            return new ExportedData($spreadsheet, $writer, $mimeType[0], $columns);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array|object $object
     *
     * @return null|mixed
     */
    private function getColumnValue(ExportedColumnInterface $column, $object)
    {
        try {
            $values = $this->getRecursivePathValue($column, $column->getPropertyPath(), $object, []);

            if (empty($values)) {
                $columnValue = null;
            } elseif (1 === \count($values)) {
                /** @var null|mixed $columnValue */
                $columnValue = $values[0];
            } else {
                $columnValue = $values;
            }
        } catch (UnexpectedTypeException | NoSuchPropertyException $e) {
            $columnValue = null;
        }

        return $columnValue;
    }

    /**
     * @param null|mixed $value
     */
    private function getRecursivePathValue(ExportedColumnInterface $column, string $path, $value, array $values): array
    {
        $parts = explode('[i].', $path);

        if (\count($parts) > 1) {
            $currentPath = array_shift($parts);
            $partValue = $this->getPathValue($column, $currentPath, $value);

            if (\is_array($partValue) || $partValue instanceof \Countable) {
                for ($i = 0; $i < \count($partValue); ++$i) {
                    $itemValue = $partValue[$i];
                    $values = $this->getRecursivePathValue($column, implode('[i].', $parts), $itemValue, $values);
                }

                return $values;
            }

            return $this->getRecursivePathValue($column, implode('[i].', $parts), $partValue, $values);
        }

        $values[] = $this->getPathValue($column, $path, $value);

        return $values;
    }

    /**
     * @param null|array|object $object
     *
     * @return null|mixed
     */
    private function getPathValue(ExportedColumnInterface $column, string $path, $object)
    {
        try {
            $value = $this->propertyAccessor->getValue($object, $path);

            foreach ($this->viewTransformers as $viewTransformer) {
                if ($viewTransformer->support($column, $value)) {
                    $value = $viewTransformer->transformValue($column, $value);

                    break;
                }
            }

            return $value;
        } catch (UnexpectedTypeException | NoSuchPropertyException $e) {
            return null;
        }
    }

    /**
     * @param ExportedColumnInterface[]|string[] $fields
     *
     * @return ExportedColumn[]
     */
    private function getExportedColumns(ObjectMetadataInterface $metadata, array $fields): array
    {
        $validFields = [];
        $addDefaultFields = false;

        if (\count($fields) > 0
            && ((\is_string($fields[0]) && '+' === $fields[0])
                || ($fields[0] instanceof ExportedColumnInterface && '+' === $fields[0]->getPropertyPath())
            )
        ) {
            $addDefaultFields = true;
            array_shift($fields);
        }

        if (empty($fields) || $addDefaultFields) {
            foreach ($metadata->getFields() as $fieldMetadata) {
                $fields[] = $fieldMetadata->getField();
            }

            foreach ($metadata->getAssociations() as $associationMetadata) {
                $fields[] = $associationMetadata->getAssociation();
            }
        }

        foreach ($fields as $field) {
            if ($field instanceof ExportedColumnInterface) {
                $validFields[] = $field;

                continue;
            }

            $fieldPaths = explode('.', $field);
            $pathMetadata = $metadata;
            $labelPrefix = '';
            $propertyPathPrefix = '';

            foreach ($fieldPaths as $i => $fieldPath) {
                if ($pathMetadata->hasFieldByName($fieldPath)) {
                    $fieldMeta = $pathMetadata->getFieldByName($fieldPath);

                    if ($this->isFieldExportable($fieldMeta)) {
                        $validFields[] = new ExportedColumn(
                            $labelPrefix.$this->getMetadataLabel($fieldMeta),
                            $propertyPathPrefix.$fieldMeta->getName()
                        );
                    } else {
                        break;
                    }
                } elseif ($pathMetadata->hasAssociationByName($fieldPath)) {
                    $assoMeta = $pathMetadata->getAssociationByName($fieldPath);
                    $labelPrefix .= $this->getMetadataLabel($assoMeta).' > ';
                    $propertyPathPrefix .= $assoMeta->getName().'.';

                    if ($this->isAssociationExportable($assoMeta)) {
                        $pathMetadata = $this->metadataManager->get($assoMeta->getTarget());

                        if (\in_array($assoMeta->getType(), ['one-to-many', 'many-to-many'], true)) {
                            $propertyPathPrefix = rtrim($propertyPathPrefix, '.').'[i].';
                        }

                        if ($i + 1 === \count($fieldPaths)) {
                            if ($pathMetadata->hasFieldByName($pathMetadata->getFieldLabel())) {
                                $fieldMeta = $pathMetadata->getFieldByName($pathMetadata->getFieldLabel());

                                if (!$this->isFieldExportable($fieldMeta)) {
                                    $fieldMeta = $pathMetadata->getField($pathMetadata->getFieldIdentifier());
                                }
                            } else {
                                $fieldMeta = $pathMetadata->getField($pathMetadata->getFieldIdentifier());
                            }

                            $validFields[] = new ExportedColumn(
                                $labelPrefix.$this->getMetadataLabel($fieldMeta),
                                $propertyPathPrefix.$fieldMeta->getName()
                            );
                        }
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
        }

        if (empty($validFields)) {
            $idFieldMeta = $metadata->getField($metadata->getFieldIdentifier());
            $validFields[] = new ExportedColumn(
                $this->getMetadataLabel($idFieldMeta),
                $idFieldMeta->getField()
            );
        }

        return $validFields;
    }

    private function getMetadataLabel(MetadataInterface $metadata): string
    {
        return MetadataUtil::getTrans(
            $this->translator,
            $metadata->getLabel(),
            $metadata->getTranslationDomain(),
            $metadata->getName()
        );
    }

    /**
     * Check if the object is exportable.
     *
     * @param ObjectMetadataInterface $metadata The object metadata
     */
    private function isObjectExportable(ObjectMetadataInterface $metadata): bool
    {
        return $metadata->isPublic()
            && $this->authChecker->isGranted(new PermVote('view'), $metadata->getClass());
    }

    /**
     * Check if the field is exportable.
     *
     * @param FieldMetadataInterface $fieldMetadata The field metadata
     */
    private function isFieldExportable(FieldMetadataInterface $fieldMetadata): bool
    {
        return $fieldMetadata->isPublic()
            && $this->authChecker->isGranted(new PermVote('read'), new FieldVote($fieldMetadata->getParent()->getClass(), $fieldMetadata->getField()));
    }

    /**
     * Check if the association is exportable.
     *
     * @param AssociationMetadataInterface $associationMetadata The association metadata
     */
    private function isAssociationExportable(AssociationMetadataInterface $associationMetadata): bool
    {
        $targetMeta = $this->metadataManager->get($associationMetadata->getTarget());

        return $associationMetadata->isPublic()
            && $targetMeta->isPublic()
            && $this->authChecker->isGranted(new PermVote('read'), new FieldVote($associationMetadata->getParent()->getClass(), $associationMetadata->getAssociation()))
            && $this->authChecker->isGranted(new PermVote('view'), $targetMeta->getClass())
        ;
    }
}
