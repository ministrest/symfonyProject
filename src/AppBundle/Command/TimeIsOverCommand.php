<?php

namespace AppBundle\Command;

use AppBundle\Entity\Action;
use AppBundle\Service\MessageListService;
use AppBundle\Service\Notification\NotificationService;
use AppBundle\Service\SettingService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TimeIsOverCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var NotificationService
     */
    private $notification;

    /**
     * @var SettingService
     */
    private $settings;

    /**
     * @var MessageListService
     */
    private $messageList;
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sc:timeover')
            ->setDescription('Notify users if time is over');
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->notification = $this->getContainer()->get('app.notification');
        $this->settings = $this->getContainer()->get('app.system.settings');
        $this->messageList = $this->getContainer()->get('app.message.list');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $actions = $this->em->getRepository(Action::class)->findActive();
        /* @var $action Action */
        foreach ($actions as $action) {
            if ($action->getStatusCode() == "progress") {
                $setting = $this->settings->get();
                $time = $action->getTimeAlmostOver($setting->getTimeover());
                if ($time == 0) {
                    $timeLeft = $action->getTimeLeftFormat();
                    $title = $action->getTitle();
                    $text = ($action->getTimeInvert() == 0) ? "Вы просрочили задачу $title на $timeLeft" : "Время выполнения задачи $title заканчивается. Осталось $timeLeft";

                    if ($user = $action->getUser() and $emergency = $action->getEmergency()) {
                        if (!$this->messageList->updateByAction($user, $text, $emergency, $action)) {
                            if ($action->getTimeInvert() == 0 and $boss = $user->getBoss()) {
                                $name = $user->getFullName();
                                $text = 'Задача ' . $emergency->__toString() . ' ' . $action->getTitle() . ' просрочена пользователем ' . $name . ' на ' . $timeLeft;
                                $this->notification->clearList("emails");
                                $this->notification->notifyByEmail(null, $boss, $text, $name);
                            }
                        }
                    }
                }
            }
        }
    }
}
