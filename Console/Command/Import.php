<?php

namespace Xigen\StockUpload\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\ImportExport\Model\Import as MagentoImport;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xigen\CsvUpload\Helper\Import as CsvHelper;
use Xigen\StockUpload\Helper\Import as ImportHelper;
use Xigen\StockUpload\Model\Import\Stock;

/**
 * Import console
 */
class Import extends Command
{
    const IMPORT_ARGUMENT = 'import';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;

    /**
     * @var \Xigen\StockUpload\Helper\Import
     */
    private $importHelper;

    /**
     * @var \Xigen\CsvUpload\Helper\Import
     */
    private $csvImportHelper;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var \Magento\Framework\App\ObjectManager
     */
    private $_objectManager;

    /**
     * @var Xigen\StockUpload\Model\Import\Stock
     */
    private $tier;

    /**
     * @var Xigen\StockUpload\Model\Import\Stock
     */
    protected $stock;

    /**
     * Import constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Xigen\StockUpload\Helper\Import $importHelper
     * @param \Xigen\CsvUpload\Helper\Import $csvImportHelper
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ImportHelper $importHelper,
        CsvHelper $csvImportHelper
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->importHelper = $importHelper;
        $this->csvImportHelper = $csvImportHelper;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->state->setAreaCode(Area::AREA_GLOBAL);

        $import = $input->getArgument(self::IMPORT_ARGUMENT) ?: false;

        $importData = [];
        if ($import) {
            $this->_objectManager = ObjectManager::getInstance();

            $this->output->writeln((string) __(
                '[%1] Start',
                $this->dateTime->gmtDate()
            ));

            $imports = $this->csvImportHelper->getImports();
            $progress = new ProgressBar($this->output, count($imports));
            $progress->start();

            $processArray = [];
            foreach ($imports as $import) {
                $priceEntry = $this->csvImportHelper->parseImport($import);
                $processArray[$priceEntry['sku']][] = $priceEntry;
            }

            foreach ($processArray as $sku => $stocks) {
                $importData = [];

                foreach ($stocks as $stock) {
                    if (!isset($stock['sku'])) {
                        throw new LocalizedException(__('Problem with data'));
                    }

                    $product = $this->importHelper->get($stock['sku']);
                    if (!$product) {
                        $this->output->writeln((string) __(
                            '[%1] Sku not found : %2',
                            $this->dateTime->gmtDate(),
                            $stock['sku']
                        ));
                        $this->csvImportHelper->deleteImportBySku($sku);
                        continue;
                    }

                    $importData = $stocks;

                    $progress->advance();
                }

                if ($importData) {
                    $this->stock = $this->_objectManager->create(Stock::class);
                    $this->stock->saveStocks($importData, MagentoImport::BEHAVIOR_REPLACE);
                    $this->output->writeln((string) __('[%1] Sku processed : %2', $this->dateTime->gmtDate(), $sku));
                    $this->csvImportHelper->deleteImportBySku($sku);
                }
            }

            $progress->finish();

            $this->output->writeln('');
            $this->output->writeln((string) __(
                '[%1] Finish',
                $this->dateTime->gmtDate()
            ));

            return Cli::RETURN_SUCCESS;
        }
        return Cli::RETURN_FAILURE;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("xigen:stock:import");
        $this->setDescription("Process to import stock");
        $this->setDefinition([
            new InputArgument(self::IMPORT_ARGUMENT, InputArgument::REQUIRED, 'Import'),
        ]);
        parent::configure();
    }
}
