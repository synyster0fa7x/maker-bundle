<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MakerBundle\Maker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\Mapping\Column;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineEntityHelper;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\GeneratorHelper;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validation;

/**
 * @author Sadicov Vladimir <sadikoff@gmail.com>
 */
final class MakeCrud extends AbstractMaker
{
    private $router;
    private $fileManager;
    private $entityHelper;

    public function __construct(RouterInterface $router, FileManager $fileManager, DoctrineEntityHelper $entityHelper)
    {
        $this->router = $router;
        $this->fileManager = $fileManager;
        $this->entityHelper = $entityHelper;
    }

    public static function getCommandName(): string
    {
        return 'make:crud';
    }

    /**
     * {@inheritdoc}
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig)
    {
        $command
            ->setDescription('Creates crud for Doctrine entity class')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create crud (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->setHelp(file_get_contents(__DIR__.'/../Resources/help/MakeCrud.txt'))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(InputInterface $input): array
    {
        $entityClassName = Str::asClassName($input->getArgument('entity-class'));
        Validator::validateClassName($entityClassName);
        $controllerClassName = Str::asClassName($entityClassName, 'Controller');
        Validator::validateClassName($controllerClassName);
        $formClassName = Str::asClassName($entityClassName, 'Type');
        Validator::validateClassName($formClassName);

        if (!$this->fileManager->fileExists('src/Entity/'.$entityClassName.'.php')) {
            throw new RuntimeCommandException(sprintf('Entity "%s" doesn\'t exists in your project. May be you would like to create it with "make:entity" command?', $entityClassName));
        }

        $metadata = $this->entityHelper->getEntityMetadata($entityClassName);

        $baseLayoutExists = $this->fileManager->fileExists('templates/base.html.twig');

        $helper = new GeneratorHelper();

        return [
            'helper' => $helper,
            'controller_class_name' => $controllerClassName,
            'entity_var_plural' => lcfirst(Inflector::pluralize($entityClassName)),
            'entity_var_singular' => lcfirst(Inflector::singularize($entityClassName)),
            'entity_class_name' => $entityClassName,
            'entity_identifier' => $metadata->identifier[0],
            'entity_fields' => $metadata->fieldMappings,
            'form_class_name' => $formClassName,
            'form_fields' => $this->entityHelper->getFormFieldsFromEntity($entityClassName),
            'route_path' => Str::asRoutePath(str_replace('Controller', '', $controllerClassName)),
            'route_name' => Str::asRouteName(str_replace('Controller', '', $controllerClassName)),
            'base_layout_exists' => $baseLayoutExists,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles(array $params): array
    {
        return [
            __DIR__.'/../Resources/skeleton/crud/controller/Controller.tpl.php' => 'src/Controller/'.$params['controller_class_name'].'.php',
            __DIR__.'/../Resources/skeleton/form/Type.tpl.php' => 'src/Form/'.$params['form_class_name'].'.php',
            __DIR__.'/../Resources/skeleton/crud/templates/_delete_form.tpl.php' => 'templates/'.$params['route_name'].'/_delete_form.html.twig',
            __DIR__.'/../Resources/skeleton/crud/templates/_form.tpl.php' => 'templates/'.$params['route_name'].'/_form.html.twig',
            __DIR__.'/../Resources/skeleton/crud/templates/index.tpl.php' => 'templates/'.$params['route_name'].'/index.html.twig',
            __DIR__.'/../Resources/skeleton/crud/templates/show.tpl.php' => 'templates/'.$params['route_name'].'/show.html.twig',
            __DIR__.'/../Resources/skeleton/crud/templates/new.tpl.php' => 'templates/'.$params['route_name'].'/new.html.twig',
            __DIR__.'/../Resources/skeleton/crud/templates/edit.tpl.php' => 'templates/'.$params['route_name'].'/edit.html.twig',
        ];
    }

    public function writeSuccessMessage(array $params, ConsoleStyle $io)
    {
        parent::writeSuccessMessage($params, $io);

        $io->text('Next: Check your new crud!');
    }

    /**
     * {@inheritdoc}
     */
    public function configureDependencies(DependencyBuilder $dependencies)
    {
        $dependencies->addClassDependency(
            Route::class,
            'annotations'
        );

        $dependencies->addClassDependency(
            AbstractType::class,
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator'
        );

        $dependencies->addClassDependency(
            TwigBundle::class,
            'twig-bundle'
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm'
        );

        $dependencies->addClassDependency(
            Column::class,
            'orm'
        );
    }
}
