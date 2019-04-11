<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace StateMachine\Test\TestCase\Business\Business;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DateTime;
use StateMachine\Business\StateMachineFacade;
use StateMachine\Business\StateMachineFacadeInterface;
use StateMachine\Dependency\CommandPluginInterface;
use StateMachine\Dependency\ConditionPluginInterface;
use StateMachine\Dependency\StateMachineHandlerInterface;
use StateMachine\Model\Table\StateMachineItemStateHistoryTable;
use StateMachine\Model\Table\StateMachineItemStatesTable;
use StateMachine\Model\Table\StateMachineProcessesTable;
use StateMachine\Model\Table\StateMachineTimeoutsTable;
use StateMachine\Transfer\StateMachineItemTransfer;
use StateMachine\Transfer\StateMachineProcessTransfer;

class StateMachineFacadeTest extends TestCase
{
    protected const TESTING_SM = 'TestingSm';
    protected const TEST_PROCESS_NAME = 'TestProcess';
    protected const TEST_PROCESS_WITH_LOOP_NAME = 'TestProcessWithLoop';

    /**
     * @var \StateMachine\Model\Table\StateMachineItemStateHistoryTable
     */
    protected $StateMachineItemStateHistory;

    /**
     * @var \StateMachine\Model\Table\StateMachineProcessesTable
     */
    protected $StateMachineProcesses;

    /**
     * @var \StateMachine\Model\Table\StateMachineItemStatesTable
     */
    protected $StateMachineItemStates;

    /**
     * @var \StateMachine\Model\Table\StateMachineTimeoutsTable
     */
    protected $StateMachineTimeouts;

