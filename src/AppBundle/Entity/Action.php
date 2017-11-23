<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as JMS;
use AppBundle\Annotation as SSL;
use Symfony\Component\Validator\Constraints as Assert;
use AppBundle\Helper\DateIntervalHelper;

/**
 * Шаг по устранению НС
 *
 * @ORM\Table(name="action")
 * @ORM\Entity(repositoryClass="AppBundle\Entity\Repository\ActionRepository")
 */
class Action
{
    use SoftDeleteableEntityTrait;

    const DURATION_FORMAT = '%02d %02d:%02d:%02d';
    const DATE_FORMAT = "d.m.Y H:i:s";

    const STATUS_NEW = 0;
    const STATUS_PROGRESS = 1;
    const STATUS_STOP = 2; // остановить не обработаным
    const STATUS_COMPLETED = 3;
    const STATUS_ACTIVE = 4;
    const STATUS_FINISHED = 5;

    public static $statuses = [
        self::STATUS_NEW => "Создано",
        self::STATUS_PROGRESS => "В обработке",
        self::STATUS_STOP => "Отклонено",
        self::STATUS_COMPLETED => "Завершено",
        self::STATUS_ACTIVE => "Необработано",
        self::STATUS_FINISHED => "Завершение подтверждено"
    ];

    public static $codeStatuses = [
        self::STATUS_NEW => "new",
        self::STATUS_PROGRESS => "progress",
        self::STATUS_STOP => "reject",
        self::STATUS_COMPLETED => "done",
        self::STATUS_ACTIVE => "active",
        self::STATUS_FINISHED => "finish"
    ];

    /**
     * Название статуса по его коду
     * @param int $status
     * @return string|null
     *
     * @JMS\SerializedName("status")
     * @JMS\Groups({"api"})
     */
    public static function getStatusName($status)
    {
        return isset(self::$statuses[$status]) ? self::$statuses[$status] : null;
    }

    /**
     * @param int $status
     * @return string|null
     */
    public function getStatusCode()
    {
        return isset(self::$codeStatuses[$this->status]) ? self::$codeStatuses[$this->status] : null;
    }

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @JMS\Groups({"api"})
     */
    private $id;

    /**
     * Время начала выполнения шага
     * @var \DateTime
     *
     * @ORM\Column(name="start_date", type="datetime", nullable = true)
     *
     * @JMS\Groups({"api"})
     */
    private $startDate;

    /**
     * Время, к которому необходимо завершить шаг.
     * Вычисляется на основе RegulationStep::period
     * @var \DateTime
     *
     * @ORM\Column(name="due_date", type="datetime", nullable = true)
     *
     * @JMS\Groups({"api"})
     */
    private $dueDate;

    /**
     * Фактическое время окончания выполнения шага
     * @var \DateTime
     *
     * @ORM\Column(name="end_date", type="datetime", nullable = true)
     *
     * @JMS\Groups({"api"})
     */
    private $endDate;

    /**
     * НС, в рамках которого решается задача
     * @var Emergency
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Emergency", inversedBy="actions")
     * @ORM\JoinColumn(name="emergency_id", referencedColumnName = "id", onDelete = "SET NULL")
     */
    private $emergency;

    /**
     * Задача, из которой склонирован action
     * @var SubTask
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\SubTask", inversedBy = "actions")
     * @ORM\JoinColumn(name="sub_task_id")
     */
    private $subTask;

    /**
     * Статус выполнения
     * @var int
     *
     * @ORM\Column(name="status", type="integer", nullable=true)
     */
    private $status;

    /**
     * Примечание
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable = true)
     *
     * @JMS\Groups({"api"})
     */
    private $description;

    /**
     * @var Action
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Action", inversedBy="nextActions")
     */
    private $prevAction;

    /**
     * @var ArrayCollection|Action[]
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Action", mappedBy="prevAction", cascade={"persist"})
     */
    private $nextActions;

    /**
     * Пользователь, на которого задача назначена
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\User")
     * @ORM\JoinColumn(name="user_id")
     */
    private $user;

    /**
     * @var integer
     *
     * @ORM\Column(name="view_group", type="integer", nullable=true)
     */
    private $group;

    /* Extra fields by closing action of regulation (dependens on user subdivision) */
    
    /**
     * @ORM\OneToMany(targetEntity="EngagedService", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Внешние службы", set="subdivision")
     * @Assert\Valid()
     */
    private $engagedServices;
    
    /**
     * @ORM\OneToMany(targetEntity="EngagedSubdivision", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Службы МГТ", set="subdivision")
     * @Assert\Valid()
     */
    private $engagedSubdivisions;
    
