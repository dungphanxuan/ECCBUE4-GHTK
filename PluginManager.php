<?php 

namespace Plugin\ghtk;

use Eccube\Plugin\AbstractPluginManager;
use Plugin\ghtk\Entity\Config;
use Plugin\ghtk\Entity\GhtkOrderStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Eccube\Repository\DeliveryRepository;
use Eccube\Repository\DeliveryFeeRepository;
use Eccube\Repository\PaymentRepository;
use Eccube\Repository\PaymentOptionRepository;
use Plugin\ghtk\Repository\ConfigRepository;
use Eccube\Repository\Master\SaleTypeRepository;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Entity\Delivery;
use Eccube\Entity\PaymentOption;
use Eccube\Entity\DeliveryFee;

class PluginManager extends AbstractPluginManager 
{
	public function enable(array $meta = null, ContainerInterface $container)
	{
		$this->createShipmentMethod($container);
	}

	public function disable(array $meta = null, ContainerInterface $container)
	{
		$this->removeShipmentMethod($container);
	}


	public function removeShipmentMethod(ContainerInterface $container) 
    {
		$entityManager = $container->get('doctrine')->getManager();
		$configRepo = $container->get(ConfigRepository::class);
		$config = $configRepo->findOneBy([]);
		$deliveryRepo = $container->get(DeliveryRepository::class);
		$delivery = $deliveryRepo->findOneBy(
			[	
				'id' => $config->getDeliveryId()
			]
		);
        $delivery->setVisible(false);
		$entityManager->persist($delivery);
        $entityManager->flush();
	}

	public function createShipmentMethod(ContainerInterface $container)
	{
		$entityManager = $container->get('doctrine')->getManager();
        $configRepo = $container->get(ConfigRepository::class);
        $saleTypeRepository = $container->get(SaleTypeRepository::class);
        $saleType = $saleTypeRepository->findOneBy([], ['sort_no' => 'ASC']);
        $deliveryRepo = $container->get(DeliveryRepository::class);
        $config = $configRepo->get();
        $delivery = null;
        if ($config) {
            $delivery = $deliveryRepo->findOneBy(
                [   
                    'id' => $config->getDeliveryId()
                ]
            );
        }
        if (empty($config) || empty($delivery)) {
            $delivery = new Delivery();
            $delivery->setName('Giao Hàng Tiết Kiệm');
            $delivery->setServiceName('GHTK');
            $delivery->setSaleType($saleType);
        }
		$delivery->setVisible(true);
        $entityManager->persist($delivery);
        $entityManager->flush($delivery);

        $config = $configRepo->findOneBy([]);
        if (!$config) {
            $config = new Config();
            $config->setDeliveryId($delivery->getId());
            $config->setName('Giao Hàng Tiết Kiệm');
            $config->setWebhookHash(time());
            $entityManager->persist($config);
            $entityManager->flush($config);
        }

		$prefRepository = $container->get(PrefRepository::class);
		$prefs = $prefRepository->findAll();
		$deliveryFeeRepository = $container->get(DeliveryFeeRepository::class);
		foreach ($prefs as $pref) {
            $deliveryFee = $deliveryFeeRepository->findOneBy(
                    [
                        'Delivery' => $delivery,
                        'Pref' => $pref,
                    ]
                );
            if (!$deliveryFee) {
                $deliveryFee = new DeliveryFee();
                $deliveryFee
                    ->setPref($pref)
                    ->setDelivery($delivery)
                    ->setFee(0);
            }
            if (!$deliveryFee->getFee()) {
                $delivery->addDeliveryFee($deliveryFee);
            }
        }
        $DeliveryFees = $delivery->getDeliveryFees();
        $DeliveryFeesIndex = [];
        foreach ($DeliveryFees as $DeliveryFee) {
            $delivery->removeDeliveryFee($DeliveryFee);
            $DeliveryFeesIndex[$DeliveryFee->getPref()->getId()] = $DeliveryFee;
        }
        ksort($DeliveryFeesIndex);
        foreach ($DeliveryFeesIndex as $timeId => $DeliveryFee) {
            $delivery->addDeliveryFee($DeliveryFee);
        }
        $paymentRepository = $prefRepository = $container->get(PaymentRepository::class);
        $payments = $paymentRepository->findAll();
        $poRepository = $container->get(PaymentOptionRepository::class);
        foreach ( $payments as $payment ) {
            if ($poRepository->findOneBy(['delivery_id' => $delivery->getId(), 'payment_id' => $payment->getId()])) {
                continue;
            } 
            $paymentOption = new PaymentOption();
            $paymentOption->setDeliveryId($delivery->getId());
            $paymentOption->setPaymentId($payment->getId());
            $paymentOption->setDelivery($delivery);
            $paymentOption->setPayment($payment);
            $entityManager->persist($paymentOption);
            $entityManager->flush($paymentOption);
        } 
	}
}