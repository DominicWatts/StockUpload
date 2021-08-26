<?php

namespace Xigen\StockUpload\Controller\Adminhtml\Import;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Xigen\StockUpload\Model\Import\Stock;

/**
 * Ajax controller
 */
class Ajax extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Xigen\StockUpload\Helper\Import
     */
    protected $importHelper;

    /**
     * @var \Xigen\CsvUpload\Helper\Import
     */
    protected $csvImportHelper;

    /**
     * @var Xigen\StockUpload\Model\Import\Stock
     */
    protected $stock;

    /**
     * Ajax constructor.
     * @param Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Xigen\StockUpload\Helper\Import $importHelper
     * @param \Xigen\CsvUpload\Helper\Import $csvImportHelper
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Psr\Log\LoggerInterface $logger,
        \Xigen\StockUpload\Helper\Import $importHelper,
        \Xigen\CsvUpload\Helper\Import $csvImportHelper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->importHelper = $importHelper;
        $this->csvImportHelper = $csvImportHelper;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $type = $this->getRequest()->getParam('type');
        try {
            if ($type == 'replace' || $type == 'append') {
                return $this->processStock($type);
            }
            return $this->returnJson('finish', __('Invalid request'), 0);
        } catch (\Exception $e) {
            return $this->returnJson('finish', __('%1 - please check import data', $e->getMessage()), 0);
        }
    }

    /**
     * Import tier pricing process
     * @param string $type
     * @return \Magento\Framework\Controller\Result\JsonFactory
     */
    public function processStock($type)
    {
        $imports = $this->csvImportHelper->getImports();

        $processArray = [];
        foreach ($imports as $import) {
            $priceEntry = $this->csvImportHelper->parseImport($import);
            $processArray[$priceEntry['sku']][] = $priceEntry;
        }

        foreach ($processArray as $sku => $stocks) {
            $importData = [];
            try {
                foreach ($stocks as $stock) {
                    if (!isset($stock['sku'])) {
                        throw new LocalizedException(__('Problem with data'));
                    }
                    $product = $this->importHelper->get($stock['sku']);
                    if (!$product) {
                        $this->csvImportHelper->deleteImportBySku($sku);
                        continue;
                    }
                    $importData = $stocks;
                }

                if ($importData) {
                    $this->stock = $this->_objectManager->create(Stock::class);
                    $this->stock->saveStocks($importData, $type);
                }

                $this->csvImportHelper->deleteImportBySku($sku);

                $collection = $this->csvImportHelper->getImports();
                $collectionSize = $collection->getSize();
                if ($collection->getSize() > 0) {
                    return $this->returnJson('continue', __(
                        '%1 more %2 price(s) to process',
                        $collectionSize,
                        $type
                    ), $collectionSize);
                }
            } catch (\Exception $e) {
                return $this->returnJson('finish', __('%1 - please check import data', $e->getMessage()), 0);
            }
        }
        return $this->returnJson('finish', 'Process complete', 0);
    }

    /**
     * Return Json response
     * @param string $action
     * @param string $message
     * @param int $process
     * @return \Magento\Framework\Controller\Result\JsonFactory
     */
    public function returnJson($action, $message, $process)
    {
        $result = $this->resultJsonFactory->create();
        $data = [
            'action' => $action,
            'message' => $message,
            'process' => $process,
        ];
        return $result->setData($data);
    }
}
