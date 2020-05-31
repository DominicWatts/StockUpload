<?php

namespace Xigen\StockUpload\Model\Import;

use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface as ValidatorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Xigen\StockUpload\Model\Import\Stock\Validator\ValidatorInterface as CustomInterface;

/**
 * Xigen StockUpload import model stock
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * phpcs:disable Generic.Files.LineLength
 */
class Stock extends \Magento\ImportExport\Model\Import\Entity\AbstractEntity
{
    const COL_SKU = 'sku';
    const COL_QTY = 'quantity';
    const VALIDATOR_MAIN = 'validator';
    const TABLE_STOCK = 'cataloginventory_stock_item';
    const VALIDATOR_QTY = 'validator_quantity';
    const VALIDATOR_SKU = 'validator_sku';
    const PRODUCT_ID = 'product_id';

    /**
     * Validation failure message template definitions.
     *
     * @var array
     */
    protected $_messageTemplates = [
        ValidatorInterface::ERROR_INVALID_WEBSITE => 'Invalid value in Website column (website does not exists?)',
        ValidatorInterface::ERROR_SKU_IS_EMPTY => 'SKU is empty',
        ValidatorInterface::ERROR_SKU_NOT_FOUND_FOR_DELETE => 'Product with specified SKU not found',
        CustomInterface::ERROR_INVALID_QTY => 'Stock data price or quantity value is invalid',
        ValidatorInterface::ERROR_TIER_DATA_INCOMPLETE => 'Stock data is incomplete',
        ValidatorInterface::ERROR_INVALID_ATTRIBUTE_DECIMAL => 'Value for \'%s\' attribute contains incorrect value, acceptable values are in decimal format',
    ];

    /**
     * If we should check column names
     *
     * @var bool
     */
    protected $needColumnCheck = true;

    /**
     * Valid column names.
     *
     * @array
     */
    protected $validColumnNames = [
        self::COL_SKU,
        self::COL_QTY,
    ];

    /**
     * Need to log in import history
     *
     * @var bool
     */
    protected $logInHistory = true;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory
     */
    protected $_resourceFactory;

    /**
     * @var \Magento\Catalog\Helper\Data
     */
    protected $_catalogData;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $_productModel;

    /**
     * @var \Magento\CatalogImportExport\Model\Import\Product\StoreResolver
     */
    protected $_storeResolver;

    /**
     * @var ImportProduct
     */
    protected $_importProduct;

    /**
     * @var array
     */
    protected $_validators = [];

    /**
     * @var array
     */
    protected $_cachedSkuToDelete;

    /**
     * @var array
     */
    protected $_oldSkus = null;

    /**
     * Permanent entity columns.
     *
     * @var string[]
     */
    protected $_permanentAttributes = [self::COL_SKU];

    /**
     * Catalog product entity
     *
     * @var string
     */
    protected $_catalogProductEntity;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * Product entity link field
     *
     * @var string
     */
    private $productEntityLinkField;

    /**
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\ImportExport\Helper\Data $importExportData
     * @param \Magento\ImportExport\Model\ResourceModel\Import\Data $importData
     * @param \Magento\Eav\Model\Config $config
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Helper\Data $catalogData
     * @param ImportProduct\StoreResolver $storeResolver
     * @param ImportProduct $importProduct
     * @param Stock\Validator $validator
     * @param Stock\Validator\QtyValidator $quantityValidator
     * @param Stock\Validator\SkuValidator $skuValidator
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Eav\Model\Config $config,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\Catalog\Model\Product $productModel,
        \Magento\Catalog\Helper\Data $catalogData,
        \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver,
        ImportProduct $importProduct,
        Stock\Validator $validator,
        Stock\Validator\QtyValidator $quantityValidator,
        Stock\Validator\SkuValidator $skuValidator
    ) {
        $this->dateTime = $dateTime;
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->_connection = $resource->getConnection('write');
        $this->_resourceFactory = $resourceFactory;
        $this->_productModel = $productModel;
        $this->_catalogData = $catalogData;
        $this->_storeResolver = $storeResolver;
        $this->_importProduct = $importProduct;
        $this->_validators[self::VALIDATOR_MAIN] = $validator->init($this);
        $this->_catalogProductEntity = $this->_resourceFactory->create()->getTable('catalog_product_entity');
        $this->_oldSkus = $this->retrieveOldSkus();
        $this->_validators[self::VALIDATOR_QTY] = $quantityValidator;
        $this->_validators[self::VALIDATOR_SKU] = $skuValidator;
        $this->errorAggregator = $errorAggregator;

        foreach (array_merge($this->errorMessageTemplates, $this->_messageTemplates) as $errorCode => $message) {
            $this->getErrorAggregator()->addErrorMessageTemplate($errorCode, $message);
        }
    }

    /**
     * Validator object getter.
     *
     * @param string $type
     * @return Stock\Validator|Stock\Validator\QtyValidator
     */
    protected function _getValidator($type)
    {
        return $this->_validators[$type];
    }

