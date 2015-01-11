<?php

namespace Aleste\GeneratorBundle\Command;

use Aleste\GeneratorBundle\Generator\DoctrineCrudGenerator;
use Aleste\GeneratorBundle\Generator\DoctrineFormGenerator;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand as BaseCommand;
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
class GenerateDoctrineCrudCommand extends BaseCommand
{
    protected $generator;
    protected $formGenerator;

    protected function configure()
    {
        parent::configure();

        $this->setName('aleste:generate:crud');
        $this->setDescription('A CRUD generator with paginating and filters.');
    }

    protected function createGenerator($bundle = null)
    {
        return new DoctrineCrudGenerator($this->getContainer()->get('filesystem'));
    }


    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $format = Validators::validateFormat($input->getOption('format'));
        $prefix = $this->getRoutePrefix($input, $entity);
        $withWrite = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');

        $questionHelper->writeSection($output, 'CRUD generation');

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);

        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $bundle, $entity, $metadata[0], $format, $prefix, $withWrite, $forceOverwrite);

        $output->writeln('Generating the CRUD code: <info>OK</info>');

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // form
        if ($withWrite) {
            $output->write('Generating the Form code: ');
            if ($this->generateForm($bundle, $entity, $metadata)) {
                $output->writeln('<info>OK</info>');
            } else {
                $output->writeln('<warning>Already exists, skipping</warning>');
            }
        }

        // routing
        if ('annotation' != $format) {
            $runner($this->updateRouting($questionHelper, $input, $output, $bundle, $format, $entity, $prefix));
        }

        $questionHelper->writeGeneratorSummary($output, $errors);
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

        $skeletonDirs[] = $this->getContainer()->get('kernel')->locateResource('@AlesteGeneratorBundle/Resources/skeleton');
        $skeletonDirs[] = $this->getContainer()->get('kernel')->locateResource('@AlesteGeneratorBundle/Resources');

        return $skeletonDirs;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Aleste Bootstrap 3 CRUD generator');

        parent::interact($input, $output);
    }
}
