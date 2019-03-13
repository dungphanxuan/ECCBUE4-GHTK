<?php

namespace Plugin\ghtk\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="plg_ghtk_config")
 * @ORM\Entity(repositoryClass="Plugin\ghtk\Repository\ConfigRepository")
 */
class Config
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string|null
     *
     * @ORM\Column(name="token", type="string", length=255, nullable=true)
    */
    private $token;

    /**
     * @var string|null
     *
     * @ORM\Column(name="is_sandbox", type="boolean", length=255, nullable=true)
    */
    private $is_sandbox;

    /**
     * @var string
     *
     * @ORM\Column(name="delivery_id", type="string", length=255)
    */
    private $delivery_id;

    /**
     * @var string
     *
     * @ORM\Column(name="webhook_hash", type="string", length=255)
    */
    private $webhook_hash;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string|null $token
     *
     * @return self
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getIsSandbox()
    {
        return $this->is_sandbox;
    }

    /**
     * @param string|null $is_sandbox
     *
     * @return self
     */
    public function setIsSandbox($is_sandbox)
    {
        $this->is_sandbox = $is_sandbox;

        return $this;
    }

    /**
     * @return string
     */
    public function getDeliveryId()
    {
        return $this->delivery_id;
    }

    /**
     * @param string $delivery_id
     *
     * @return self
     */
    public function setDeliveryId($delivery_id)
    {
        $this->delivery_id = $delivery_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getWebhookHash()
    {
        return $this->webhook_hash;
    }

    /**
     * @param string $webhook_hash
     *
     * @return self
     */
    public function setWebhookHash($webhook_hash)
    {
        $this->webhook_hash = $webhook_hash;

        return $this;
    }
}
