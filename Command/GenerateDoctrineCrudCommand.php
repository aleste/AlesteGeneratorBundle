<?php

namespace Aleste\GeneratorBundle\Command;


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
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineCrudGenerator;
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineFormGenerator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;


/**
 * Generates a CRUD for a Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com> 
 * @author Alejandro Steinmetz <asteinmetz78@gmail.com> 
 */
class GenerateDoctrineCrudCommand extends BaseCommand
{
    private $generator;
    private $formGenerator;
    private $filterGenerator;

    protected function createGenerator($bundle = null)
    {
        return new DoctrineCrudGenerator($this->getContainer()->get('filesystem'));
    }

    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('layout', '', InputOption::VALUE_REQUIRED, 'The layout to use for templates', 'GestionBundle::layout.html.twig'),
                new InputOption('body-block', '', InputOption::VALUE_REQUIRED, 'The name of "body" block in your layout. Default is "content"', 'content'),
                new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
                new InputOption('with-write', '', InputOption::VALUE_NONE, 'Whether or not to generate create, new and delete actions'),
                new InputOption('overwrite', '', InputOption::VALUE_NONE, 'Do not stop the generation if crud controller already exist, thus overwriting all generated files'),
                new InputOption('format', '', InputOption::VALUE_REQUIRED, 'Use the format for configuration files (php, xml, yml, or annotation)', 'annotation'),
                new InputOption('use-paginator', '', InputOption::VALUE_NONE,'Whether or not to use paginator'),
                new InputOption('theme', '', InputOption::VALUE_OPTIONAL, 'A possible theme to use in forms'),
                new InputOption('dest', '', InputOption::VALUE_OPTIONAL, 'Change the default destination of the generated code', null),
                new InputOption('with-filter', '', InputOption::VALUE_NONE, 'Whether or not to add filter'),
                new InputOption('with-sort', '', InputOption::VALUE_NONE, 'Whether or not to add sorting'),
            ))
            ->setDescription('Generates a CRUD based on a Doctrine entity')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a CRUD based on a Doctrine entity.

The default command only generates the <comment>list</comment> and <comment>show</comment> actions.

<info>php app/console aleste:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=post_admin</info>

Using the --with-write option allows to generate the <comment>new</comment>, <comment>edit</comment> and <comment>delete</comment> actions.

<info>php app/console aleste:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=post_admin --with-write</info>

Using the --use-paginator option allows to generate <comment>list</comment> action with paginator.

Using the --with-filter option allows to generate <comment>list</comment> action with filter.

Using the --with-sort option allows to generate <comment>list</comment> action with sorting.

Using the --dest option allows to generate CRUD in a different bundle:

