<?php

namespace Xigen\StockUpload\Model\Import\Stock\Validator;

use Magento\Framework\Validation\ValidationResult;

/**
 * Extension point for row validation (Service Provider Interface - SPI)
 *
 * @api
 */
interface ValidatorInterface
{
    const ERROR_INVALID_QTY = 'invalidQty';
    /**
     * @param array $rowData
     * @param int $rowNumber
     * @return ValidationResult
     */
    public function validate(array $rowData, int $rowNumber);
}
