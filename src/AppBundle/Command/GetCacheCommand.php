<?php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetCacheCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('sc:get:cache')
             ->addArgument('key', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = $this->getContainer()->get('app.cache');
        $data = $cache->get($input->getArgument('key'));
        
        var_dump($data);
    }
    
}