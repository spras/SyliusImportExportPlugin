<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\DependencyInjection\Compiler;

use FriendsOfSylius\SyliusImportExportPlugin\Exporter\ExporterRegistry;
use FriendsOfSylius\SyliusImportExportPlugin\Listener\ExportButtonGridListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterExporterPass implements CompilerPassInterface
{
    /**
     * @var array
     */
    private $typesAndFormats = [];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $serviceId = 'sylius.exporters_registry';
        if ($container->has($serviceId) == false) {
            return;
        }

        $exportersRegistry = $container->findDefinition($serviceId);

        foreach ($container->findTaggedServiceIds('sylius.exporter') as $id => $attributes) {
            if (!isset($attributes[0]['type'])) {
                throw new \InvalidArgumentException('Tagged exporter ' . $id . ' needs to have a type');
            }
            if (!isset($attributes[0]['format'])) {
                throw new \InvalidArgumentException('Tagged exporter ' . $id . ' needs to have a format');
            }
            $type = $attributes[0]['type'];
            $format = $attributes[0]['format'];

            $name = ExporterRegistry::buildServiceName($type, $format);
            $exportersRegistry->addMethodCall('register', [$name, new Reference($id)]);

            if ($container->getParameter('sylius.exporter.web_ui')) {
                $this->registerTypeAndFormat($type, $format);
            }
        }

        if ($container->getParameter('sylius.exporter.web_ui') && !empty($this->typesAndFormats)) {
            $this->registerEventListenersForExportButton($container);
        }
    }

    private function registerTypeAndFormat(string $type, string $format): void
    {
        if (!isset($this->typesAndFormats[$type])) {
            $this->typesAndFormats[$type] = [];
        }

        if (!isset($this->typesAndFormats[$type][$format])) {
            $this->typesAndFormats[$type][] = $format;
        }
    }

    private function registerEventListenersForExportButton(ContainerBuilder $container): void
    {
        foreach ($this->typesAndFormats as $type => $formats) {
            $this->registerSingleEventListenerForExportButton($container, $type, $formats);
        }
    }

    /**
     * @param string[] $formats
     */
    private function registerSingleEventListenerForExportButton(ContainerBuilder $container, string $type, array $formats): void
    {
        $eventHookName = ExporterRegistry::buildGridButtonsEventHookName($type, $formats) . '_export';

        if ($container->has($eventHookName)) {
            return;
        }

        $container
            ->register(
                $eventHookName,
                ExportButtonGridListener::class
            )
            ->setAutowired(false)
            ->addArgument($type)
            ->addArgument($formats)
            ->addMethodCall('setRequest', [new Reference('request_stack')])
            ->addTag(
                'kernel.event_listener',
                [
                    'event' => $this->getEventName($type),
                    'method' => 'onSyliusGridAdmin',
                ]
            );
    }

    private function getEventName(string $type): string
    {
        if (strpos($type, '.') !== false) {
            $type = substr($type, strpos($type, '.') + 1);
        }

        return 'sylius.grid.admin_' . $type;
    }
}
