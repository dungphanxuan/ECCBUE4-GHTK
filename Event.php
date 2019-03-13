<?php

namespace Plugin\ghtk;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Eccube\Event\EccubeEvents;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Order;
use Eccube\Entity\Master\Pref;
use Eccube\Event\TemplateEvent;
use GuzzleHttp\Client;
use Eccube\Entity\BaseInfo;
use Eccube\Event\EventArgs;
use Eccube\Repository\BaseInfoRepository;
use Plugin\ghtk\Service\PurchaseFlow\GHTKPreprocessor;
use Plugin\ghtk\Repository\ConfigRepository;
use Plugin\ghtk\Service\GhtkApi;
use Eccube\Repository\Master\PrefRepository;
use Doctrine\ORM\EntityManager;
use Eccube\Repository\Master\OrderStatusRepository;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class Event implements EventSubscriberInterface
{

    /**
    * @var ConfigRepository
    */
    protected $configRepo;

     /**
    * @var GhtkApi
    */
    protected $service;

    /**
    * @var BaseInfoRepository
    */
    protected $baseInfo;

    /**
    * @var PrefRepository
    */
    protected $prefRepo;

    /**
    * @var EccubeConfig
    */
    protected $eccubeConfig;

    /**
    * @var EntityManager
    */
    protected $entityManager;

    /**
    * @var OrderStatusRepository
    */
    protected $orderStatusRepo;

    /**
     * @var Session
     */
    protected $session;

    public function __construct(ConfigRepository $configRepo, 
        GhtkApi $service, 
        BaseInfoRepository $baseInfoRepository,
        PrefRepository $prefRepo,
        EccubeConfig $eccubeConfig,
        EntityManager $entityManager,
        OrderStatusRepository $orderStatusRepo,
        Session $session)
    {
        $this->configRepo = $configRepo;
        $this->service = $service;
        $this->baseInfo = $baseInfoRepository->get();
        $this->prefRepo = $prefRepo;
        $this->eccubeConfig = $eccubeConfig;
        $this->entityManager = $entityManager;
        $this->orderStatusRepo = $orderStatusRepo;
        $this->session = $session;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
        	'@admin/Setting/Shop/delivery_edit.twig' => 'deliveryEdit',
            '@admin/Order/edit.twig' => 'downloadGhtkOrder',
            '@admin/Product/product.twig' => 'productEditView',
            '@admin/Product/product.twig' => 'productEditView',
            'Shopping/index.twig' => 'shoppingEdit',
            EccubeEvents::ADMIN_PRODUCT_EDIT_INITIALIZE =>  'productEditEvent', 
            EccubeEvents::FRONT_SHOPPING_COMPLETE_INITIALIZE => 'createGhtkOrder',
            EccubeEvents::ADMIN_ORDER_EDIT_INDEX_PROGRESS => 'orderEdit',
        ];
    }

    public function productEditEvent(EventArgs $event)
    {   
        $arguments = $event->getArguments();
        $arguments['builder']->add('weight', TextType::class, [
            'constraints' => [
                new NotBlank(),
            ],
        ]);
    }
    public function productEditView(TemplateEvent $event)
    {
        $event->addSnippet('@ghtk/admin/product_edit.twig');
    }

    public function deliveryEdit(TemplateEvent $event)
    {
        $config = $this->configRepo->get();
        $parameters = $event->getParameters();
        $deliveryId = $parameters['delivery_id'];
        if ( $deliveryId == $config->getDeliveryId() ) {
            $event->addSnippet('@ghtk/admin/delivery_edit.twig');
        }
    }

    public function shoppingEdit(TemplateEvent $event)
    {
        $parameters = $event->getParameters();
        $order = $parameters['Order'];
        $shippings = $order->getShippings();
        $shopname = $this->baseInfo->getShopName();
        $isExistGHTK = false ;
        foreach ( $shippings as  $shipping ) {
            if($shipping->getDelivery()->getId() == 3){
                $isExistGHTK = true ;
            }
        }
        if((empty($this->baseInfo->getPref()) || empty($this->baseInfo->getAddr01()) ) && ($isExistGHTK == true) ){
            $event->setParameter('message', 'Không thể tính phí giao hàng. Xin vui lòng liên hệ' . $shopname);
            $event->addSnippet('@ghtk/shopping/shopping_edit.twig' );
        }
    }   

    public function createGhtkOrder(EventArgs $event)
    {   
        $config = $this->configRepo->get();
        $arguments = $event->getArguments();
        $order = $arguments['Order'];
        $shippings = $order->getShippings();
        $childShippingIDs = [];
        foreach ( $shippings as $index => $shipping ) {
            if ( $shipping->getDelivery()->getId() != $config->getDeliveryId() ) {
                continue;
            }
            $data = [
                'products' => [],
                'order' => []
            ];
            foreach ($shipping->getOrderItems() as $orderItem) {
                if ( $orderItem->isProduct() )
                {
                    $product['name'] = $orderItem->getProductName();
                    $product['weight'] = $orderItem->getProduct()->getWeight();
                    $product['quantity'] = $orderItem->getQuantity();
                    $data['products'][] = $product;
                }

            }
            if($index == count($shippings) - 1){
                $order_data['is_freeship'] = 1;
                $order_data['pick_money'] = $order->getPaymentTotal();
                $order_data['pick_option'] = 'cod';
                if (count($childShippingIDs) >0) {
                    $order_data['note'] = 'Giao cùng: ' . implode(',', $childShippingIDs);
                }
            }else{
                $order_data['is_freeship'] = 1;
                $order_data['pick_money'] = 0;
                $order_data['pick_option'] = 'post'	;               
            }
            $order_data['id'] = $shipping->getId();
            $order_data['pick_name'] = $this->baseInfo->getShopName();
            $order_data['pick_address'] = $this->baseInfo->getAddr02();
            $order_data['pick_province'] = $this->getProvince($this->baseInfo->getPref());
            $order_data['pick_district'] = $this->baseInfo->getAddr01();
            $order_data['pick_tel'] = $this->baseInfo->getPhoneNumber();
            $order_data['name'] =  $order->getName02() . ' ' . $order->getName01();
            $order_data['address'] = $shipping->getAddr02();
            $order_data['province'] = $this->getProvince($shipping->getPref());
            $order_data['district'] = $shipping->getAddr01();
            $order_data['tel'] = $shipping->getPhoneNumber();
            $order_data['email'] = $order->getEmail();
            $order_data['weight_option'] = 'gram';
            $data['order'] = $order_data;
            $serviceCreateOrder = $this->service->createShipment($data);
            if (empty($serviceCreateOrder->order)) {
                $this->session->getFlashBag()->add('eccube.front.error', 'Có lỗi xảy ra khi tạo đơn hàng với GHTK. Xin vui lòng thử lại sau');
                return;
            }
            $created_order = $serviceCreateOrder->order;
            $shipping->setTrackingNumber($created_order->label);
            if($index != count($shippings)-1){
                array_push($childShippingIDs,$created_order->label);
            }
            $this->entityManager->flush($shipping);
        }
        $event->setArguments($arguments);
    }
    public function downloadGhtkOrder(TemplateEvent $event)
    {
        $config = $this->configRepo->get();
        $parameters = $event->getParameters();
        $order = $parameters['Order'];
        if ($order->getId()) {
            $readOnly = false;
            foreach($order->getShippings() as $shipping) {
                if ($shipping->getDelivery()->getId() == $config->getDeliveryId()) {
                    $ghtkStatus = $this->service->shipmentStatus($shipping->getTrackingNumber());
                    if ( $ghtkStatus->success ) {
                        $shipping->setGhtkStatus($ghtkStatus->order->status_text);
                        $event->setParameters($parameters);
                    }
                    $event->addSnippet('@ghtk/admin/order_edit.twig');
                }
            }
        }
    }

    public function getProvince(Pref $pref)
    {
        $pref = $this->prefRepo->findOneBy(['id' => $pref->getId()]);
        return $pref->getName();
    }

    public function orderEdit(EventArgs $event)
    {
        $args = $event->getArguments();
        $target_order = $args['TargetOrder'];
        $origin_order = $args['OriginOrder'];
        $shippings = $target_order->getShippings();
        $config = $this->configRepo->get();
        foreach ( $shippings as $shipping )
        {
            $delivery = $shipping->getDelivery();
            if ( $delivery->getId() == $config->getDeliveryId() ) {
                $order_status = $target_order->getOrderStatus();
                // Status 'Da Huy' = 3
                if ( $order_status->getId() == '3' ) {
                    $serviceCancel = $this->service->shipmentCancel($shipping->getTrackingNumber());
                    if ( $serviceCancel->success ) {
                        $this->session->getFlashBag()->add('eccube.admin.success', 'Huỷ đơn hàng thành công');
                    }else{
                        $this->session->getFlashBag()->add('eccube.admin.error', $serviceCancel->message);
                    }
                } 
            }
        }
    }
}