<info>php %command.full_name% --entity=AcmeBlogBundle:Post --dest=AnotherBundle</info>
EOT
            )
            ->setName('aleste:generate:crud')
            ->setAliases(array('generate:aleste:crud'))
        ;
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

        $withWrite      = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');
        $layout         = $input->getOption('layout');  // TODO validate
        $bodyBlock      = $input->getOption('body-block');  // TODO validate
        $usePaginator   = $input->getOption('use-paginator');
        $theme          = $input->getOption('theme');  // TODO validate
        $withFilter     = $input->getOption('with-filter');  // TODO validate
        $withSort       = $input->getOption('with-sort');  // TODO validate
        $dest           = $input->getOption('dest')?:$bundle;  // TODO validate

        if ($withFilter && !$usePaginator) {
            throw new \RuntimeException(sprintf('Cannot use filter without paginator.'));
        }

        $questionHelper->writeSection($output, 'CRUD generation');   

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);
        $destBundle  = $this->getContainer()->get('kernel')->getBundle($dest);

        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $destBundle, $entity, $metadata[0], $format, $prefix, $withWrite, $forceOverwrite, $layout, $bodyBlock, $usePaginator, $theme, $withFilter, $withSort);
        $output->writeln('Generating the CRUD code: <info>OK</info>');


        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // form
        if ($withWrite) {
            $this->doGenerateForm($bundle, $destBundle, $entity, $metadata);
            $output->writeln('Generating the Form code: <info>OK</info>');
        }

        // filter form
        if ($withFilter) {
            $this->doGenerateFilter($bundle, $destBundle, $entity, $metadata);
            $output->writeln('Generating the Filter code: <info>OK</info>');
        }

        // routing
        if ('annotation' != $format) {
            $runner($this->updateRouting($questionHelper, $input, $output, $bundle, $format, $entity, $prefix));
        }

        
        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function getFormGenerator($bundle = null)
    {
        if (null === $this->formGenerator) {
            $this->formGenerator = new DoctrineFormGenerator($this->getContainer()->get('filesystem'));
            $this->formGenerator->setSkeletonDirs($this->getSkeletonDirs($bundle));
        }

        return $this->formGenerator;
    }

    protected function getFilterGenerator($bundle = null)
    {
        if (null === $this->filterGenerator) {
            $this->filterGenerator = new DoctrineFormGenerator($this->getContainer()->get('filesystem'));
            $this->filterGenerator->setSkeletonDirs($this->getSkeletonDirs($bundle));
        }

        return $this->filterGenerator;
    }

    protected function doGenerateForm($bundle, $destBundle, $entity, $metadata)
    {
        try {
            $this->getFormGenerator($bundle)->generate($bundle, $destBundle, $entity, $metadata[0]);
        } catch (\RuntimeException $e) {
            // form already exists
        }
    }

    protected function doGenerateFilter($bundle, $destBundle, $entity, $metadata)
    {
        try {
            $this->getFilterGenerator($bundle)->generateFilter($bundle, $destBundle, $entity, $metadata[0]);
        } catch (\RuntimeException $e) {
            // form already exists
        }
    }

    /**
     * add this bundle skeleton dirs to the beginning of the parent skeletonDirs array
     *
     * @param BundleInterface $bundle
     *
     * @return array
     */
    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {
        $baseSkeletonDirs = parent::getSkeletonDirs($bundle);

        $skeletonDirs = array();

        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/AlesteGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        if (is_dir($dir = $this->getContainer()->get('kernel')->getRootdir().'/Resources/AlesteGeneratorBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';

        return array_merge($skeletonDirs, $baseSkeletonDirs);
    }

    /**
     * Override "interact" method to ask for adding parameters
     */    
    protected function interact(InputInterface $input, OutputInterface $output)
    {

        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Aleste Bootstrap 3 CRUD generator');


        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate CRUD controllers and templates.',
            '',
            'First, you need to give the entity for which you want to generate a CRUD.',
            'You can give an entity that does not exist yet and the wizard will help',
            'you defining it.',
            '',
            'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
            '',
        ));

        $question = new Question($questionHelper->getQuestion('The Entity shortcut name', $input->getOption('entity')), $input->getOption('entity'));
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
        $entity = $questionHelper->ask($input, $output, $question);
        
        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        // Entity exists?
        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata = $this->getEntityMetadata($entityClass);

        // layout
        $template = $input->getOption('layout');
        $output->writeln(array(
            '',
            'Select a layout. Example: <comment>AcmeDemoBundle::layout.html.twig</comment>',
            '',
        ));
        // TODO add validator
        
        $layout = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Routes prefix', $input->getOption('layout')), $input->getOption('layout')));
        $input->setOption('layout', $layout);

        // paginator?
        $usePaginator = $input->getOption('use-paginator') ?: false;
        $output->writeln(array(
            '',
            'By default, the generator creates an index action with list of all entites.',
            'You can also ask it to generate a paginator. Please notice that <comment>KnpPaginatorBundle</comment> is required.',
            '',
        ));
        


        $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you want a paginator', $usePaginator ? 'yes' : 'no', '?', $usePaginator), $usePaginator);

        $usePaginator = $questionHelper->ask($input, $output, $question);        
        $input->setOption('use-paginator', $usePaginator);


        // filter?

        $withFilter = $input->getOption('with-filter') ?: false;
        $output->writeln(array(
            '',
            'You can add a filter to generated index. Please notice that <comment>LexikFormFilterBundle </comment> is required.',
            '',
        ));


        $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you want filter', $withFilter ? 'yes' : 'no', '?', $withFilter), $withFilter);

        $withFilter = $questionHelper->ask($input, $output, $question);        
        $input->setOption('with-filter', $withFilter);


        // sort?
        $withSort = $input->getOption('with-sort') ?: false;
        $output->writeln(array(
            '',
            'You can add sort links to columns of generated index.',
            '',
        ));

        $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you want sort', $withSort ? 'yes' : 'no', '?', $withSort), $withSort);

        $withSort = $questionHelper->ask($input, $output, $question);                
        $input->setOption('with-sort', $withSort);

        // write?
        $withWrite = $input->getOption('with-write') ?: false;
        $output->writeln(array(
            '',
            'By default, the generator creates two actions: list and show.',
            'You can also ask it to generate "write" actions: new, update, and delete.',
            '',
        ));

        $question = new ConfirmationQuestion($questionHelper->getQuestion('Do you want to generate the "write" actions', $withWrite ? 'yes' : 'no', '?', $withWrite), $withWrite);

        $withWrite = $questionHelper->ask($input, $output, $question);                
        $input->setOption('with-write', $withWrite);


        
        // format
        $format = $input->getOption('format');
        $output->writeln(array(
            '',
            'Determine the format to use for the generated CRUD.',
            '',
        ));

        $question = new Question($questionHelper->getQuestion('Configuration format (yml, xml, php, or annotation)', $input->getOption('format')), $input->getOption('format'));
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat'));
        $format = $questionHelper->ask($input, $output, $question);        
                
        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(array(
            '',
            'Determine the routes prefix (all the routes will be "mounted" under this',
            'prefix: /prefix/, /prefix/new, ...).',
            '',
        ));
        $prefix = $questionHelper->ask($input, $output, new Question($questionHelper->getQuestion('Routes prefix', '/'.$prefix), '/'.$prefix));
        $input->setOption('route-prefix', $prefix);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf("You are going to generate a Bootstrap 3 CRUD controller for \"<info>%s:%s</info>\"", $bundle, $entity),
            sprintf("using the \"<info>%s</info>\" format.", $format),
            '',
        ));


    }
}
