<?php

namespace Packlink\Subscribers;

use Enlight\Event\SubscriberInterface;
use Enlight_Hook_HookArgs;
use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Order\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Order\OrderService;
use Packlink\Utilities\Reference;

class OrderListHandler implements SubscriberInterface
{
    /**
     * @var \Packlink\Services\BusinessLogic\ConfigurationService
     */
    protected $configService;
    /**
     * @var \Logeecom\Infrastructure\ORM\Interfaces\RepositoryInterface
     */
    protected $orderDetailsRepository;
    /**
     * @var \Packlink\BusinessLogic\Order\OrderService
     */
    protected $orderService;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Controllers_Backend_Order::getList::after' => 'extendOrderList',
        ];
    }

    /**
     * Extends order list with additional data.
     *
     * @param \Enlight_Hook_HookArgs $args
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function extendOrderList(Enlight_Hook_HookArgs $args)
    {
        if (!$this->isLoggedIn()) {
            return;
        }

        $userCountry = $this->getUserCountry();
        $return = $args->getReturn();

        foreach ($return['data'] as $index => $order) {
            if (($orderDetails = $this->getOrderDetails($order['id'])) !== null && $orderDetails->getReference()) {
                $return['data'][$index]['plReferenceUrl'] = Reference::getUrl(
                    $userCountry,
                    $orderDetails->getReference()
                );

                $return['data'][$index]['plIsDeleted'] = $orderDetails->isDeleted();

                $orderService = $this->getOrderService();
                $isLabelsAvailable = $orderService->isReadyToFetchShipmentLabels($orderDetails->getStatus());

                if ($isLabelsAvailable) {
                    $labels = $orderDetails->getShipmentLabels();

                    $return['data'][$index]['plHasLabel'] = true;
                    $return['data'][$index]['plIsLabelPrinted'] = !empty($labels) && $labels[0]->isPrinted();
                }
            }
        }

        $args->setReturn($return);
    }

    /**
     * Retrieves user country. Fallback is de.
     *
     * @return string
     */
    protected function getUserCountry()
    {
        $userAccount = $this->getConfigService()->getUserInfo();

        return strtolower($userAccount ? $userAccount->country : 'de');
    }

    /**
     * Checks whether user is logged in.
     *
     * @return bool
     */
    protected function isLoggedIn()
    {
        $authToken = $this->getConfigService()->getAuthorizationToken();

        return !empty($authToken);
    }

    /**
     * Retrieves config service.
     *
     * @return \Packlink\Services\BusinessLogic\ConfigurationService
     */
    protected function getConfigService()
    {
        if ($this->configService === null) {
            $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        }

        return $this->configService;
    }

    /**
     * Retrieves order details.
     *
     * @param $orderId
     *
     * @return \Packlink\BusinessLogic\Order\Models\OrderShipmentDetails
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function getOrderDetails($orderId)
    {
        $query = new QueryFilter();
        $query->where('orderId', Operators::EQUALS, $orderId);
        /** @var \Packlink\BusinessLogic\Order\Models\OrderShipmentDetails $details | null */
        $details = $this->getOrderDetailsRepository()->selectOne($query);

        return $details;
    }

    /**
     * Retrieves order details repository.
     *
     * @return \Logeecom\Infrastructure\ORM\Interfaces\RepositoryInterface
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function getOrderDetailsRepository()
    {
        if ($this->orderDetailsRepository === null) {
            $this->orderDetailsRepository = RepositoryRegistry::getRepository(OrderShipmentDetails::getClassName());
        }

        return $this->orderDetailsRepository;
    }

    /**
     * Retrieves order service.
     *
     * @return \Packlink\BusinessLogic\Order\OrderService
     */
    protected function getOrderService()
    {
        if ($this->orderService === null) {
            $this->orderService = ServiceRegister::getService(OrderService::CLASS_NAME);
        }

        return $this->orderService;
    }
}