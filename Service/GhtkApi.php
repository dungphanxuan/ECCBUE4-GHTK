<?php 

namespace Plugin\ghtk\Service;

use GuzzleHttp\Client;
use Plugin\ghtk\Repository\ConfigRepository;

class GhtkApi {

	/**
	* @var $configRepo /
	*/
	protected $configRepo;

	/**
	* @var $config /
	*/
	protected $config;

	/**
	* GhtkApi Constructor
	* @param ConfigRepository $configRepo
	*/
	public function __construct(ConfigRepository $configRepo)
	{
		$this->config = $configRepo->get();
		$this->api_url = 'https://services.giaohangtietkiem.vn';
		if ( $this->config->getIsSandbox() )
		{
			$this->api_url = 'https://dev.ghtk.vn';
		}
		$this->client = new Client([
			 'headers' => [
                'Content-Type' => 'application/pdf',
                'Token' => $this->config->getToken(), 
            ]
		]);
    }
    /**
     * Estimate shipping fee
     *
     * @param [type] $pick_province
     * @param [type] $pick_district
     * @param [type] $province
     * @param [type] $district
     * @param [type] $address
     * @param [type] $weight
     * @return void
     */
    public function shipmentFee($pick_province, $pick_district, $province, $district, $address, $weight)
    {
        $response = $this->client->get($this->api_url . '/services/shipment/fee',  [
            'query' => [
                "pick_province" => $pick_province,
                "pick_district" => $pick_district,
                "province" => $province,
                "district" => $district,
                "address" => $address,
                "weight" => $weight
            ]
        ]);
        $result = json_decode($response->getBody()->getContents());
        return $result;
    }

    /*
    * S1.A1.17373471 : GHTK order id
    * eccube order id (optional) : /services/shipment/v2/partner_id:1234567
    */
    public function shipmentStatus($label)
    {
        $response = $this->client->get($this->api_url . '/services/shipment/v2/'. $label);
        $result = json_decode($response->getBody()->getContents());
        return $result;
    }

    /*
    * use ghtk order id : /services/shipment/cancel/S1.17373471
    * use eccube id : /services/shipment/cancel/partner_id:1234567
    */

    public function shipmentCancel($label)
    {
        $response = $this->client->post($this->api_url . '/services/shipment/cancel/' . $label);
        $result = json_decode($response->getBody()->getContents());
        return $result;
    }

    /**
     * [createShipment description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function createShipment($data)
    {
        $body = ['form_params' => $data];
        $response = $this->client->post($this->api_url . '/services/shipment/order/?ver=1.5', $body);
        $result = $response->getBody()->getContents();
        $d = json_decode($result);
        return $d;
    }

    /**
     * [getInvoicePdf description]
     * @param  [type] $trackingId [description]
     * @return [type]             [description]
     */
    public function getInvoicePdf($trackingId)
    {
        $response = $this->client->get($this->api_url . '/services/label/' . $trackingId);
        return $response->getBody()->getContents();
    }
}