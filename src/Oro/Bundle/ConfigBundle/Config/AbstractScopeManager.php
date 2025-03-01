<?php

namespace Oro\Bundle\ConfigBundle\Config;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ConfigBundle\Entity\Config;
use Oro\Bundle\ConfigBundle\Event\ConfigManagerScopeIdUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The base class for configuration scope managers.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
abstract class AbstractScopeManager
{
    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var CacheProvider */
    protected $cache;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var ConfigBag */
    protected $configBag;

    /** @var array */
    protected $changedSettings = [];

    public function __construct(
        ManagerRegistry $doctrine,
        CacheProvider $cache,
        EventDispatcherInterface $eventDispatcher,
        ConfigBag $configBag
    ) {
        $this->doctrine = $doctrine;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->configBag = $configBag;
    }

    /**
     * Return config value from current scope
     *
     * @param string $name Setting name, for example "oro_user.level"
     * @param bool $full
     * @param null|int|object $scopeIdentifier
     * @param bool $skipChanges
     *
     * @return array|null|string
     */
    public function getSettingValue($name, $full = false, $scopeIdentifier = null, $skipChanges = false)
    {
        $entityId = $this->resolveIdentifier($scopeIdentifier);
        $setting = $this->getCachedSetting($entityId, $name, $skipChanges);

        $result = null;

        if ($setting === null) {
            return $result;
        }

        if ($setting[ConfigManager::VALUE_KEY] !== null
            || $setting[ConfigManager::USE_PARENT_SCOPE_VALUE_KEY] === false
        ) {
            $result = $setting[ConfigManager::VALUE_KEY];
            if ($full) {
                $result = $setting;
                $result[ConfigManager::SCOPE_KEY] = $this->getScopedEntityName();
            }
        }

        return $result;
    }

    /**
     * Get Additional Info of Config Value
     *
     * @param string $name
     * @param null|int|object $scopeIdentifier
     *
     * @return array
     */
    public function getInfo($name, $scopeIdentifier = null)
    {
        $entityId = $this->resolveIdentifier($scopeIdentifier);
        $setting = $this->getCachedSetting($entityId, $name);

        $createdAt   = null;
        $updatedAt   = null;
        $isNullValue = true;

        if (null !== $setting) {
            $isNullValue = false;
            if (array_key_exists('createdAt', $setting)) {
                $createdAt = $setting['createdAt'];
            }
            if (array_key_exists('updatedAt', $setting)) {
                $updatedAt = $setting['updatedAt'];
            }
        }

        return [$createdAt, $updatedAt, $isNullValue];
    }

    /**
     * @param int|null $entityId
     * @param string $name
     * @param bool $skipChanges
     *
     * @return array|null
     */
    protected function getCachedSetting($entityId, $name, $skipChanges = false)
    {
        $cacheKey = $this->getCacheKey($this->getScopedEntityName(), $entityId);
        [$section, $key] = explode(ConfigManager::SECTION_MODEL_SEPARATOR, $name);

        $settings = $this->cache->fetch($cacheKey);
        if (false === $settings) {
            $settings = $this->loadStoredSettings($entityId);
            $this->cache->save($cacheKey, $settings);
        }

        $keySetting = null;

        if (!empty($settings[$section][$key])) {
            $keySetting = $settings[$section][$key];
        }

        if (!$skipChanges && isset($this->changedSettings[$entityId][$name][ConfigManager::VALUE_KEY])) {
            if (null === $keySetting) {
                $keySetting = [];
            }
            $keySetting = array_merge($keySetting, $this->changedSettings[$entityId][$name]);
        }

        return $keySetting;
    }

    /**
     * Set setting value. To save changes in a database you need to call flush method
     *
     * @param string $name Setting name, for example "oro_user.level"
     * @param mixed $value Setting value
     * @param null|int|object $scopeIdentifier
     */
    public function set($name, $value, $scopeIdentifier = null)
    {
        $entityId = $this->resolveIdentifier($scopeIdentifier);

        $this->changedSettings[$entityId][$name] = [
            ConfigManager::VALUE_KEY                  => $value,
            ConfigManager::USE_PARENT_SCOPE_VALUE_KEY => false
        ];
    }

    /**
     * Reset setting value to default. To save changes in a database you need to call flush method
     *
     * @param string $name Setting name, for example "oro_user.level"
     * @param null|int|object $scopeIdentifier
     */
    public function reset($name, $scopeIdentifier = null)
    {
        $entityId = $this->resolveIdentifier($scopeIdentifier);

        $this->cache->delete($this->getCacheKey($this->getScopedEntityName(), $entityId));

        $this->changedSettings[$entityId][$name] = [
            ConfigManager::USE_PARENT_SCOPE_VALUE_KEY => true
        ];
    }

