<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemId;
use Magento\InventoryIndexer\Indexer\Source\SourceIndexer;

/**
 * Reindex after source items delete plugin
 */
class ReindexAfterSourceItemsDeletePlugin
{
    /**
     * @var GetSourceItemId
     */
    private $getSourceItemId;

    /**
     * @var SourceIndexer
     */
    private $sourceIndexer;

    /**
     * @param GetSourceItemId $getSourceItemId
     * @param SourceIndexer $sourceIndexer
     */
    public function __construct(GetSourceItemId $getSourceItemId, SourceIndexer $sourceIndexer)
    {
        $this->getSourceItemId = $getSourceItemId;
        $this->sourceIndexer = $sourceIndexer;
    }

    /**
     * @param SourceItemsDeleteInterface $subject
     * @param callable $proceed
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        SourceItemsDeleteInterface $subject,
        callable $proceed,
        array $sourceItems
    ) {
        $sourceCodes = [];
        foreach ($sourceItems as $sourceItem) {
            $sourceCodes[] = $sourceItem->getSourceCode();
        }

        $proceed($sourceItems);

        if (count($sourceCodes)) {
            $this->sourceIndexer->executeList($sourceCodes);
        }
    }
}