    /**
     * @var array
     */
    public $fixtures = [
        'plugin.StateMachine.StateMachineItemStateHistory',
        'plugin.StateMachine.StateMachineProcesses',
        'plugin.StateMachine.StateMachineItemStates',
        'plugin.StateMachine.StateMachineTimeouts',
        'plugin.StateMachine.StateMachineLocks',
        'plugin.StateMachine.StateMachineTransitionLogs',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('StateMachineItemStateHistory') ? [] : ['className' => StateMachineItemStateHistoryTable::class];
        $this->StateMachineItemStateHistory = TableRegistry::getTableLocator()->get('StateMachineItemStateHistory', $config);

        $config = TableRegistry::getTableLocator()->exists('StateMachineProcesses') ? [] : ['className' => StateMachineProcessesTable::class];
        $this->StateMachineProcesses = TableRegistry::getTableLocator()->get('StateMachineProcesses', $config);

        $config = TableRegistry::getTableLocator()->exists('StateMachineItemStates') ? [] : ['className' => StateMachineItemStatesTable::class];
        $this->StateMachineItemStates = TableRegistry::getTableLocator()->get('StateMachineItemStates', $config);

        $config = TableRegistry::getTableLocator()->exists('StateMachineTimeouts') ? [] : ['className' => StateMachineTimeoutsTable::class];
        $this->StateMachineTimeouts = TableRegistry::getTableLocator()->get('StateMachineTimeouts', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->StateMachineItemStateHistory, $this->StateMachineProcesses, $this->StateMachineItemStates, $this->StateMachineTimeouts);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testTriggerForNewStateMachineItemWhenInitialProcessIsSuccessShouldNotifyHandlerStateChange(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $triggerResult = $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineProcessEntity = $this->StateMachineProcesses
            ->find()
            ->where([
                'name' => $processName,
                'state_machine' => static::TESTING_SM
            ])
            ->first();

        $stateMachineItemStateEntity = $this->StateMachineItemStates
            ->find()
            ->matching($this->StateMachineProcesses->getAlias())
            ->where([
                $this->StateMachineItemStates->aliasField('state_machine_process_id') => $stateMachineProcessEntity->id,
                $this->StateMachineItemStates->aliasField('name') => $stateMachineHandler->getInitialStateForProcess($processName),
            ])
            ->first();


        $this->assertNotEmpty($stateMachineItemStateEntity);
        $this->assertEquals(3, $triggerResult);
        $this->assertEquals($identifier, $stateMachineItemTransfer->getIdentifier());
        $this->assertEquals('order exported', $stateMachineItemTransfer->getStateName());
        $this->assertEquals($processName, $stateMachineItemTransfer->getProcessName());
    }

    /**
     * @return void
     */
    public function testTriggerEventForItemWithManualEventShouldMoveToNextStateWithManualEvent(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $triggerResult = $stateMachineFacade->triggerEvent('ship order', $stateMachineItemTransfer);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $this->assertEquals(2, $triggerResult);
        $this->assertEquals('waiting for payment', $stateMachineItemTransfer->getStateName());
        $this->assertEquals($processName, $stateMachineItemTransfer->getProcessName());
        $this->assertEquals($identifier, $stateMachineItemTransfer->getIdentifier());
    }

    /**
     * @return void
     */
    public function testGetProcessesShouldReturnListOfProcessesAddedToHandler(): void
    {
        $processName = static::TEST_PROCESS_NAME;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $processList = $stateMachineFacade->getProcesses(static::TESTING_SM);

        $this->assertCount(1, $processList);

        /** @var \StateMachine\Transfer\StateMachineProcessTransfer $process */
        $processTransfer = array_pop($processList);
        $this->assertEquals($processName, $processTransfer->getProcessName());
    }

    /**
     * @return void
     */
    public function testGetStateMachineProcessIdShouldReturnIdStoredInPersistence(): void
    {
        $processName = static::TEST_PROCESS_NAME;

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $processId = $stateMachineFacade->getStateMachineProcessId($stateMachineProcessTransfer);

        $stateMachineProcessEntity = $this->StateMachineProcesses
            ->find()
            ->where([
                'name' => $processName,
                'state_machine' => static::TESTING_SM,
            ])
            ->first();

        $this->assertEquals($stateMachineProcessEntity->id, $processId);
    }

    /**
     * @return void
     */
    public function testGetManualEventsForStateMachineItemShouldReturnAllManualEventsForProvidedState(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $manualEvents = $stateMachineFacade->getManualEventsForStateMachineItem($stateMachineItemTransfer);

        $this->assertEquals('order exported', $stateMachineItemTransfer->getStateName());
        $this->assertCount(2, $manualEvents);

        $manualEvent = array_pop($manualEvents);
        $this->assertEquals('check with condition', $manualEvent);

        $manualEvent = array_pop($manualEvents);
        $this->assertEquals('ship order', $manualEvent);
    }

    /**
     * @return void
     */
    public function testGetManualEventForStateMachineItemsShouldReturnAllEventsForProvidedStates(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $firstItemIdentifier = 1985;
        $secondItemIdentifier = 1988;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineItems = [];
        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $firstItemIdentifier);
        $stateMachineItems[$firstItemIdentifier] = $stateMachineHandler->getItemStateUpdated();

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $secondItemIdentifier);
        $stateMachineItems[$secondItemIdentifier] = $stateMachineHandler->getItemStateUpdated();

        $stateMachineFacade->triggerEvent('ship order', $stateMachineItems[$secondItemIdentifier]);

        $manualEvents = $stateMachineFacade->getManualEventsForStateMachineItems($stateMachineItems);

        $this->assertCount(2, $manualEvents);

        $firstItemManualEvents = $manualEvents[$firstItemIdentifier];
        $secondItemManualEvents = $manualEvents[$secondItemIdentifier];

        $manualEvent = array_pop($firstItemManualEvents);
        $this->assertEquals('check with condition', $manualEvent);

        $manualEvent = array_pop($firstItemManualEvents);
        $this->assertEquals('ship order', $manualEvent);