    /**
     * Removes scope settings. To save changes in a database, a flush method should be called.
     *
     * @param int|object $scopeIdentifier
     */
    public function deleteScope($scopeIdentifier): void
    {
        $entity   = $this->getScopedEntityName();
        $entityId = $this->resolveIdentifier($scopeIdentifier);

        /** @var Config $config */
        $config = $this->doctrine->getManagerForClass(Config::class)
            ->getRepository(Config::class)
            ->findByEntity($entity, $entityId);

        if ($config) {
            foreach ($config->getValues() as $value) {
                $name = $value->getSection() . ConfigManager::SECTION_MODEL_SEPARATOR . $value->getName();
                $this->changedSettings[$entityId][$name] = [
                    ConfigManager::USE_PARENT_SCOPE_VALUE_KEY => true
                ];
            }
        }

        $this->cache->delete($this->getCacheKey($entity, $entityId));
    }

    /**
     * @param null|int $scopeIdentifier
     * @return array
     */
    public function getChanges($scopeIdentifier = null)
    {
        $entityId = $this->resolveIdentifier($scopeIdentifier);
        if (array_key_exists($entityId, $this->changedSettings)) {
            return $this->changedSettings[$entityId];
        }

        return [];
    }

    /**
     * @return int[]
     */
    public function getChangedScopeIdentifiers()
    {
        return array_keys($this->changedSettings);
    }

    /**
     * Save changes made with set or reset methods in a database
     * @param null|int|object $scopeIdentifier
     */
    public function flush($scopeIdentifier = null)
    {
        $entityId = $this->resolveIdentifier($scopeIdentifier);
        if (!empty($this->changedSettings[$entityId])) {
            $this->save($this->changedSettings[$entityId], $scopeIdentifier);
            $this->changedSettings[$entityId] = [];
        }
    }

    /**
     * Save settings with fallback to global scope (default)
     *
     * @param array $settings
     * @param null|int|object $scopeIdentifier
     *
     * @return array [updated, removed]
     */
    public function save($settings, $scopeIdentifier = null): array
    {
        $entity   = $this->getScopedEntityName();
        $entityId = $this->resolveIdentifier($scopeIdentifier);

        $em = $this->doctrine->getManagerForClass(Config::class);

        /** @var Config $config */
        $config = $em
            ->getRepository(Config::class)
            ->findByEntity($entity, $entityId);
        if (null === $config) {
            $config = new Config();
            $config->setScopedEntity($entity)->setRecordId($entityId);
        }

        [$updated, $removed] = $this->calculateChangeSet($settings, $entityId);
        foreach ($removed as $name) {
            [$section, $key] = explode(ConfigManager::SECTION_MODEL_SEPARATOR, $name);
            $config->removeValue($section, $key);
        }
        foreach ($updated as $name => $value) {
            [$section, $key] = explode(ConfigManager::SECTION_MODEL_SEPARATOR, $name);

            $configValue = $config->getOrCreateValue($section, $key);
            $configValue->setValue($value);

            if (!$configValue->getId()) {
                $config->getValues()->add($configValue);
            }
        }
        if (0 === $config->getValues()->count()) {
            $em->remove($config);
        } else {
            $em->persist($config);
        }

        $em->flush();

        foreach ($settings as $name => $value) {
            unset($this->changedSettings[$entityId][$name]);
        }

        $settings = $this->normalizeSettings(SettingsConverter::convertToSettings($config));
        $this->cache->save($this->getCacheKey($entity, $entityId), $settings);

        $em->detach($config);

        return [$updated, $removed];
    }

    /**
     * Calculates and returns config change set
     * Does not modify anything, so even if you call flush after calculating you will not persist any changes
     *
     * @param array $settings
     * @param null|int|object $scopeIdentifier
     *
     * @return array [updated,              removed]
     *               [[name => value, ...], [name, ...]]
     */
    public function calculateChangeSet(array $settings, $scopeIdentifier = null)
    {
        // find new and updated
        $updated = $removed = [];
        foreach ($settings as $name => $value) {
            $entityId = $this->resolveIdentifier($scopeIdentifier);
            $currentValue = $this->getSettingValue($name, true, $entityId);
            $useCurrentScope = empty($value[ConfigManager::USE_PARENT_SCOPE_VALUE_KEY]);

            // save only if there's no default checkbox checked
            if ($useCurrentScope) {
                $updated[$name] = $value[ConfigManager::VALUE_KEY];
            }

            $valueDefined = empty($currentValue[ConfigManager::USE_PARENT_SCOPE_VALUE_KEY]);
            if ($valueDefined && !$useCurrentScope) {
                $removed[] = $name;
            }
        }

        return [$updated, $removed];
    }

