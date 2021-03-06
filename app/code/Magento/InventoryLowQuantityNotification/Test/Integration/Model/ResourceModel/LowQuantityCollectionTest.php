<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotification\Test\Integration\Model\ResourceModel;

use Magento\InventoryLowQuantityNotification\Model\ResourceModel\LowQuantityCollection;
use Magento\InventoryLowQuantityNotificationApi\Api\Data\SourceItemConfigurationInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class LowQuantityCollectionTest extends TestCase
{
    /**
     * @var LowQuantityCollection
     */
    private $lowQuantityCollection;

    protected function setUp()
    {
        $this->lowQuantityCollection = Bootstrap::getObjectManager()->create(LowQuantityCollection::class);
    }

    /**
     * Tests that products from disabled sources are not present.
     * Each source code is used exclusively in one source item, so we check only source codes.
     *
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/products.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stocks.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/source_items.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stock_source_links.php
     * @codingStandardsIgnoreLine
     * @magentoDataFixture ../../../../app/code/Magento/InventoryLowQuantityNotificationApi/Test/_files/source_item_configuration.php
     */
    public function testLowQuantityCollection()
    {
        $expectedSourceCodes = [
            'eu-1',
            'eu-2'
        ];
        $actualSourceCodes = $this->lowQuantityCollection->getColumnValues(
            SourceItemConfigurationInterface::SOURCE_CODE
        );

        $this->assertEquals($expectedSourceCodes, $actualSourceCodes);
    }
}
