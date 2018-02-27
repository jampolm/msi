<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Save;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Inventory\Model\IsSourceItemsManagementAllowedForProductTypeInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryCatalog\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalog\Model\IsSingleSourceModeInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

/**
 * Save source product relations during product persistence via controller
 *
 * This needs to be handled in dedicated observer, because there is no pre-defined way of making several API calls for
 * Form submission handling
 */
class ProcessSourceItemsObserver implements ObserverInterface
{
    /**
     * @var IsSourceItemsManagementAllowedForProductTypeInterface
     */
    private $isSourceItemsManagementAllowedForProductType;

    /**
     * @var SourceItemsProcessor
     */
    private $sourceItemsProcessor;

    /**
     * @var IsSingleSourceModeInterface
     */
    private $isSingleSourceMode;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @param IsSourceItemsManagementAllowedForProductTypeInterface $isSourceItemsManagementAllowedForProductType
     * @param SourceItemsProcessor $sourceItemsProcessor
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SourceItemRepositoryInterface $sourceItemRepository
     */
    public function __construct(
        IsSourceItemsManagementAllowedForProductTypeInterface $isSourceItemsManagementAllowedForProductType,
        SourceItemsProcessor $sourceItemsProcessor,
        IsSingleSourceModeInterface $isSingleSourceMode,
        DefaultSourceProviderInterface $defaultSourceProvider,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SourceItemRepositoryInterface $sourceItemRepository
    ) {
        $this->isSourceItemsManagementAllowedForProductType = $isSourceItemsManagementAllowedForProductType;
        $this->sourceItemsProcessor = $sourceItemsProcessor;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sourceItemRepository = $sourceItemRepository;
    }

    /**
     * Process source items during product saving via controller
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @var ProductInterface $product */
        $product = $observer->getEvent()->getProduct();
        if ($this->isSourceItemsManagementAllowedForProductType->execute($product->getTypeId()) === false) {
            return;
        }
        /** @var Save $controller */
        $controller = $observer->getEvent()->getController();
        $productData = $controller->getRequest()->getParam('product', []);
        $singleSourceData = $productData['quantity_and_stock_status'] ?? [];

        if (!$this->isSingleSourceMode->execute()) {
            $sources = $controller->getRequest()->getParam('sources', []);
            $assignedSources = $sources['assigned_sources'] ?? [];
            $this->sourceItemsProcessor->process($productData['sku'], $assignedSources);
        } elseif (!empty($singleSourceData)) {
            /** @var StockItemInterface $stockItem */
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            $qty = $singleSourceData['qty'] ?? (empty($stockItem) ? 0 : $stockItem->getQty());
            $isInStock = $singleSourceData['is_in_stock'] ?? (empty($stockItem) ? 1 : (int)$stockItem->getIsInStock());
            $defaultSourceData = [
                SourceItemInterface::SKU => $productData['sku'],
                SourceItemInterface::SOURCE_CODE => $this->defaultSourceProvider->getCode(),
                SourceItemInterface::QUANTITY => $qty,
                SourceItemInterface::STATUS => $isInStock
            ];
            $sourceItems = $this->getSourceItemsWithoutDefault($productData['sku']);
            $sourceItems[] = $defaultSourceData;
            $this->sourceItemsProcessor->process($productData['sku'], $sourceItems);
        }
    }

    /**
     * Get Source Items Data without Default Source by SKU
     *
     * @param string $sku
     * @return array
     */
    private function getSourceItemsWithoutDefault(string $sku): array
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $sku)
            ->addFilter(SourceItemInterface::SOURCE_CODE, $this->defaultSourceProvider->getCode(), 'neq')
            ->create();
        $sourceItems = $this->sourceItemRepository->getList($searchCriteria)->getItems();

        $sourceItemData = [];
        if ($sourceItems) {
            foreach ($sourceItems as $sourceItem) {
                $sourceItemData[] = [
                    SourceItemInterface::SKU => $sourceItem->getSku(),
                    SourceItemInterface::SOURCE_CODE => $sourceItem->getSourceCode(),
                    SourceItemInterface::QUANTITY => $sourceItem->getQuantity(),
                    SourceItemInterface::STATUS => $sourceItem->getStatus()
                ];
            }
        }
        return $sourceItemData;
    }
}