    /**
     * Reload settings data
     * @param null|int|object $scopeIdentifier
     */
    public function reload($scopeIdentifier = null)
    {
        $this->resetCache();

        $entityId = $this->resolveIdentifier($scopeIdentifier);
        $cacheKey = $this->getCacheKey($this->getScopedEntityName(), $entityId);

        $settings = $this->loadStoredSettings($entityId);
        $this->cache->save($cacheKey, $settings);

        $event = new ConfigManagerScopeIdUpdateEvent();
        $this->eventDispatcher->dispatch($event, ConfigManagerScopeIdUpdateEvent::EVENT_NAME);
    }

    /**
     * @return string
     */
    abstract public function getScopedEntityName();

    /**
     * @return int
     */
    abstract public function getScopeId();

    /**
     * @param int $scopeId
     */
    public function setScopeId($scopeId)
    {
    }

    protected function dispatchScopeIdChangeEvent()
    {
        $event = new ConfigManagerScopeIdUpdateEvent();
        $this->eventDispatcher->dispatch($event, ConfigManagerScopeIdUpdateEvent::EVENT_NAME);
    }

    /**
     * @return string
     */
    public function getScopeInfo()
    {
        return '';
    }

    /**
     * @param object $entity
     * @return int|null
     */
    public function getScopeIdFromEntity($entity)
    {
        if ($this->isSupportedScopeEntity($entity)) {
            return $this->getScopeEntityIdValue($entity);
        }

        // Must be null because we should not return any scope id if the entity is not supported as a scope entity.
        return null;
    }

    /**
     * Find scope id by provided entity object
     *
     * @param object $entity
     */
    public function setScopeIdFromEntity($entity)
    {
        $scopeId = $this->getScopeIdFromEntity($entity);

        if ($scopeId) {
            $this->setScopeId($scopeId);
        }
    }

    /**
     * @param object $entity
     * @return bool
     */
    protected function isSupportedScopeEntity($entity)
    {
        return false;
    }

    /**
     * @param object $entity
     * @return mixed
     */
    protected function getScopeEntityIdValue($entity)
    {
        return null;
    }

    /**
     * Loads settings from a database
     *
     * @param int $entityId
     *
     * @return array
     */
    protected function loadStoredSettings($entityId)
    {
        $config = $this->doctrine->getManagerForClass(Config::class)
            ->getRepository(Config::class)
            ->findByEntity($this->getScopedEntityName(), $entityId);

        if (null === $config) {
            return [];
        }

        return $this->normalizeSettings(SettingsConverter::convertToSettings($config));
    }

    protected function normalizeSettings(array $settings): array
    {
        $configFields = $this->configBag->getConfig()['fields'];
        foreach ($settings as $section => $sectionSettings) {
            foreach ($sectionSettings as $key => $setting) {
                $settingPath = sprintf('%s.%s', $section, $key);
                if (empty($configFields[$settingPath])
                    || $setting['value'] === null
                    || empty($configFields[$settingPath]['data_type'])
                ) {
                    continue;
                }

                $normalizedValue = $this->normalizeSettingValue(
                    $configFields[$settingPath]['data_type'],
                    $setting['value']
                );

                if ($normalizedValue !== null) {
                    $settings[$section][$key]['value'] = $normalizedValue;
                }
            }
        }

        return $settings;
    }

    /**
     * @param string $dataType
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeSettingValue(string $dataType, $value)
    {
        switch ($dataType) {
            case 'integer':
                return (integer) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return (boolean) $value;
            default:
                return null;
        }
    }

    /**
     * @param string $entity
     * @param int|null $entityId
     *
     * @return string
     */
    protected function getCacheKey($entity, $entityId)
    {
        return $entity . '_' . $entityId;
    }

    /**
     * @param object|int|null $identifier
     *
     * @return int|null
     */
    public function resolveIdentifier($identifier)
    {
        if (\is_object($identifier)) {
            return $this->getScopeIdFromEntity($identifier);
        }

        return $identifier ?? $this->getScopeId();
    }

    protected function resetCache(): void
    {
        $this->cache->flushAll();
    }
}
