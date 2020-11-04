<?php

namespace Packlink\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use Packlink\Tests\TestComponents\BaseQueueItemRepositoryTestAdapter;
use PHPUnit\Framework\TestCase;

class QueueItemRepositoryWrapperTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'Packlink' => []
    ];
    /**
     * @var BaseQueueItemRepositoryTestAdapter
     */
    protected $baseTest;

    /**
     * QueueItemRepositoryWrapperTest constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct(...func_get_args());
        $this->baseTest = new BaseQueueItemRepositoryTestAdapter(...func_get_args());
        $entityManager = Shopware()->Container()->get('models');
        $this->baseTest->setEntityManager($entityManager);
    }

    /**
     * Proxies method to base test.
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        if (is_callable([$this->baseTest, $name])) {
            $this->baseTest->$name(...$arguments);
        }
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testRegisteredRepositories()
    {
        $this->baseTest->testRegisteredRepositories();
    }

    /**
     * @depends testRegisteredRepositories
     *
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testQueueItemMassInsert()
    {
        $this->baseTest->testQueueItemMassInsert();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testUpdate()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testUpdate();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testQueryAllQueueItems()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testQueryAllQueueItems();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testQueryWithFiltersString()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testQueryWithFiltersString();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testQueryWithFiltersInt()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testQueryWithFiltersInt();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testQueryWithFiltersAndSort()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testQueryWithFiltersAndSort();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testQueryWithFiltersAndLimit()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testQueryWithFiltersAndLimit();
    }

    /**
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testFindOldestQueuedItems()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testFindOldestQueuedItems();
    }

    /**
     * @expectedException \Packlink\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException
     *
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException
     */
    public function testSaveWithCondition()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testSaveWithCondition();
    }

    /**
     * @expectedException \Packlink\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException
     *
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException
     */
    public function testSaveWithConditionWithNull()
    {
        $this->baseTest->testQueueItemMassInsert();

        $this->baseTest->testSaveWithConditionWithNull();
    }

    /**
     * @expectedException \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     *
     * @throws \Packlink\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function testInvalidQueryFilter()
    {
        $this->baseTest->testInvalidQueryFilter();
    }

    /**
     * @inheritDoc
     *
     * @throws \Packlink\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException
     */
    protected function setUp()
    {
        $this->baseTest->setUp();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown()
    {
        $this->baseTest->tearDown();
    }
}
