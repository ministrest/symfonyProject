<?php

namespace AppBundle\Command;

use AppBundle\Entity\CompensationRoute;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendCompensationRoutesToArchiveCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sc:compensationRoute:cr:archive')
            ->setDescription('');
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $compensationRoutes = $this->em->getRepository(CompensationRoute::class)->findExpired(CompensationRoute::STATUS_CLOSE);
        foreach ($compensationRoutes as $compensationRoute) {
            $compensationRoute->setStatus(CompensationRoute::STATUS_ARCHIVE);
        }
        $this->em->flush();
    }
}
