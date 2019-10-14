<?php

namespace Xigen\StockUpload\Model\Import\Stock\Validator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationResultFactory;
use Magento\InventoryImportExport\Model\Import\Sources;

/**
 * Extension point for row validation
 */
class QtyValidator implements ValidatorInterface
{
    /**
     * @var ValidationResultFactory
     */
    private $validationResultFactory;
    /**
     * @param ValidationResultFactory $validationResultFactory
     * @throws LocalizedException
     */
    public function __construct(ValidationResultFactory $validationResultFactory)
    {
        $this->validationResultFactory = $validationResultFactory;
    }
    /**
     * @inheritdoc
     */
    public function validate(array $rowData, int $rowNumber)
    {
        $errors = [];
        if (!isset($rowData[Sources::COL_QTY])) {
            $errors[] = __('Missing required column "%column"', ['column' => Sources::COL_QTY]);
        }
        return $this->validationResultFactory->create(['errors' => $errors]);
    }
}