    /**
     * Create stock data from raw data.
     * @throws \Exception
     * @return bool Result of operation.
     */
    protected function _importData()
    {
        if (\Magento\ImportExport\Model\Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->deleteStock();
        } elseif (\Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE == $this->getBehavior()) {
            $this->saveProductStocks();
        } elseif (\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND == $this->getBehavior()) {
            $this->saveStocks();
        }

        return true;
    }

    /**
     * Entity type code getter.
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'cataloginventory_stock';
    }

    /**
     * Row validation.
     * @param array $rowData
     * @param int $rowNum
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum)
    {
        $sku = false;
        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }
        $this->_validatedRows[$rowNum] = true;
        // BEHAVIOR_DELETE use specific validation logic
        if (\Magento\ImportExport\Model\Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            if (!isset($rowData[self::COL_SKU])) {
                $this->addRowError(ValidatorInterface::ERROR_SKU_IS_EMPTY, $rowNum);
                return false;
            }
            return true;
        }
        if (!$this->_getValidator(self::VALIDATOR_MAIN)->isValid($rowData)) {
            foreach ($this->_getValidator(self::VALIDATOR_MAIN)->getMessages() as $message) {
                $this->addRowError($message, $rowNum);
            }
        }
        if (isset($rowData[self::COL_SKU])) {
            $sku = $rowData[self::COL_SKU];
        }
        if (false === $sku) {
            $this->addRowError(ValidatorInterface::ERROR_ROW_IS_ORPHAN, $rowNum);
        }
        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * Deletes stock data from raw data
     * @param array $importData
     * @return $this
     */
    public function deleteStock(array $importData = [])
    {
        $this->_cachedSkuToDelete = null;
        $listSku = [];
        foreach ($importData as $rowNum => $rowData) {
            $this->validateRow($rowData, $rowNum);
            if (!$this->getErrorAggregator()->isRowInvalid($rowNum)) {
                $rowSku = $rowData[self::COL_SKU];
                $listSku[] = $rowSku;
            }
            if ($this->getErrorAggregator()->hasToBeTerminated()) {
                $this->getErrorAggregator()->addRowToSkip($rowNum);
            }
        }

        if ($listSku) {
            $this->deleteStock(array_unique($listSku), self::TABLE_STOCK);
            $this->setUpdatedAt($listSku);
        }
        return $this;
    }

    /**
     * Deletes stock data from raw data
     * @param array $importData
     * @param string $behavior
     * @return void
     */
    public function saveStocks(array $importData = [], string $behavior = null)
    {
        $stocks = [];
        $listSku = [];

        if (!$behavior) {
            throw new LocalizedException(__('Behaviour mode not set'));
        }

        foreach ($importData as $rowNum => $rowData) {
            if (!$this->validateRow($rowData, $rowNum)) {
                $this->addRowError(ValidatorInterface::ERROR_SKU_IS_EMPTY, $rowNum);
                continue;
            }
            if ($this->getErrorAggregator()->hasToBeTerminated()) {
                $this->getErrorAggregator()->addRowToSkip($rowNum);
                continue;
            }

            $rowSku = $rowData[self::COL_SKU];
            $listSku[] = $rowSku;
            $stocks[$rowSku][] = [
                'qty' => $rowData[self::COL_QTY],
            ];
        }

        if ($behavior == \Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE) {
            if ($listSku) {
                $this->processCountNewStocks($stocks);
                $this->saveProductStocks($stocks, self::TABLE_STOCK);
                $this->setUpdatedAt($listSku);
            }
        }
        return $this;
    }

