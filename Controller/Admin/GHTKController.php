<?php

namespace Plugin\ghtk\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\ghtk\Service\GhtkApi as DeliveryService;
use Plugin\ghtk\Form\Type\Admin\ConfigType;
use Plugin\ghtk\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Eccube\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Master\OrderStatus;

class GHTKController extends AbstractController
{   
    protected $delivery;
    protected $config;
    protected $orderRepository;
    protected $entityManager;

    public function __construct(DeliveryService $delivery, ConfigRepository $configRepository, EntityManagerInterface $entityManager)
    {
        $this->delivery = $delivery;
        $this->config = $configRepository->get();
        $this->entityManager = $entityManager;
        $this->orderRepository = $this->entityManager->getRepository('Eccube\Entity\Order'); 
    }

    /**
     * @Route("/%eccube_admin_route%/ghtk/download_pdf", name="ghtk_admin_download_pdf")
     */
    public function index(Request $request)
    {
        $trackingNumber = $request->get('tracking_number');
        $trackingNumber = str_replace('-', '.', $trackingNumber);
        $filename = $trackingNumber . '.pdf';
        $content = $this->delivery->getInvoicePdf($trackingNumber);
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment ; filename=' . $filename);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));    
        echo $content;
    }

    /**
     * Callback webhook will be called from GHTK
     * 
     * @Route("/ghtk/webhook", name="ghtk_admin_webhook")
     * 
     */
    public function callback(Request $request)
    {
        if ($request->isMethod('post')) {
            if ($request->get('hash') == $this->config->getWebhookHash()) {
                $orderId = $request->get('partner_id');
                $ghtkStatus = $request->get('status_id');
                $order = $this->orderRepository->find($orderId);
                $order->setGhtkStatus($ghtkStatus);
                $status = null;
                switch($ghtkStatus) {
                    case 3: case 4:
                        $status = $this->entityManager->getRepository('Eccube\Entity\Master\OrderStatus')->find(OrderStatus::IN_PROGRESS); // Processing
                        break;
                    case 5:
                        $status = $this->entityManager->getRepository('Eccube\Entity\Master\OrderStatus')->find(OrderStatus::DELIVERED); // Deliveried
                        break;
                    default:
                        break;
                }
                if ($status) {
                    $order->setOrderStatus($status);
                }
                $this->entityManager->flush();
                return $this->json(['message' => 'Hooked at: ' . date('Y-m-d H:i:s')]);
            }
        }
        throw new \Exception('Something went wrong!');
    }
}
