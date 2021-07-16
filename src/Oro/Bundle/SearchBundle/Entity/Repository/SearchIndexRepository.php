<?php

namespace Oro\Bundle\SearchBundle\Entity\Repository;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\SearchBundle\Engine\Orm\BaseDriver;
use Oro\Bundle\SearchBundle\Engine\Orm\DBALPersisterInterface;
use Oro\Bundle\SearchBundle\Entity\AbstractItem;
use Oro\Bundle\SearchBundle\Entity\IndexText;
use Oro\Bundle\SearchBundle\Entity\Item;
use Oro\Bundle\SearchBundle\Query\Query;

/**
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SearchIndexRepository extends EntityRepository implements DBALPersisterInterface
{
    /**
     * @var \Oro\Bundle\SearchBundle\Engine\Orm\BaseDriver
     */
    protected $driverRepo;

    /**
     * @var array
     */
    protected $drivers;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var ObjectManager[]
     */
    protected $managers = [];

    /**
     * Search query to index
     *
     * @param Query $query
     *
     * @return array
     */
    public function search(Query $query)
    {
        return $this->getDriverRepo()->search($query);
    }

    /**
     * @param Query $query
     *
     * @return array
     * [
     *      <entityFQCN> => <documentCount>
     * ]
     */
    public function getDocumentsCountGroupByEntityFQCN(Query $query): array
    {
        return $this->getDriverRepo()->getDocumentsCountGroupByEntityFQCN($query);
    }

    /**
     * Get count of records without limit parameters in query
     *
     * @param Query $query
     *
     * @return integer
     */
    public function getRecordsCount(Query $query)
    {
        return $this->getDriverRepo()->getRecordsCount($query);
    }

    /**
     * Get aggregated data calculated based on requirements from query
     *
     * @param Query $query
     * @return array
     */
    public function getAggregatedData(Query $query)
    {
        return $this->getDriverRepo()->getAggregatedData($query);
    }

    /**
     * Set array with additional drivers
     *
     * @param array $drivers
     */
    public function setDriversClasses($drivers)
    {
        foreach ($drivers as $driver) {
            if (!is_a($driver, BaseDriver::class, true)) {
                throw new \InvalidArgumentException('Wrong driver class passed, please check configuration');
            }
        }
        $this->drivers = $drivers;
    }

    /**
     * Truncate all search index tables
     */
    public function truncateIndex()
    {
        $this->getDriverRepo()->truncateIndex();
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(AbstractItem $item)
    {
        $this->getDriverRepo()->writeItem($item);
    }

    /**
     * {@inheritdoc}
     */
    public function flushWrites()
    {
        $this->getDriverRepo()->flushWrites();
    }

    /**
     * Get driver repository
     *
     * @return \Oro\Bundle\SearchBundle\Engine\Orm\BaseDriver
     */
    protected function getDriverRepo()
    {
        if (!is_object($this->driverRepo)) {
            $config = $this->getEntityManager()->getConnection()->getParams();
            $className = $this->drivers[$config['driver']];

            $this->driverRepo = new $className($config['driver']);
            $this->driverRepo->initRepo($this->_em, $this->_class);
        }

        return $this->driverRepo;
    }

    /**
     * Returns array of search items in following format:
     * array(
     *      '<entityClass>' => array(
     *          <entityIdentifier> => <instance of OroSearchBundle:Item>,
     *          ...
     *      ),
     *      ...
     * )
     *
     * @param array $entities
     * @return array
     */
    public function getItemsForEntities(array $entities)
    {
        if (!$entities) {
            return [];
        }

        $identifiers = [];
        foreach ($entities as $entity) {
            $class = ClassUtils::getClass($entity);
            $ids   = $this->getManager($class)->getClassMetadata($class)->getIdentifierValues($entity);

            if (!empty($ids)) {
                $identifiers[$class][] = current($ids);
            }
        }

        if (!$identifiers) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('item');
        $parameterCounter = 0;

        foreach ($identifiers as $class => $entityIds) {
            $parameterClassName = 'class_' . $parameterCounter;
            $parameterIds = 'entityIds_' . $parameterCounter;
            $parameterCounter++;

            $entityCondition = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq('item.entity', ':'.$parameterClassName),
                $queryBuilder->expr()->in('item.recordId', ':'.$parameterIds)
            );

            $queryBuilder->orWhere($entityCondition)
                ->setParameter($parameterClassName, $class)
                ->setParameter($parameterIds, $entityIds);
        }

        /** @var Item[] $items */
        $items = $queryBuilder->getQuery()->getResult();

        $groupedItems = [];
        foreach ($items as $item) {
            $class = $item->getEntity();
            $id    = $item->getRecordId();
            $groupedItems[$class][$id] = $item;
        }

        return $groupedItems;
    }

    /**
     * @param string $entityClass
     * @return ObjectManager
     */
    protected function getManager($entityClass)
    {
        if (array_key_exists($entityClass, $this->managers)) {
            return $this->managers[$entityClass];
        }

        $this->managers[$entityClass] = $this->registry->getManagerForClass($entityClass);

        if (null === $this->managers[$entityClass]) {
            $this->managers[$entityClass] = $this->registry->getManager();
        }

        return $this->managers[$entityClass];
    }

    /**
     * @param ManagerRegistry $registry
     */
    public function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array $entityIds
     * @param string $entityClass
     * @param string|null $entityAlias
     */
    public function removeEntities(array $entityIds, $entityClass, $entityAlias = null)
    {
        if (empty($entityIds)) {
            return;
        }

        $queryBuilder = $this->createQueryBuilder('item');
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in('item.recordId', ':entityIds'))
            ->andWhere($queryBuilder->expr()->eq('item.entity', ':entityClass'))
            ->setParameter('entityClass', $entityClass)
            ->setParameter('entityIds', $entityIds);

        if ($entityAlias) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->eq('item.alias', ':entityAlias'))
                ->setParameter('entityAlias', $entityAlias);
        }

        $this->deleteFromIndexTextTable(clone $queryBuilder);

        $queryBuilder->delete()->getQuery()->execute();
    }

    /**
     * We need to remove data manually as fulltext index in MySQL is only available in MyISAM engine which doesn't
     * support cascade deletes by a foreign key.
     */
    private function deleteFromIndexTextTable(QueryBuilder $subQueryBuilder)
    {
        $subQueryDQL = $subQueryBuilder->select('item.id')->getDQL();

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder
            ->from(IndexText::class, 'indexText')
            ->delete()
            ->where($queryBuilder->expr()->in('indexText.item', $subQueryDQL))
            ->setParameters($subQueryBuilder->getParameters())
            ->getQuery()
            ->execute();
    }
}