    /**
     * Save product stock.
     * @param array $priceData
     * @param string $table
     * @return $this
     */
    protected function saveProductStocks(array $priceData = [], string $table = null)
    {
        if ($priceData) {
            $tableName = $this->_resourceFactory->create()->getTable($table);
            $stockIn = [];
            $entityIds = [];
            $oldSkus = $this->retrieveOldSkus();
            foreach ($priceData as $sku => $priceRows) {
                if (isset($oldSkus[$sku])) {
                    $productId = $oldSkus[$sku];
                    foreach ($priceRows as $row) {
                        $row[self::PRODUCT_ID] = $productId;
                        if ($row) {
                            $this->_connection->update($tableName, $row, [self::PRODUCT_ID . ' = ?' => $productId]);
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Set updated_at for product
     * @param array $listSku
     * @return $this
     */
    protected function setUpdatedAt(array $listSku)
    {
        $updatedAt = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $this->_connection->update(
            $this->_catalogProductEntity,
            [\Magento\Catalog\Model\Category::KEY_UPDATED_AT => $updatedAt],
            $this->_connection->quoteInto('sku IN (?)', array_unique($listSku))
        );
        return $this;
    }

    /**
     * Retrieve product skus
     * @return array
     */
    protected function retrieveOldSkus()
    {
        if ($this->_oldSkus === null) {
            $this->_oldSkus = $this->_connection->fetchPairs(
                $this->_connection->select()->from(
                    $this->_catalogProductEntity,
                    ['sku', $this->getProductEntityLinkField()]
                )
            );
        }
        return $this->_oldSkus;
    }

    /**
     * Count existing prices
     * @param array $stocks
     * @param string $table
     * @return $this
     */
    protected function processCountExistingStocks($stocks, $table)
    {
        $oldSkus = $this->retrieveOldSkus();
        $existProductIds = array_intersect_key($oldSkus, $stocks);
        if (!count($existProductIds)) {
            return $this;
        }

        $tableName = $this->_resourceFactory->create()->getTable($table);
        $productEntityLinkField = $this->getProductEntityLinkField();
        $existingPrices = $this->_connection->fetchAssoc(
            $this->_connection->select()->from(
                $tableName,
                ['value_id', $productEntityLinkField]
            )->where($productEntityLinkField . ' IN (?)', $existProductIds)
        );
        foreach ($existingPrices as $existingPrice) {
            foreach ($stocks as $sku => $skuPrices) {
                if (isset($oldSkus[$sku]) && $existingPrice[$productEntityLinkField] == $oldSkus[$sku]) {
                    $this->incrementCounterUpdated($skuPrices, $existingPrice);
                }
            }
        }

        return $this;
    }

    /**
     * Increment counter of updated items
     * @param array $stocks
     * @param array $existingPrice
     * @return void
     */
    protected function incrementCounterUpdated($stocks, $existingPrice)
    {
        foreach ($stocks as $price) {
            $this->countItemsUpdated++;
        }
    }

    /**
     * Count new prices
     * @param array $stocks
     * @return $this
     */
    protected function processCountNewStocks(array $stocks)
    {
        foreach ($stocks as $productPrices) {
            $this->countItemsCreated += count($productPrices);
        }
        $this->countItemsCreated -= $this->countItemsUpdated;

        return $this;
    }

    /**
     * Get product entity link field
     * @return string
     * @throws \Exception
     */
    private function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }
}
