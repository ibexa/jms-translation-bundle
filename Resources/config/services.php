<?php

declare(strict_types=1);

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Common\Annotations\DocParser;
use JMS\TranslationBundle\Controller\ApiController;
use JMS\TranslationBundle\Controller\TranslateController;
use JMS\TranslationBundle\Translation\ConfigFactory;
use JMS\TranslationBundle\Translation\Dumper\PhpDumper;
use JMS\TranslationBundle\Translation\Dumper\SymfonyDumperAdapter;
use JMS\TranslationBundle\Translation\Dumper\XliffDumper;
use JMS\TranslationBundle\Translation\Dumper\YamlDumper;
use JMS\TranslationBundle\Translation\Extractor\File\AuthenticationMessagesExtractor;
use JMS\TranslationBundle\Translation\Extractor\File\DefaultPhpFileExtractor;
use JMS\TranslationBundle\Translation\Extractor\File\FormExtractor;
use JMS\TranslationBundle\Translation\Extractor\File\TranslationContainerExtractor;
use JMS\TranslationBundle\Translation\Extractor\File\TwigFileExtractor;
use JMS\TranslationBundle\Translation\Extractor\File\ValidationExtractor;
use JMS\TranslationBundle\Translation\Extractor\FileExtractor;
use JMS\TranslationBundle\Translation\ExtractorManager;
use JMS\TranslationBundle\Translation\FileSourceFactory;
use JMS\TranslationBundle\Translation\FileWriter;
use JMS\TranslationBundle\Translation\Loader\Symfony\XliffLoader as SymfonyXliffLoader;
use JMS\TranslationBundle\Translation\Loader\SymfonyLoaderAdapter;
use JMS\TranslationBundle\Translation\Loader\XliffLoader;
use JMS\TranslationBundle\Translation\LoaderManager;
use JMS\TranslationBundle\Translation\Updater;
use JMS\TranslationBundle\Twig\TranslationExtension;

