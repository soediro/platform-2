<?php

namespace Oro\Bundle\LayoutBundle\Layout\Extension\Generator;

use Oro\Component\Layout\Loader\Generator\ConfigLayoutUpdateGenerator;
use Oro\Component\Layout\Loader\Generator\ConfigLayoutUpdateGeneratorExtensionInterface;
use Oro\Component\Layout\Loader\Generator\GeneratorData;
use Oro\Component\Layout\Loader\Visitor\VisitorCollection;
use Symfony\Component\Finder\Finder;

/**
 * Extension for using relative paths in setBlockTheme and setFormTheme
 */
class ThemesRelativePathGeneratorExtension implements ConfigLayoutUpdateGeneratorExtensionInterface
{
    const THEMES_KEY = 'themes';
    const ACTION_SET_FORM_THEME = '@setFormTheme';
    const ACTION_SET_BLOCK_THEME = '@setBlockTheme';

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(GeneratorData $data, VisitorCollection $collection)
    {
        $source = $data->getSource();
        $file = $data->getFilename();

        $actionsKey = ConfigLayoutUpdateGenerator::NODE_ACTIONS;
        if (is_array($source) && $file && array_key_exists($actionsKey, $source)) {
            $source[$actionsKey] = array_map(function ($actionDefinition) use ($file) {
                $actionName = is_array($actionDefinition) ? key($actionDefinition) : '';
                if (in_array($actionName, [self::ACTION_SET_BLOCK_THEME, self::ACTION_SET_FORM_THEME], true)
                    && array_key_exists(self::THEMES_KEY, $actionDefinition[$actionName])
                ) {
                    $themes = array_map(function ($theme) use ($file) {
                        return $this->prepareThemePath($theme, $file);
                    }, (array)$actionDefinition[$actionName][self::THEMES_KEY]);
                    if (count($themes) === 1) {
                        $themes = reset($themes);
                    }
                    $actionDefinition[$actionName][self::THEMES_KEY] = $themes;
                }
                return $actionDefinition;
            }, $source[$actionsKey]);
            $data->setSource($source);
        }
    }

    /**
     * @param string $theme
     * @param string $file
     * @return string
     */
    protected function prepareThemePath($theme, $file)
    {
        if ($theme && strpos($theme, ':') === false && strpos($theme, '/') !== 0 && strpos($theme, '@') !== 0) {
            $directoryPath = \dirname($file);
            $absolutePath = realpath($directoryPath.'/'.$theme);
            if ($absolutePath) {
                $theme = $this->getNamespacedThemeName($directoryPath, $absolutePath) ?? $absolutePath;
            }
        }
        return $theme;
    }

    /**
     * Returns namespaced theme name (e.g. "@OroLayout/folder1/folder2/layout.html.twig")
     * if we can find a Bundle name or null otherwise.
     */
    private function getNamespacedThemeName(string $directoryPath, string $absolutePath): ?string
    {
        $bundleClassData = $this->findBundleClass($directoryPath);
        if (!$bundleClassData) {
            return null;
        }

        [$bundleClassFolder, $namespace] = $bundleClassData;

        return '@' . str_replace([$bundleClassFolder, '/Resources/views'], [$namespace, ''], $absolutePath);
    }

    /**
     * Recursively find a Bundle class
     */
    private function findBundleClass(string $directoryPath): ?array
    {
        $directoryPath = realpath($directoryPath);
        if (!is_dir($directoryPath)) {
            return null;
        }

        $finder = Finder::create()->files()->name('*Bundle.php')->in($directoryPath)->depth(0);
        if (!$finder->hasResults()) {
            return $directoryPath !== $this->projectDir
                ? $this->findBundleClass($directoryPath . DIRECTORY_SEPARATOR . '..')
                : null;
        }

        $iterator = $finder->getIterator();
        $iterator->rewind();

        $bundleClass = $iterator->current();

        return [
            \dirname($bundleClass->getRealPath()),
            str_replace('Bundle', '', $bundleClass->getFilenameWithoutExtension())
        ];
    }
}