        $manualEvent = array_pop($secondItemManualEvents);
        $this->assertEquals('payment received', $manualEvent);
    }

    /**
     * @return void
     */
    public function testGetProcessedStateMachineItemsShouldReturnItemsByProvidedStateIdsStoredInPersistence(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $firstItemIdentifier = 1985;
        $secondItemIdentifier = 1988;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        /** @var \StateMachine\Transfer\StateMachineItemTransfer[] $stateMachineItems */
        $stateMachineItems = [];
        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $firstItemIdentifier);
        $stateMachineItems[] = $stateMachineHandler->getItemStateUpdated();

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $secondItemIdentifier);
        $stateMachineItems[] = $stateMachineHandler->getItemStateUpdated();

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        /** @var \StateMachine\Transfer\StateMachineItemTransfer[] $updatedStateMachineItems */
        $updatedStateMachineItems = $stateMachineFacade->getProcessedStateMachineItems($stateMachineItems);

        $this->assertCount(2, $updatedStateMachineItems);

        $firstUpdatedStateMachineItemTransfer = $updatedStateMachineItems[0];
        $firstBeforeUpdateStateMachineItemTransfer = $stateMachineItems[0];
        $this->assertEquals(
            $firstUpdatedStateMachineItemTransfer->getIdItemState(),
            $firstBeforeUpdateStateMachineItemTransfer->getIdItemState()
        );
        $this->assertEquals(
            $firstUpdatedStateMachineItemTransfer->getProcessName(),
            $firstBeforeUpdateStateMachineItemTransfer->getProcessName()
        );
        $this->assertEquals(
            $firstUpdatedStateMachineItemTransfer->getIdStateMachineProcess(),
            $firstBeforeUpdateStateMachineItemTransfer->getIdStateMachineProcess()
        );
        $this->assertEquals(
            $firstUpdatedStateMachineItemTransfer->getStateName(),
            $firstBeforeUpdateStateMachineItemTransfer->getStateName()
        );
        $this->assertEquals(
            $firstUpdatedStateMachineItemTransfer->getIdentifier(),
            $firstBeforeUpdateStateMachineItemTransfer->getIdentifier()
        );

        $secondUpdatedStateMachineItemTransfer = $updatedStateMachineItems[1];
        $secondBeforeUpdateStateMachineItemTransfer = $stateMachineItems[1];
        $this->assertEquals(
            $secondUpdatedStateMachineItemTransfer->getIdItemState(),
            $secondBeforeUpdateStateMachineItemTransfer->getIdItemState()
        );
        $this->assertEquals(
            $secondUpdatedStateMachineItemTransfer->getProcessName(),
            $secondBeforeUpdateStateMachineItemTransfer->getProcessName()
        );
        $this->assertEquals(
            $secondUpdatedStateMachineItemTransfer->getIdStateMachineProcess(),
            $secondBeforeUpdateStateMachineItemTransfer->getIdStateMachineProcess()
        );
        $this->assertEquals(
            $secondUpdatedStateMachineItemTransfer->getStateName(),
            $secondBeforeUpdateStateMachineItemTransfer->getStateName()
        );
        $this->assertEquals(
            $secondUpdatedStateMachineItemTransfer->getIdentifier(),
            $secondBeforeUpdateStateMachineItemTransfer->getIdentifier()
        );
    }

    /**
     * @return void
     */
    public function testGetProcessedStateMachineItemTransferShouldReturnItemTransfer(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $firstItemIdentifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $firstItemIdentifier);
        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $updatedStateMachineItemTransfer = $stateMachineFacade
            ->getProcessedStateMachineItemTransfer($stateMachineItemTransfer);

        $this->assertEquals(
            $updatedStateMachineItemTransfer->getIdItemState(),
            $stateMachineItemTransfer->getIdItemState()
        );
        $this->assertEquals(
            $updatedStateMachineItemTransfer->getProcessName(),
            $stateMachineItemTransfer->getProcessName()
        );
        $this->assertEquals(
            $updatedStateMachineItemTransfer->getIdStateMachineProcess(),
            $stateMachineItemTransfer->getIdStateMachineProcess()
        );
        $this->assertEquals(
            $updatedStateMachineItemTransfer->getStateName(),
            $stateMachineItemTransfer->getStateName()
        );
        $this->assertEquals(
            $updatedStateMachineItemTransfer->getIdentifier(),
            $stateMachineItemTransfer->getIdentifier()
        );
    }

    /**
     * @return void
     */
    public function testGetStateHistoryByStateItemIdentifierShouldReturnAllHistoryForThatItem(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);
        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineItemsTransfer = $stateMachineFacade->getStateHistoryByStateItemIdentifier(
            $stateMachineItemTransfer->getIdStateMachineProcess(),
            $identifier
        );

        $this->assertCount(3, $stateMachineItemsTransfer);

        $stateMachineItemTransfer = $stateMachineItemsTransfer[0];
        $this->assertEquals('invoice created', $stateMachineItemTransfer->getStateName());

        $stateMachineItemTransfer = $stateMachineItemsTransfer[1];
        $this->assertEquals('invoice sent', $stateMachineItemTransfer->getStateName());

        $stateMachineItemTransfer = $stateMachineItemsTransfer[2];
        $this->assertEquals('order exported', $stateMachineItemTransfer->getStateName());
    }

    /**
     * @return void
     */
    public function testGetItemsWithFlagShouldReturnListOfStateMachineItemsWithGivenFlag(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemsWithGivenFlag = $stateMachineFacade->getItemsWithFlag(
            $stateMachineProcessTransfer,
            'Flag1'
        );

        $this->assertCount(2, $stateMachineItemsWithGivenFlag);

        $stateMachineItemTransfer = $stateMachineItemsWithGivenFlag[0];
        $this->assertInstanceOf(StateMachineItemTransfer::class, $stateMachineItemTransfer);
        $this->assertEquals('invoice created', $stateMachineItemTransfer->getStateName());
        $this->assertEquals($identifier, $stateMachineItemTransfer->getIdentifier());

        $stateMachineItemTransfer = $stateMachineItemsWithGivenFlag[1];
        $this->assertEquals('invoice sent', $stateMachineItemTransfer->getStateName());
        $this->assertEquals($identifier, $stateMachineItemTransfer->getIdentifier());

        $stateMachineItemsWithGivenFlag = $stateMachineFacade->getItemsWithFlag(
            $stateMachineProcessTransfer,
            'Flag2'
        );

        $this->assertCount(1, $stateMachineItemsWithGivenFlag);
    }

    /**
     * @return void
     */
    public function testCheckConditionsShouldProcessStatesWithConditionAndWithoutEvent(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineFacade->triggerEvent('check with condition', $stateMachineItemTransfer);

        $stateMachineHandler->setStateMachineItemsByStateIds([$stateMachineItemTransfer]);

        $stateMachineFacade->checkConditions(static::TESTING_SM);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $this->assertEquals('waiting for payment', $stateMachineItemTransfer->getStateName());
    }

    /**
     * @return void
     */
    public function testCheckTimeoutsShouldMoveStatesWithExpiredTimeouts(): void
    {
        $processName = static::TEST_PROCESS_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineFacade->triggerEvent('ship order', $stateMachineItemTransfer);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineItemEventTimeoutEntity = $this->StateMachineTimeouts
            ->find()
            ->where([
                'identifier' => $stateMachineItemTransfer->getIdentifier(),
                'state_machine_process_id' => $stateMachineItemTransfer->getIdStateMachineProcess(),
            ])
            ->first();

        $stateMachineItemEventTimeoutEntity->timeout = new DateTime('1985-07-01');
        $this->StateMachineTimeouts->save($stateMachineItemEventTimeoutEntity);

        $affectedItems = $stateMachineFacade->checkTimeouts(static::TESTING_SM);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $this->assertEquals(1, $affectedItems);
        $this->assertEquals('reminder I sent', $stateMachineItemTransfer->getStateName());
    }

    /**
     * @return void
     */
    public function testLoopDoesNotCauseExceptions(): void
    {
        $processName = static::TEST_PROCESS_WITH_LOOP_NAME;
        $identifier = 1985;

        $stateMachineProcessTransfer = new StateMachineProcessTransfer();
        $stateMachineProcessTransfer->setProcessName($processName);
        $stateMachineProcessTransfer->setStateMachineName(static::TESTING_SM);

        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);

        $stateMachineFacade->triggerForNewStateMachineItem($stateMachineProcessTransfer, $identifier);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $stateMachineFacade->triggerEvent('enter loop action', $stateMachineItemTransfer);
        $triggerResult = $stateMachineFacade->triggerEvent('loop exit action', $stateMachineItemTransfer);

        $stateMachineItemTransfer = $stateMachineHandler->getItemStateUpdated();

        $this->assertEquals(1, $triggerResult);
        $this->assertEquals('done', $stateMachineItemTransfer->getStateName());
        $this->assertEquals($processName, $stateMachineItemTransfer->getProcessName());
        $this->assertEquals($identifier, $stateMachineItemTransfer->getIdentifier());
    }

    /**
     * @return void
     */
    public function testStateMachineExistsReturnsTrueWhenStateMachineHasHandler()
    {
        // Assign
        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineName = $stateMachineHandler->getStateMachineName();
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);
        $expectedResult = true;

        // Act
        $actualResult = $stateMachineFacade->stateMachineExists($stateMachineName);

        // Assert
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @return void
     */
    public function testStateMachineExistsReturnsFalseWhenStateMachineHasNoHandler(): void
    {
        // Assign
        $stateMachineHandler = $this->createTestStateMachineHandler();
        $stateMachineName = $stateMachineHandler->getStateMachineName() . 'SomethingElse';
        $stateMachineFacade = $this->createStateMachineFacade($stateMachineHandler);
        $expectedResult = false;

        // Act
        $actualResult = $stateMachineFacade->stateMachineExists($stateMachineName);

        // Assert
        $this->assertSame($expectedResult, $actualResult);
    }

    protected function createStateMachineFacade(StateMachineHandlerInterface $stateMachineHandler): StateMachineFacadeInterface
    {
        Configure::write('StateMachine.handlers', [
            $stateMachineHandler,
        ]);
        Configure::write('StateMachine.pathToXml', __DIR__ . DS . '..' . DS . '..' . DS . 'test_files' . DS);

        $stateMachineFacade = new StateMachineFacade();

        return $stateMachineFacade;
    }

    /**
     * @return \StateMachine\Dependency\StateMachineHandlerInterface
     */
    protected function createTestStateMachineHandler(): StateMachineHandlerInterface
    {
        return new class implements StateMachineHandlerInterface
        {
            protected static $itemStateUpdated;
            protected static $stateMachineItemsByStateIds = [];

            public function getCommandPlugins(): array
            {
                return [
                    'Test/CreateInvoice' => $this->createCommandPlugin(),
                    'Test/SendInvoice' => $this->createCommandPlugin(),
                ];
            }

            public function getConditionPlugins(): array
            {
                return [
                    'Test/IsInvoiceSent' => $this->createConditionPlugin(),
                    'Test/Condition' => $this->createConditionPlugin(),
                ];
            }

            public function getStateMachineName(): string
            {
                return 'TestingSm';
            }

            public function getActiveProcesses(): array
            {
                return [
                    'TestProcess',
                ];
            }

            public function getInitialStateForProcess($processName): string
            {
                return 'new';
            }

            public function itemStateUpdated(StateMachineItemTransfer $stateMachineItemTransfer): bool
            {
                static::$itemStateUpdated = $stateMachineItemTransfer;

                return true;
            }

            public function getStateMachineItemsByStateIds(array $stateIds = []): array
            {
                $result = [];
                foreach (static::$stateMachineItemsByStateIds as $stateMachineItemTransfer) {
                    if (in_array($stateMachineItemTransfer->getIdItemState(), $stateIds)) {
                        $result[] = $stateMachineItemTransfer;
                    }
                }

                return $result;
            }

            public function getItemStateUpdated(): StateMachineItemTransfer
            {
                return static::$itemStateUpdated;
            }

            public function setStateMachineItemsByStateIds(array $stateMachineItemsByStateIds): void
            {
                static::$stateMachineItemsByStateIds = $stateMachineItemsByStateIds;
            }

            protected function createCommandPlugin(): CommandPluginInterface
            {
                return new class implements CommandPluginInterface
                {
                    public function run(StateMachineItemTransfer $stateMachineItemTransfer): bool
                    {
                        return true;
                    }
                };
            }

            protected function createConditionPlugin(): ConditionPluginInterface
            {
                return new class implements ConditionPluginInterface
                {
                    public function check(StateMachineItemTransfer $stateMachineItemTransfer): bool
                    {
                        return true;
                    }
                };
            }
        };
    }
}
