<?php

namespace PhpBench\Extension;

use PhpBench\Extension;
use PhpBench\Container;
use PhpBench\ProgressLogger\DotsProgressLogger;
use PhpBench\Report\Generator\ConsoleTableGenerator;
use PhpBench\ProgressLoggerRegistry;
use PhpBench\Report\ReportManager;
use PhpBench\Result\Loader\XmlLoader;
use PhpBench\Result\Dumper\XmlDumper;
use PhpBench\Console\Command\RunCommand;
use PhpBench\Console\Command\ReportCommand;
use PhpBench\Benchmark\CollectionBuilder;
use PhpBench\Benchmark\SubjectBuilder;
use PhpBench\Benchmark\Runner;
use PhpBench\Console\Application;
use Symfony\Component\Finder\Finder;
use PhpBench\Report\Generator\CompositeGenerator;

class CoreExtension implements Extension
{
    public function configure(Container $container)
    {
        $container->register('console.application', function (Container $container) {
            $application = new Application();

            foreach (array_keys($container->getServiceIdsForTag('console.command')) as $serviceId) {
                $command = $container->get($serviceId);
                $application->add($command);
            }

            return $application;
        });
        $container->register('benchmark.runner', function (Container $container) {
            return new Runner(
                $container->get('benchmark.collection_builder'),
                $container->get('benchmark.subject_builder'),
                $container->getParameter('config_path')
            );
        });
        $container->register('benchmark.finder', function (Container $container) {
            return new Finder();
        });
        $container->register('benchmark.subject_builder', function (Container $container) {
            return new SubjectBuilder();
        });
        $container->register('benchmark.collection_builder', function (Container $container) {
            return new CollectionBuilder($container->get('benchmark.finder'));
        });
        $container->register('console.command.run', function (Container $container) {
            return new RunCommand(
                $container->get('benchmark.runner'),
                $container->get('result.dumper.xml'),
                $container->get('report.manager'),
                $container->get('progress_logger.registry'),
                $container->getParameter('progress_logger_name'),
                $container->getParameter('path'),
                $container->getParameter('enable_gc'),
                $container->getParameter('config_path')
            );
        }, array('console.command' => array()));

        $container->register('console.command.report', function (Container $container) {
            return new ReportCommand(
                $container->get('result.loader.xml'),
                $container->get('report.manager')
            );
        }, array('console.command' => array()));
        $container->register('result.dumper.xml', function () {
            return new XmlDumper();
        });
        $container->register('result.loader.xml', function () {
            return new XmlLoader();
        });
        $container->register('report.manager', function () {
            return new ReportManager();
        });
        $container->register('progress_logger.registry', function (Container $container) {
            return new ProgressLoggerRegistry();
        });
        $this->registerProgressLoggers($container);
        $this->registerReportGenerators($container);

        $container->mergeParameters(array(
            'enable_gc' => false,
            'path' => null,
            'reports' => array(),
            'config_path' => null,
            'progress_logger_name' => 'benchdots'
        ));
    }

    public function build(Container $container)
    {
        foreach ($container->getServiceIdsForTag('progress_logger') as $serviceId => $attributes) {
            $progressLogger = $container->get($serviceId);
            $container->get('progress_logger.registry')->addProgressLogger($attributes['name'], $progressLogger);
        }

        foreach ($container->getServiceIdsForTag('report_generator') as $serviceId => $attributes) {
            $reportGenerator = $container->get($serviceId);
            $container->get('report.manager')->addGenerator($attributes['name'], $reportGenerator);
        }

        foreach ($container->getParameter('reports') as $reportName => $report) {
            $container->get('report.manager')->addReport($reportName, $report);
        }
    }

    private function registerProgressLoggers(Container $container)
    {
        $container->register('progress_logger.dots', function (Container $container) {
            return new DotsProgressLogger();
        }, array('progress_logger' => array('name' => 'dots')));

        $container->register('progress_logger.benchdots', function (Container $container) {
            return new DotsProgressLogger(true);
        }, array('progress_logger' => array('name' => 'benchdots')));
    }

    private function registerReportGenerators(Container $container)
    {
        $container->register('report_generator.console_table', function () {
            return new ConsoleTableGenerator();
        }, array('report_generator' => array('name' => 'console_table')));
        $container->register('report_generator.composite', function (Container $container) {
            return new CompositeGenerator($container->get('report.manager'));
        }, array('report_generator' => array('name' => 'composite')));
    }
}