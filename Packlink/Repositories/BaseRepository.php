<?php

namespace Packlink\Repositories;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Logeecom\Infrastructure\ORM\Entity;
use Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Logeecom\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryCondition;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\Utility\IndexHelper;
use Packlink\Models\BaseEntity;
use Packlink\Models\PacklinkEntity;
use function count;

class BaseRepository implements RepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * @var string
     */
    protected static $doctrineModel = PacklinkEntity::class;
    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * BaseRepository constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->entityManager = Shopware()->Container()->get('models');
    }

    /**
     * Returns full class name.
     *
     * @return string Full class name.
     */
    public static function getClassName()
    {
        return static::THIS_CLASS_NAME;
    }

    /**
     * Sets repository entity.
     *
     * @param string $entityClass Repository entity class.
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * Executes select query.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return Entity[] A list of found entities ot empty array.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function select(QueryFilter $filter = null)
    {
        $query = $this->getBaseDoctrineQuery($filter);

        return $this->getResult($query);
    }

    /**
     * Executes select query and returns first result.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return Entity | null First found entity or NULL.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function selectOne(QueryFilter $filter = null)
    {
        $query = $this->getBaseDoctrineQuery($filter);
        $query->setMaxResults(1);

        $result = $this->getResult($query);

        return !empty($result[0]) ? $result[0] : null;
    }

    /**
     * Executes insert query and returns ID of created entity. Entity will be updated with new ID.
     *
     * @param Entity $entity Entity to be saved.
     *
     * @return int Identifier of saved entity.
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     */
    public function save(Entity $entity)
    {
        $doctrineEntity = new static::$doctrineModel;
        $id = $this->persistEntity($entity, $doctrineEntity);
        $entity->setId($id);

        return $id;
    }

    /**
     * Executes update query and returns success flag.
     *
     * @param Entity $entity Entity to be updated.
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE.
     */
    public function update(Entity $entity)
    {
        $result = true;

        try {
            /** @var BaseEntity $doctrineEntity */
            $doctrineEntity = $this->entityManager->find(static::$doctrineModel, $entity->getId());
            if ($doctrineEntity) {
                $this->persistEntity($entity, $doctrineEntity);
            } else {
                $result = false;
            }
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Executes delete query and returns success flag.
     *
     * @param Entity $entity Entity to be deleted.
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE.
     */
    public function delete(Entity $entity)
    {
        $result = true;

        try {
            $persistentEntity = $this->entityManager->find(static::$doctrineModel, $entity->getId());
            if ($persistentEntity) {
                $this->entityManager->remove($persistentEntity);
                $this->entityManager->flush();
            }
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Counts records that match filter criteria.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return int Number of records that match filter criteria.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function count(QueryFilter $filter = null)
    {
        $query = $this->getBaseDoctrineQuery($filter, true);

        return (int)$query->getQuery()->getSingleScalarResult();
    }

    /**
     * Builds condition groups (each group is chained with OR internally, and with AND externally) based on query
     * filter.
     *
     * @param QueryFilter $filter Query filter object.
     * @param array $fieldIndexMap Map of property indexes.
     *
     * @return array Array of condition groups..
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    protected function buildConditionGroups(QueryFilter $filter, array $fieldIndexMap)
    {
        $groups = [];
        $counter = 0;
        $fieldIndexMap['id'] = 0;
        foreach ($filter->getConditions() as $condition) {
            if (!empty($groups[$counter]) && $condition->getChainOperator() === 'OR') {
                $counter++;
            }

            // Only index columns can be filtered.
            if (!array_key_exists($condition->getColumn(), $fieldIndexMap)) {
                throw new QueryFilterInvalidParamException("Field [{$condition->getColumn()}] is not indexed.");
            }

            $groups[$counter][] = $condition;
        }

        return $groups;
    }

    /**
     * Retrieves doctrine query.
     *
     * @param \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter|null $filter
     *
     * @param bool $isCount
     *
     * @return \Doctrine\ORM\QueryBuilder
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    protected function getBaseDoctrineQuery(QueryFilter $filter = null, $isCount = false)
    {
        /** @var Entity $entity */
        $entity = new $this->entityClass;
        $type = $entity->getConfig()->getType();
        $indexMap = IndexHelper::mapFieldsToIndexes($entity);

        $query = $this->entityManager->createQueryBuilder();
        $alias = 'p';
        $baseSelect = $isCount ? "count($alias.id)" : $alias;
        $query->select($baseSelect)
            ->from(static::$doctrineModel, $alias)
            ->where("$alias.type = '$type'");

        $groups = $filter ? $this->buildConditionGroups($filter, $indexMap) : [];
        $queryParts = $this->getQueryParts($groups, $indexMap, $alias);

        $where = $this->generateWhereStatement($queryParts);
        if (!empty($where)) {
            $query->andWhere($where);
        }

        if ($filter) {
            $this->setLimit($filter, $query);
            $this->setOrderBy($filter, $indexMap, $alias, $query);
        }

        return $query;
    }

    /**
     * Retrieves group query parts.
     *
     * @param array $conditionGroups
     * @param array $indexMap
     * @param string $alias
     *
     * @return array
     */
    protected function getQueryParts(array $conditionGroups, array $indexMap, $alias)
    {
        $parts = [];

        foreach ($conditionGroups as $group) {
            $subPart = [];

            foreach ($group as $condition) {
                $subPart[] = $this->getQueryPart($condition, $indexMap, $alias);
            }

            if (!empty($subPart)) {
                $parts[] = $subPart;
            }
        }

        return $parts;
    }

    /**
     * Retrieves query part.
     *
     * @param \Logeecom\Infrastructure\ORM\QueryFilter\QueryCondition $condition
     * @param array $indexMap
     * @param string $alias
     *
     * @return string
     */
    protected function getQueryPart(QueryCondition $condition, array $indexMap, $alias)
    {
        $column = $condition->getColumn();

        if ($column === 'id') {
            return "$alias.id=" . $condition->getValue();
        }

        $part = "$alias.index_" . $indexMap[$column] . ' ' . $condition->getOperator();
        if (!in_array($condition->getOperator(), array(Operators::NULL, Operators::NOT_NULL), true)) {
            if (in_array($condition->getOperator(), array(Operators::NOT_IN, Operators::IN), true)) {
                $part .= $this->getInOperatorValues($condition);
            } else {
                $part .= " '" . IndexHelper::castFieldValue($condition->getValue(), $condition->getValueType()) . "'";
            }
        }

        return $part;
    }

    /**
     * Handles values for the IN and NOT IN operators,
     *
     * @param QueryCondition $condition
     *
     * @return string
     */
    protected function getInOperatorValues(QueryCondition $condition)
    {
        $values = array_map(
            function ($item) {
                if (is_string($item)) {
                    return "'$item'";
                }

                return "'" . IndexHelper::castFieldValue($item, is_int($item) ? 'integer' : 'double') . "'";
            },
            $condition->getValue()
        );

        return '(' . implode(',', $values) . ')';
    }

    /**
     * Retrieves query result.
     *
     * @param \Doctrine\ORM\QueryBuilder $builder
     *
     * @return Entity[]
     */
    protected function getResult(QueryBuilder $builder)
    {
        $doctrineEntities = $builder->getQuery()->getResult();

        $result = [];

        /** @var BaseEntity $doctrineEntity */
        foreach ($doctrineEntities as $doctrineEntity) {
            $entity = $this->unserializeEntity($doctrineEntity->getData());
            if ($entity) {
                $entity->setId($doctrineEntity->getId());
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * Unserializes ORM entity.
     *
     * @param string $data
     *
     * @return \Logeecom\Infrastructure\ORM\Entity
     */
    protected function unserializeEntity($data)
    {
        $jsonEntity = json_decode($data, true);
        if (array_key_exists('class_name', $jsonEntity)) {
            $entity = new $jsonEntity['class_name'];
        } else {
            $entity = new $this->entityClass;
        }

        /** @var Entity $entity */
        $entity->inflate($jsonEntity);

        return $entity;
    }

    /**
     * Persists entity.
     *
     * @param \Logeecom\Infrastructure\ORM\Entity $entity
     * @param \Packlink\Models\BaseEntity $persistedEntity
     *
     * @return int
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function persistEntity(Entity $entity, BaseEntity $persistedEntity)
    {
        $persistedEntity->setType($entity->getConfig()->getType());

        $indexValueMap = IndexHelper::transformFieldsToIndexes($entity);

        foreach ($indexValueMap as $index => $value) {
            $setterName = "setIndex_{$index}";
            $persistedEntity->$setterName($value);
        }

        $persistedEntity->setData(json_encode($entity->toArray()));

        $this->entityManager->persist($persistedEntity);
        $this->entityManager->flush($persistedEntity);

        return $persistedEntity->getId();
    }

    /**
     * Generates where statement.
     *
     * @param array $queryParts
     *
     * @return string
     */
    protected function generateWhereStatement(array $queryParts)
    {
        $where = '';

        foreach ($queryParts as $index => $part) {
            $subWhere = '';

            if ($index > 0) {
                $subWhere .= ' OR ';
            }

            $subWhere .= $part[0];
            $count = count($part);
            for ($i = 1; $i < $count; $i++) {
                $subWhere .= ' AND ' . $part[$i];
            }

            $where .= $subWhere;
        }

        return $where;
    }

    /**
     * Sets limit.
     *
     * @param \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter $filter
     * @param \Doctrine\ORM\QueryBuilder $query
     */
    protected function setLimit(QueryFilter $filter, QueryBuilder $query)
    {
        if ($filter->getLimit()) {
            $query->setMaxResults($filter->getLimit());
        }
    }

    /**
     * Sets order by.
     *
     * @param \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter $filter
     * @param array $indexMap
     * @param $alias
     * @param \Doctrine\ORM\QueryBuilder $query
     */
    protected function setOrderBy(QueryFilter $filter, array $indexMap, $alias, QueryBuilder $query)
    {
        if ($filter->getOrderByColumn()) {
            $orderByColumn = $filter->getOrderByColumn();

            if ($orderByColumn === 'id' || !empty($indexMap[$orderByColumn])) {
                $columnName = $orderByColumn === 'id'
                    ? "$alias.id" : "$alias.index_" . $indexMap[$orderByColumn];
                $query->orderBy($columnName, $filter->getOrderDirection());
            }
        }
    }
}