return static function (ContainerConfigurator $container): void {
    $parameters = $container->parameters();

    $parameters
        ->set('jms_translation.twig_extension.class', TranslationExtension::class)

        ->set('jms_translation.controller.translate_controller.class', TranslateController::class)
        ->set('jms_translation.controller.api_controller.class', ApiController::class)

        ->set('jms_translation.extractor_manager.class', ExtractorManager::class)
        ->set('jms_translation.extractor.file_extractor.class', FileExtractor::class)
        ->set('jms_translation.extractor.file.default_php_extractor', DefaultPhpFileExtractor::class)
        ->set('jms_translation.extractor.file.translation_container_extractor', TranslationContainerExtractor::class)
        ->set('jms_translation.extractor.file.twig_extractor', TwigFileExtractor::class)
        ->set('jms_translation.extractor.file.form_extractor.class', FormExtractor::class)
        ->set('jms_translation.extractor.file.validation_extractor.class', ValidationExtractor::class)
        ->set('jms_translation.extractor.file.authentication_message_extractor.class', AuthenticationMessagesExtractor::class)

        ->set('jms_translation.loader.symfony.xliff_loader.class', SymfonyXliffLoader::class)
        ->set('jms_translation.loader.xliff_loader.class', XliffLoader::class)
        ->set('jms_translation.loader.symfony_adapter.class', SymfonyLoaderAdapter::class)
        ->set('jms_translation.loader_manager.class', LoaderManager::class)

        ->set('jms_translation.dumper.php_dumper.class', PhpDumper::class)
        ->set('jms_translation.dumper.xliff_dumper.class', XliffDumper::class)
        ->set('jms_translation.dumper.yaml_dumper.class', YamlDumper::class)
        ->set('jms_translation.dumper.symfony_adapter.class', SymfonyDumperAdapter::class)

        ->set('jms_translation.file_writer.class', FileWriter::class)

        ->set('jms_translation.updater.class', Updater::class)
        ->set('jms_translation.config_factory.class', ConfigFactory::class)
        ->set('jms_translation.file_source_factory.class', FileSourceFactory::class);

    $services = $container->services();

    // Controllers
    $services->set('jms_translation.controller.translate_controller', '%jms_translation.controller.translate_controller.class%')
        ->public()
        ->args([
            service('jms_translation.config_factory'),
            service('jms_translation.loader_manager'),
            service('twig'),
        ])
        ->call('setSourceLanguage', ['%jms_translation.source_language%']);

    $services->alias(TranslateController::class, 'jms_translation.controller.translate_controller')
        ->public();

    $services->set('jms_translation.controller.api_controller', '%jms_translation.controller.api_controller.class%')
        ->public()
        ->args([
            service('jms_translation.config_factory'),
            service('jms_translation.updater'),
        ]);

    $services->alias(ApiController::class, 'jms_translation.controller.api_controller')
        ->public();

    $services->set('jms_translation.updater', '%jms_translation.updater.class%')
        ->public()
        ->args([
            service('jms_translation.loader_manager'),
            service('jms_translation.extractor_manager'),
            service('logger'),
            service('jms_translation.file_writer'),
        ]);

    $services->set('jms_translation.config_factory', '%jms_translation.config_factory.class%')
        ->public();

    $services->set('jms_translation.file_source_factory', '%jms_translation.file_source_factory.class%')
        ->args([
            '%kernel.project_dir%',
            '%kernel.project_dir%',
        ]);

    $services->set('jms_translation.file_writer', '%jms_translation.file_writer.class%')
        ->private();

    // Loaders
    $services->set('jms_translation.loader.symfony_adapter', '%jms_translation.loader.symfony_adapter.class%')
        ->abstract()
        ->private();

    // public as needed by the TranslateController
    $services->set('jms_translation.loader_manager', '%jms_translation.loader_manager.class%');

    $services->set('jms_translation.loader.xliff_loader', '%jms_translation.loader.xliff_loader.class%')
        ->private()
        ->tag('jms_translation.loader', ['format' => 'xliff']);

    $services->set('jms_translation.loader.xlf_loader', '%jms_translation.loader.xliff_loader.class%')
        ->private()
        ->tag('jms_translation.loader', ['format' => 'xlf']);

    // Dumpers
    $services->set('jms_translation.dumper.php_dumper', '%jms_translation.dumper.php_dumper.class%')
        ->private()
        ->tag('jms_translation.dumper', ['format' => 'php']);

    $services->set('jms_translation.dumper.xliff_dumper', '%jms_translation.dumper.xliff_dumper.class%')
        ->private()
        ->call('setSourceLanguage', ['%jms_translation.source_language%'])
        ->call('setAddDate', ['%jms_translation.dumper.add_date%'])
        ->call('setAddReference', ['%jms_translation.dumper.add_references%'])
        ->tag('jms_translation.dumper', ['format' => 'xliff']);

    $services->set('jms_translation.dumper.xlf_dumper', '%jms_translation.dumper.xliff_dumper.class%')
        ->private()
        ->call('setSourceLanguage', ['%jms_translation.source_language%'])
        ->call('setAddDate', ['%jms_translation.dumper.add_date%'])
        ->call('setAddReference', ['%jms_translation.dumper.add_references%'])
        ->tag('jms_translation.dumper', ['format' => 'xlf']);

    $services->set('jms_translation.dumper.yaml_dumper', '%jms_translation.dumper.yaml_dumper.class%')
        ->private()
        ->tag('jms_translation.dumper', ['format' => 'yml']);

    $services->set('jms_translation.dumper.symfony_adapter', '%jms_translation.dumper.symfony_adapter.class%')
        ->abstract()
        ->private();

    // Extractors
    $services->set('jms_translation.extractor_manager', '%jms_translation.extractor_manager.class%')
        ->private()
        ->args([
            service('jms_translation.extractor.file_extractor'),
            service('logger'),
        ]);

    // File-based extractors
    $services->set('jms_translation.extractor.file_extractor', '%jms_translation.extractor.file_extractor.class%')
        ->private()
        ->args([
            service('twig'),
            service('logger'),
        ]);

    $services->set('jms_translation.extractor.file.default_php_extractor', '%jms_translation.extractor.file.default_php_extractor%')
        ->private()
        ->args([
            service('jms_translation.doc_parser'),
            service('jms_translation.file_source_factory'),
        ])
        ->tag('jms_translation.file_visitor');

    $services->set('jms_translation.extractor.file.form_extractor', '%jms_translation.extractor.file.form_extractor.class%')
        ->private()
        ->args([
            service('jms_translation.doc_parser'),
            service('jms_translation.file_source_factory'),
        ])
        ->tag('jms_translation.file_visitor');

    $services->set('jms_translation.extractor.file.translation_container_extractor', '%jms_translation.extractor.file.translation_container_extractor%')
        ->private()
        ->tag('jms_translation.file_visitor');

    $services->set('jms_translation.extractor.file.twig_extractor', '%jms_translation.extractor.file.twig_extractor%')
        ->private()
        ->args([
            service('twig'),
            service('jms_translation.file_source_factory'),
        ])
        ->tag('jms_translation.file_visitor');

    $services->set('jms_translation.extractor.file.validation_extractor', '%jms_translation.extractor.file.validation_extractor.class%')
        ->private()
        ->args([
            service('validator.mapping.class_metadata_factory'),
        ])
        ->tag('jms_translation.file_visitor');

    $services->set('jms_translation.extractor.file.authentication_message_extractor', '%jms_translation.extractor.file.authentication_message_extractor.class%')
        ->private()
        ->args([
            service('jms_translation.doc_parser'),
            service('jms_translation.file_source_factory'),
        ])
        ->tag('jms_translation.file_visitor');

    // Util
    $services->set('jms_translation.doc_parser', DocParser::class)
        ->private()
        ->call('setImports', [[
            'desc' => 'JMS\TranslationBundle\Annotation\Desc',
            'meaning' => 'JMS\TranslationBundle\Annotation\Meaning',
            'ignore' => 'JMS\TranslationBundle\Annotation\Ignore',
        ]])
        ->call('setIgnoreNotImportedAnnotations', [true]);

    $services->set('jms_translation.twig_extension', '%jms_translation.twig_extension.class%')
        ->public()
        ->args([
            service('translator'),
            '%kernel.debug%',
        ])
        ->tag('twig.extension');
};
