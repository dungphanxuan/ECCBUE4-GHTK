<?php 

namespace Plugin\ghtk\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation as Eccube;

/**
 * Trait GhtkProductTrait
 * @package Plugin\ghtk\Entity
 *
 * @Eccube\EntityExtension("Eccube\Entity\Product")
 */
trait GhtkProductTrait
{
    /**
     * @var string
     *
     * @ORM\Column(name="weight", type="integer", options={"default":"0"}, nullable=true)
     */
    private $weight = "";

    /**
     * @return string
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param string $weight
     *
     * @return self
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }
}