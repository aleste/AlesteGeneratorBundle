<?php

namespace Aleste\GeneratorBundle\Command;

use Aleste\GeneratorBundle\Generator\AlesteCrudGenerator;
//use Aleste\GeneratorBundle\Generator\DoctrineFormGenerator;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Sensio\Bundle\GeneratorBundle\Command\Validators;

/**
 * Generates a CRUD for a Doctrine entity.
 * 
 * @author Alejandro Steinmetz <asteinmetz78@gmail.com> 
 */
class AlesteCrudCommand extends GenerateDoctrineCrudCommand
{
    protected $generator;
    protected $formGenerator;
    protected $filterGenerator;

    /**
     * @see Command
     */
    protected function configure()
    {

        parent::configure();
        $this->setName('aleste:generate:crud')
             ->setDescription('Generates a CRUD based on a Doctrine entity with Boostrap 3 theme, filters and paginator');
    }

    protected function createGenerator($bundle = null)
    {
        return new AlesteCrudGenerator($this->getContainer()->get('filesystem'));
    }


    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {
        $skeletonDirs = array();
        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/SensioGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }
        if (is_dir($dir = $this->getContainer()->get('kernel')->getRootdir().'/Resources/SensioGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }
        $skeletonDirs[] = $this->getContainer()->get('kernel')->locateResource('@AlesteCrudGeneratorBundle/Resources/skeleton');
        $skeletonDirs[] = $this->getContainer()->get('kernel')->locateResource('@AlesteCrudGeneratorBundle/Resources');
        return $skeletonDirs;
    }  

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Aleste Bootstrap 3 CRUD generator');        
        parent::interact($input, $output);
    }

}
