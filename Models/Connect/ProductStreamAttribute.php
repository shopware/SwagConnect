<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\CustomModels\Connect;

use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;

/**
 * Connect specific attributes for shopware Connect product streams
 *
 * @ORM\Table(name="s_plugin_connect_streams")
 * @ORM\Entity(repositoryClass="ProductStreamAttributeRepository")
 */
class ProductStreamAttribute extends ModelEntity
{
    /** marks streams for deletion */
    const STREAM_RELATION_DELETED = 1;
    const STREAM_RELATION_ACTIVE = 0;

    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(name="id", type="integer", nullable=false)
     */
    protected $id;

    /**
     * @var int
     *
     * @ORM\Column(name="stream_id", type="integer", nullable=false)
     */
    protected $streamId;

    /**
     * @var string
     *
     * @ORM\Column(name="export_status", type="string", length=255, nullable=true)
     */
    protected $exportStatus;

    /**
     * @var string
     *
     * @ORM\Column(name="export_message", type="text", nullable=true)
     */
    protected $exportMessage;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getStreamId()
    {
        return $this->streamId;
    }

    /**
     * @param int $streamId
     */
    public function setStreamId($streamId)
    {
        $this->streamId = $streamId;
    }

    /**
     * @return string
     */
    public function getExportStatus()
    {
        return $this->exportStatus;
    }

    /**
     * @param string $exportStatus
     */
    public function setExportStatus($exportStatus)
    {
        $this->exportStatus = $exportStatus;
    }

    /**
     * @return string
     */
    public function getExportMessage()
    {
        return $this->exportMessage;
    }

    /**
     * @param string $exportMessage
     */
    public function setExportMessage($exportMessage)
    {
        $this->exportMessage = $exportMessage;
    }
}
