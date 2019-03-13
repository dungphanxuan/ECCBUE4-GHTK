<?php 

namespace Plugin\ghtk\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation as Eccube;

/**
 * Trait GhtkShippingTrait
 * @package Plugin\ghtk\Entity
 *
 * @Eccube\EntityExtension("Eccube\Entity\Shipping")
 */
trait GhtkShippingTrait
{
    /**
     * @var string
     *
     * @ORM\Column(name="ghtk_status", type="string", options={"default":"NULL"}, nullable=true)
     */
    private $ghtk_status = "";

    /**
     * @return string
     */
    public function getGhtkStatus()
    {
        return $this->ghtk_status;
    }

    /**
     * @param string $ghtk_status
     *
     * @return self
     */
    public function setGhtkStatus($ghtk_status)
    {
        $this->ghtk_status = $ghtk_status;

        return $this;
    }
}