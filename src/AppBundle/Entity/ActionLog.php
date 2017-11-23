<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ActionLog
 *
 * @ORM\Table(name="action_log")
 * @ORM\Entity(repositoryClass="AppBundle\Entity\Repository\ActionLogRepository")
 */
class ActionLog extends Log
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Код статуса шага процесса
     * @var int
     *
     * @ORM\Column(name="status", type="integer", nullable=true)
     */
    private $status;

    /**
     * @var Action
     *
     * @ORM\ManyToOne(targetEntity = "Action", cascade = {"persist", "remove"})
     * @ORM\JoinColumn(name="action_id", referencedColumnName = "id", onDelete = "SET NULL")
     */
    private $action;

    /**
     * @var Emergency
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Emergency")
     * @ORM\JoinColumn(name="emergency_id", referencedColumnName = "id", onDelete = "SET NULL")
     */
    private $emergency;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity = "User")
     * @ORM\JoinColumn(name = "edited_user_id", referencedColumnName = "id", onDelete = "SET NULL")
     */
    private $editedUser;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_time", type="datetime", nullable = true)
     */
    private $createdTime;

    public function __construct()
    {
        $this->createdTime = new \DateTime("now");
        {{ parent::__construct(); }}
    }

    /**
     * @return \DateTime
     */
    public function getCreatedTime()
    {
        return $this->createdTime;
    }

    /**
     * @return User
     */
    public function getEditedUser()
    {
        return $this->editedUser;
    }

    /**
     * @param User $user
     * @return ActionLog
     */
    public function setEditedUser($user)
    {
        $this->editedUser = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedTimeString()
    {
        return $this->createdTime ? $this->createdTime->format("d.m.Y H:i:s") : null;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getStatusString()
    {
        return isset(Action::$statuses[$this->status]) ? Action::$statuses[$this->status] : null;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param Action $action
     *
     * @return ActionLog
     */
    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @return Action
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param Emergency $emergency
     *
     * @return ActionLog
     */
    public function setEmergency($emergency)
    {
        $this->emergency = $emergency;
        return $this;
    }

    /**
     * @return Emergency
     */
    public function getEmergency()
    {
        return $this->emergency;
    }
}