    /**
     * @var ArrayCollection|EngagedVehicle[]
     * @ORM\OneToMany(targetEntity="EngagedVehicle", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Привлеченные ТС", set="subdivision")
     * @Assert\Valid()
     */
    protected $engagedVehicles;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="application_time", type="datetime", nullable = true)
     * @SSL\FieldSet(title="Дата и время принятия заявки", set="subdivision")
     */
    protected $applicationTime;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="team_challenge", type="boolean", nullable = true)
     * @SSL\FieldSet(title="Вызов бригады", set="subdivision")
     */
    protected $teamChallenge;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="op_approval", type="boolean", nullable = true)
     * @SSL\FieldSet(title="Утверждение оперативного плана", set="subdivision")
     */
    protected $opApproval;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="finish_emergency_cause", type="boolean", nullable = true)
     * @SSL\FieldSet(title="Завершение события-причины НС", set="subdivision")
     */
    protected $finishEmergencyCause;

    /**
     * @var boolean
     *
     * @ORM\Column(name="cr_activation", type="boolean", nullable = true)
     * @SSL\FieldSet(title="Запустить КМ", set="subdivision")
     */
    protected $CRActivation;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="cr_agreement", type="boolean", nullable = true)
     * @SSL\FieldSet(title="Отправить КМ на согласование", set="subdivision")
     */
    protected $CRAgreement;
    
    /**
     * Бригадир
     * @var string
     *
     * @ORM\Column(name="taskmaster", type="string", length=255, nullable = true)
     */
    protected $taskmaster;
    
    /**
     * Время начала работы бригады
     * @var \DateTime
     *
     * @ORM\Column(name="start_team_time", type="datetime", nullable = true)
     */
    protected $startTeamTime;
    
    /**
     * Время окончания работы бригады
     * @var \DateTime
     *
     * @ORM\Column(name="end_team_time", type="datetime", nullable = true)
     */
    protected $endTeamTime;
    
    /**
     * Время прибытия бригады
     * @var \DateTime
     *
     * @ORM\Column(name="arrival_team_time", type="datetime", nullable = true)
     */
    protected $arrivalTeamTime;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="welder_challenge", type="boolean", nullable = true)
     * @SSL\FieldSet(title="Вызов газосварщика", set="subdivision")
     */
    protected $welderChallenge;
    
    /**
     * @var ArrayCollection|Job[]
     * @ORM\OneToMany(targetEntity="Job", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Работы", set="subdivision")
     * @Assert\Valid()
     */
    protected $jobs;
    
    /**
     * @var ArrayCollection|CableDisconnection[]
     * @ORM\OneToMany(targetEntity="CableDisconnection", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Отключеные кабели", set="subdivision")
     * @Assert\Valid()
     */
    protected $cables;
    
    /**
     * @var ArrayCollection|SubstationDisconnection[]
     * @ORM\OneToMany(targetEntity="SubstationDisconnection", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Отключенные подстанции", set="subdivision")
     * @Assert\Valid()
     */
    protected $substations;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="solution_time", type="datetime", nullable = true)
     * @SSL\FieldSet(title="Дата и время устранения НС (прогноз)", set="subdivision")
     */
    protected $solutionTime;
    
    /**
     * @var ArrayCollection|ChangeRoute[]
     * @ORM\OneToMany(targetEntity="ChangeRoute", mappedBy="action", cascade={"persist"})
     *
     * @SSL\FieldSet(title="Оперативные изменения маршрута", set="subdivision")
     * @Assert\Valid()
     */
    protected $changeRoutes;
    
    /**
     * Буферная переменная, для оповещения при выборе Вызов газосварщика
     * @var CustomNotification
     */
    protected $customNotification;
    
    /**
     * @var boolean
     *
     * @SSL\FieldSet(title="Отправить список ТС", set="subdivision")
     */
    protected $sendListVehicle;
    
    /**
     * Буферная переменная, для отправки списка тс
     * @var CustomNotification
     */
    protected $notificationListVehicle;
    
    public function __construct()
    {
        $this->status = self::STATUS_NEW;
        $this->startDate = new \DateTime();
        $this->nextActions = new ArrayCollection();
        $this->engagedServices = new ArrayCollection();
        $this->engagedSubdivisions = new ArrayCollection();
        $this->engagedVehicles = new ArrayCollection();
        $this->jobs = new ArrayCollection();
        $this->cables = new ArrayCollection();
        $this->substations = new ArrayCollection();
        $this->changeRoutes = new ArrayCollection();
    }

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
     * @return Action
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return EngagedService[]|ArrayCollection
     */
    public function getEngagedServices()
    {
        return $this->engagedServices;
    }

    /**
     * @param EngagedService[]|ArrayCollection $services
     *
     * @return Action
     */
    public function setEngagedServices($services)
    {
        $this->engagedServices = $services;

        foreach ($this->engagedServices as $service) {
            $service->setAction($this);
        }
    }
    
    /**
     * @return EngagedSubdivision[]|ArrayCollection
     */
    public function getEngagedSubdivisions()
    {
        return $this->engagedSubdivisions;
    }
    
    /**
     * @return ArrayCollection|EngagedVehicle[]
     */
    public function getEngagedVehicles()
    {
        return $this->engagedVehicles;
    }
    
    /**
     * @param EngagedVehicle[]|ArrayCollection $engaged
     */
    public function setEngagedVehicles($engaged)
    {
        $this->engagedVehicles = $engaged;
        foreach ($this->engagedVehicles as $engageVehicle) {
            $engageVehicle->setAction($this);
        }
    }
    
    /**
     * @return \DateTime
     */
    public function getApplicationTime()
    {
        return $this->applicationTime;
    }

    /**
     * @return Boolean
     */
    public function getTeamChallenge()
    {
        return $this->teamChallenge;
    }

    /**
     * @return string
     */
    public function getTeamChallengeString()
    {
        return $this->teamChallenge ? 'Да' : 'Нет';
    }

    /**
     * @return Boolean
     */
    public function getFinishEmergencyCause()
    {
        return $this->finishEmergencyCause;
    }

    /**
     * @return string
     */
    public function getFinishEmergencyCauseString()
    {
        return $this->finishEmergencyCause ? 'Да' : 'Нет';
    }

    /**
     * @param Boolean $finishEmergencyCause
     */
    public function setFinishEmergencyCause($finishEmergencyCause)
    {
        $this->finishEmergencyCause = $finishEmergencyCause;
    }
    
    /**
     * @return Boolean
     */
    public function getOpApproval()
    {
        return $this->opApproval;
    }

    /**
     * @return string
     */
    public function getOpApprovalString()
    {
        return $this->opApproval ? 'Да' : 'Нет';
    }
    
    /**
     * @param Boolean $opApproval
     */
    public function setOpApproval($opApproval)
    {
        $this->opApproval = $opApproval;
    }

    /**
     * @return Boolean
     */
    public function getCRAgreement()
    {
        return $this->CRAgreement;
    }

    /**
     * @return string
     */
    public function getCRAgreementString()
    {
        return $this->CRAgreement ? 'Да' : 'Нет';
    }
    
    /**
     * @param Boolean $CRAgreement
     */
    public function setCRAgreement($CRAgreement)
    {
        $this->CRAgreement = $CRAgreement;
    }

    /**
     * @return Boolean
     */
    public function getCRActivation()
    {
        return $this->CRActivation;
    }

    /**
     * @return string
     */
    public function getCRActivationString()
    {
        return $this->CRActivation ? 'Да' : 'Нет';
    }
    
    /**
     * @param Boolean $CRActivation
     */
    public function setCRActivation($CRActivation)
    {
        $this->CRActivation = $CRActivation;
    }
    
    /**
     * @return string
     */
    public function getTaskmaster()
    {
        return $this->taskmaster;
    }

    /**
     * @return \DateTime
     */
    public function getStartTeamTime()
    {
        return $this->startTeamTime;
    }
    
    /**
     * @return \DateTime
     */
    public function getEndTeamTime()
    {
        return $this->endTeamTime;
    }
    
    /**
     * @return \DateTime
     */
    public function getArrivalTeamTime()
    {
        return $this->arrivalTeamTime;
    }
    
    /**
     * @return Boolean
     */
    public function getWelderChallenge()
    {
        return $this->welderChallenge;
    }
    
    /**
     * @return string
     */
    public function getWelderChallengeString()
    {
        return $this->welderChallenge ? 'Да' : 'Нет';
    }
    
    /**
     * @return ArrayCollection|Job[]
     */
    public function getJobs()
    {
        return $this->jobs;
    }
    
    /**
     * @return ArrayCollection|CableDisconnection[]
     */
    public function getCables()
    {
        return $this->cables;
    }

    /**
     * @return ArrayCollection|SubstationDisconnect[]
     */
    public function getSubstations()
    {
        return $this->substations;
    }

    /**
     * @return \DateTime
     */
    public function getSolutionTime()
    {
        return $this->solutionTime;
    }
    
    /**
     * @param \DateTime $applicationTime
     */
    public function setApplicationTime($applicationTime)
    {
        $this->applicationTime = $applicationTime;
    }

    /**
     * @param Boolean $teamChallenge
     */
    public function setTeamChallenge($teamChallenge)
    {
        $this->teamChallenge = $teamChallenge;
    }

    /**
     * @param string $taskmaster
     */
    public function setTaskmaster($taskmaster)
    {
        $this->taskmaster = $taskmaster;
    }

    /**
     * @param \DateTime $startTeamTime
     */
    public function setStartTeamTime($startTeamTime)
    {
        $this->startTeamTime = $startTeamTime;
    }

    /**
     * @param \DateTime $endTeamTime
     */
    public function setEndTeamTime($endTeamTime)
    {
        $this->endTeamTime = $endTeamTime;
    }

    /**
     * @param \DateTime $arrivalTeamTime
     */
    public function setArrivalTeamTime($arrivalTeamTime)
    {
        $this->arrivalTeamTime = $arrivalTeamTime;
    }

    /**
     * @param boolean $welderChallenge
     */
    public function setWelderChallenge($welderChallenge)
    {
        $this->welderChallenge = $welderChallenge;
    }

    /**
     * @param ArrayCollection|Job[] $jobs
     */
    public function setJobs($jobs)
    {
        $this->jobs = $jobs;
        
        foreach ($this->jobs as $job) {
            $job->setAction($this);
        }
    }

    /**
     * @param ArrayCollection|CableConnection[] $cables
     */
    public function setCables($cables)
    {
        $this->cables = $cables;
        
        foreach ($this->cables as $cable) {
            $cable->setAction($this);
        }
    }

    /**
     * @param ArrayCollection|Substation[] $substations
     */
    public function setSubstations($substations)
    {
        $this->substations = $substations;
        
        foreach ($this->substations as $substation) {
            $substation->setAction($this);
        }
    }

    /**
     * @param \DateTime $solutionTime
     */
    public function setSolutionTime($solutionTime)
    {
        $this->solutionTime = $solutionTime;
    }

    /**
     * @param string $station
     */
    public function setStation($station)
    {
        $this->station = $station;
    }

    /**
     * @param EngagedSubdivision[]|ArrayCollection $subdivisions
     */
    public function setEngagedSubdivisions($subdivisions)
    {
        $this->engagedSubdivisions = $subdivisions;

        foreach ($this->engagedSubdivisions as $subdivision) {
            $subdivision->setAction($this);
        }
    }
    
    /**
     * @return \DateInterval
     */
    public function getTimeLeft()
    {
        if ($this->dueDate and $period = $this->getSubTask()->getPeriod()) {
            return $this->dueDate->diff(new \DateTime("now"));
        }
    }

    /**
     * @return string
     */
    public function getTimeLeftFormat()
    {
        if ($interval = $this->getTimeLeft()) {
            return $interval->format(DateIntervalHelper::formatting($interval));
        }
    }

    /**
     * @param \DateInterval $timeInterval
     *
     * @return string
     */
    public function getFormattedTime($timeInterval)
    {
        $format = '%h:%I:%S';

        return $timeInterval->format($format);
    }

    /**
     * @return bool
     */
    public function getTimeInvert()
    {
        if ($this->dueDate and $period = $this->getSubTask()->getPeriod()) {
            return $this->dueDate->diff(new \DateTime("now"))->invert;
        }
    }

    /**
     * @param int $min
     * @return bool
     */
    public function getTimeAlmostOver($min = null)
    {
        if ($this->dueDate and $period = $this->getSubTask()->getPeriod()) {
            $time = new \DateTime("now");
            date_add($time, date_interval_create_from_date_string("$min minute"));
            return $this->dueDate->diff($time)->invert;
        }
    }

    /**
     * @param int $min
     * @return null|\DateTimeInterval
     */
    public function getTimeOver()
    {
        if ($this->startDate && $this->dueDate) {
            $endTime = new \DateTime();
            if ($this->endDate) {
                $endTime = $this->endDate;
            }
            if ($endTime->getTimestamp() > $this->dueDate->getTimestamp()) {
                return $endTime->diff($this->dueDate)->format('%a дней %H:%i:%s');
            }
        }

        return null;
    }

    /**
     * @param \DateTime $startDate
     * @return Action
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return \DateTime
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param \DateTime $dueDate
     *
     * @return Action
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }
    
    /**
     * @param \DateTime $endDate
     *
     * @return Action
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * @param Emergency $emergency
     *
     * @return Action
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

    /**
     * @return SubTask
     */
    public function getSubTask()
    {
        return $this->subTask;
    }

    /**
     * @param SubTask $task
     * @return Action
     */
    public function setSubTask($task)
    {
        $this->subTask = $task;
        return $this;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getStatus() == Action::STATUS_NEW || $this->getStatus() == Action::STATUS_ACTIVE || $this->getStatus() == Action::STATUS_PROGRESS || $this->getStatus() == Action::STATUS_COMPLETED;
    }

    /**
     * @param string
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return Action
     */
    public function getPrevAction()
    {
        return $this->prevAction;
    }

    /**
     * @param Action $prevAction
     */
    public function setPrevAction($prevAction)
    {
        $this->prevAction = $prevAction;
    }

    /**
     * @return Action[]|ArrayCollection
     */
    public function getNextActions()
    {
        return $this->nextActions;
    }

    /**
     * @param Action[]|ArrayCollection $nextActions
     */
    public function setNextActions($nextActions)
    {
        $this->nextActions = $nextActions;
    }

    /**
     * @param Action $nextAction
     */
    public function addNextAction($nextAction)
    {
        $this->nextActions->add($nextAction);
    }

    /**
     * @return CustomNotification
     */
    public function getCustomNotification()
    {
        return $this->customNotification;
    }
    
    /**
     * @param CustomNotification $notification
     */
    public function setCustomNotification($notification)
    {
        $this->customNotification = $notification;
    }
    
     /**
     * @return CustomNotification
     */
    public function getNotificationListVehicle()
    {
        return $this->notificationListVehicle;
    }
    
    /**
     * @param CustomNotification $notification
     */
    public function setNotificationListVehicle($notification)
    {
        $this->notificationListVehicle = $notification;
    }
    
    /**
     * @return string
     *
     * @JMS\VirtualProperty()
     * @JMS\SerializedName("status")
     * @JMS\Groups({"api"})
     */
    public function getStatusString()
    {
        return self::getStatusName($this->status);
    }

    public function __toString()
    {
        return $this->getTitle();
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->subTask ? $this->subTask->getTitle() : "";
    }

    /**
     * @return int
     */
    public function getStepNumber()
    {
        return $this->subTask->getStep();
    }

    /**
     * @return Person
     */
    public function getPerson()
    {
        return $this->regulationStep->getPerson();
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     *
     * @JMS\Groups({"api"})
     */
    public function getUserName()
    {
        return $this->user ? $this->user->getFullName() : '';
    }

    /**
     * @return integer
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param integer $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @return string
     *
     * @JMS\Groups({"api"})
     */
    public function getTask()
    {
        return $this->subTask->getTitle();
    }

    /**
     * @return \DateTime
     */
    public function getPeriod()
    {
        return $this->subTask->getPeriod();
    }

    /**
     * @return bool
     */
    public function isFinished()
    {
        return $this->status == self::STATUS_COMPLETED || $this->status == self::STATUS_FINISHED;
    }
    
    /**
     * @return ChangeRoutes[]|ArrayCollection
     */
    public function getChangeRoutes()
    {
        return $this->changeRoutes;
    }

    /**
     * @param ChangeRoutes[]|ArrayCollection $changes
     */
    public function setChangeRoutes($changes)
    {
        $this->changeRoutes = $changes;
        
        foreach ($this->changeRoutes as $route) {
            $route->setAction($this);
        }
    }
    
    /**
     * @return Boolean
     */
    public function getSendListVehicle()
    {
        return $this->sendListVehicle;
    }
    
    /**
     * @param Boolean $send
     */
    public function setSendListVehicle($send)
    {
        $this->sendListVehicle = $send;
    }
    
    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id,
            'title' => str_replace('"', "'", $this->subTask->getTitle()),
            'prevId' => $this->prevAction ? $this->prevAction->getId() : null,
            'prevTitle' => $this->prevAction ? str_replace('"', "'", $this->prevAction->getSubTask()->getTitle()) : null,
            'taskId' => ($this->subTask->getTask()) ? $this->subTask->getTask()->getId() : $this->group,
            'status' => $this->getStatusString(),
            'prevStatus' => $this->prevAction ? $this->prevAction->getStatusString() : null,
            'user' => ($this->user)? $this->user->getFullName() : '',
            'role' => ($this->subTask)? $this->subTask->getRole()->getTitle() : '',
            'group' => $this->group,
            'prevGroup' => $this->prevAction ? $this->prevAction->getGroup() : null,
            'taskTitle' => $this->subTask ? str_replace('"', "'", $this->subTask->getTitle()) : '',
            'timePlan' => ($this->subTask)? $this->subTask->getPeriodFormat() : '',
            'timeFact' => ($this->endDate && $this->startDate) ? $this->getFormattedTime($this->endDate->diff($this->startDate)) : ''
        ];
    }
}
