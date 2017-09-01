<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Tests\Unit\Subscribers;

use ShopwarePlugins\Connect\Subscribers\Search;
use ShopwarePlugins\Connect\Tests\AbstractConnectUnitTest;
use Enlight\Event\SubscriberInterface;
use Shopware\Components\Model\ModelManager;

class SearchTest extends AbstractConnectUnitTest
{
    public function test_it_can_be_created()
    {
        $subscriber = new Search($this->createMock(ModelManager::class));

        $this->assertInstanceOf(SubscriberInterface::class, $subscriber);
        $this->assertInstanceOf(Search::class, $subscriber);
    }

    public function test_subscribed_events()
    {
        $this->assertSame(
            [
                'Enlight_Controller_Action_PostDispatch_Backend_Search' => 'extendBackendPropertySearch',
            ],
            Search::getSubscribedEvents()
        );
    }
}
