<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace StateMachine\Business\StateMachine;

use Exception;
use StateMachine\Business\Exception\CommandNotFoundException;
use StateMachine\Business\Exception\TriggerException;
use StateMachine\Business\Logger\TransitionLogInterface;
use StateMachine\Dependency\StateMachineHandlerInterface;
use StateMachine\Dto\StateMachine\ItemDto;
use StateMachine\Dto\StateMachine\ProcessDto;

class Trigger implements TriggerInterface
{
    public const MAX_EVENT_REPEATS = 10;

    /**
     * @var \StateMachine\Business\Logger\TransitionLogInterface
     */
    protected $transitionLog;

    /**
     * @var \StateMachine\Business\StateMachine\HandlerResolverInterface
     */
    protected $stateMachineHandlerResolver;

    /**
     * @var \StateMachine\Business\StateMachine\FinderInterface
     */
    protected $finder;

    /**
     * @var \StateMachine\Business\StateMachine\PersistenceInterface
     */
    protected $stateMachinePersistence;

    /**
     * @var array
     */
    protected $eventCounter = [];

    /**
     * @var \StateMachine\Business\StateMachine\ConditionInterface
     */
    protected $condition;

    /**
     * @var \StateMachine\Business\StateMachine\StateUpdaterInterface
     */
    protected $stateUpdater;

    /**
     * @var int
     */
    protected $affectedItems = 0;

    /**
     * @param \StateMachine\Business\Logger\TransitionLogInterface $transitionLog
     * @param \StateMachine\Business\StateMachine\HandlerResolverInterface $stateMachineHandlerResolver
     * @param \StateMachine\Business\StateMachine\FinderInterface $finder
     * @param \StateMachine\Business\StateMachine\PersistenceInterface $stateMachinePersistence
     * @param \StateMachine\Business\StateMachine\ConditionInterface $condition
     * @param \StateMachine\Business\StateMachine\StateUpdaterInterface $stateUpdater
     */
    public function __construct(
        TransitionLogInterface $transitionLog,
        HandlerResolverInterface $stateMachineHandlerResolver,
        FinderInterface $finder,
        PersistenceInterface $stateMachinePersistence,
        ConditionInterface $condition,
        StateUpdaterInterface $stateUpdater
    ) {
        $this->transitionLog = $transitionLog;
        $this->stateMachineHandlerResolver = $stateMachineHandlerResolver;
        $this->finder = $finder;
        $this->stateMachinePersistence = $stateMachinePersistence;
        $this->condition = $condition;
        $this->stateUpdater = $stateUpdater;
    }

    /**
     * @param \StateMachine\Dto\StateMachine\ProcessDto $stateMachineProcessTransfer
     * @param string $identifier
     *
     * @return int
     */
    public function triggerForNewStateMachineItem(
        ProcessDto $stateMachineProcessTransfer,
        string $identifier
    ): int {
        $stateMachineItemTransfer = $this->createItemTransferForNewProcess($stateMachineProcessTransfer, $identifier);

        $processes = $this->finder->findProcessesForItems([$stateMachineItemTransfer]);

        $itemsWithOnEnterEvent = $this->finder->filterItemsWithOnEnterEvent([$stateMachineItemTransfer], $processes);

        $this->triggerOnEnterEvents($itemsWithOnEnterEvent);

        return $this->affectedItems;
    }

    /**
     * @param string $eventName
     * @param \StateMachine\Dto\StateMachine\ItemDto[] $stateMachineItems
     *
     * @return int
     */
    public function triggerEvent(string $eventName, array $stateMachineItems): int
    {
        if ($this->checkForEventRepetitions($eventName) === false) {
            return 0;
        }

        $stateMachineItems = $this->stateMachinePersistence
            ->updateStateMachineItemsFromPersistence($stateMachineItems);

        $processes = $this->finder->findProcessesForItems($stateMachineItems);
        $stateMachineItems = $this->filterEventAffectedItems($eventName, $stateMachineItems, $processes);

        $this->transitionLog->init($stateMachineItems);
        $this->logSourceState($stateMachineItems);

        $this->runCommand($eventName, $stateMachineItems, $processes);

        $sourceStateBuffer = $this->updateStateByEvent($eventName, $stateMachineItems);

        $this->stateUpdater->updateStateMachineItemState(
            $stateMachineItems,
            $processes,
            $sourceStateBuffer
        );

        $stateMachineItemsWithOnEnterEvent = $this->finder->filterItemsWithOnEnterEvent(
            $stateMachineItems,
            $processes,
            $sourceStateBuffer
        );

        $this->transitionLog->saveAll();

        $this->affectedItems += count($stateMachineItems);

        $this->triggerOnEnterEvents($stateMachineItemsWithOnEnterEvent);

        return $this->affectedItems;
    }

    /**
     * @param string $stateMachineName
     *
     * @return int
     */
    public function triggerConditionsWithoutEvent(string $stateMachineName): int
    {
        $stateMachineHandler = $this->stateMachineHandlerResolver->get($stateMachineName);
        foreach ($stateMachineHandler->getActiveProcesses() as $processName) {
            $stateMachineItemsWithOnEnterEvent = $this->condition->getOnEnterEventsForStatesWithoutTransition(
                $stateMachineName,
                $processName
            );
            $this->triggerOnEnterEvents($stateMachineItemsWithOnEnterEvent);
        }

        return $this->affectedItems;
    }

    /**
     * @param string $stateMachineName
     *
     * @return int
     */
    public function triggerForTimeoutExpiredItems(string $stateMachineName): int
    {
        $stateMachineItems = $this->stateMachinePersistence->getItemsWithExpiredTimeouts($stateMachineName);

        $groupedStateMachineItems = $this->groupItemsByEvent($stateMachineItems);
        foreach ($groupedStateMachineItems as $event => $stateMachineItems) {
            $this->triggerEvent($event, $stateMachineItems);
        }

        return $this->affectedItems;
    }

    /**
     * @param \StateMachine\Dto\StateMachine\ItemDto[] $stateMachineItems
     *
     * @return array
     */
    protected function groupItemsByEvent(array $stateMachineItems): array
    {
        $groupedStateMachineItems = [];
        foreach ($stateMachineItems as $stateMachineItemTransfer) {
            $eventName = $stateMachineItemTransfer->getEventName();
            if (!isset($groupedStateMachineItems[$eventName])) {
                $groupedStateMachineItems[$eventName] = [];
            }
            $groupedStateMachineItems[$eventName][] = $stateMachineItemTransfer;
        }

        return $groupedStateMachineItems;
    }

    /**
     * @param string $eventName
     * @param \StateMachine\Dto\StateMachine\ItemDto[] $stateMachineItems
     * @param \StateMachine\Business\Process\ProcessInterface[] $processes
     *
     * @return \StateMachine\Dto\StateMachine\ItemDto[]
     */
    protected function filterEventAffectedItems($eventName, array $stateMachineItems, $processes)
    {
        $stateMachineItemsFiltered = [];
        foreach ($stateMachineItems as $stateMachineItemTransfer) {
            $stateName = $stateMachineItemTransfer->getStateNameOrFail();
            $processName = $stateMachineItemTransfer->getProcessNameOrFail();
            if (!isset($processes[$processName])) {
                continue;
            }

            $process = $processes[$processName];
            $state = $process->getStateFromAllProcesses($stateName);
            if ($state->hasEvent($eventName)) {
                $stateMachineItemsFiltered[] = $stateMachineItemTransfer;
            }
        }

        return $stateMachineItemsFiltered;
    }

    /**
     * @param string $eventName
     * @param \StateMachine\Dto\StateMachine\ItemDto[] $stateMachineItems
     * @param \StateMachine\Business\Process\ProcessInterface[] $processes
     *
     * @throws \Exception
     *
     * @return void
     */
    protected function runCommand($eventName, array $stateMachineItems, array $processes)
    {
        foreach ($stateMachineItems as $stateMachineItemTransfer) {
            $stateName = $stateMachineItemTransfer->getStateNameOrFail();
            $processName = $stateMachineItemTransfer->getProcessNameOrFail();
            if (!isset($processes[$processName])) {
                continue;
            }

            $process = $processes[$processName];
            $state = $process->getStateFromAllProcesses($stateName);
            $event = $state->getEvent($eventName);

            if (!$event->hasCommand()) {
                continue;
            }

            $commandPlugin = $this->getCommand($event->getCommand(), $stateMachineItemTransfer->getStateMachineName());

            $this->transitionLog->addCommand($stateMachineItemTransfer, $commandPlugin);

            try {
                $commandPlugin->run($stateMachineItemTransfer);
            } catch (Exception $e) {
                $this->transitionLog->setIsError(true);
                $this->transitionLog->setErrorMessage(get_class($commandPlugin) . ' - ' . $e->getMessage());
                $this->transitionLog->saveAll();
                throw $e;
            }
        }
    }

    /**
     * @param string $eventName
     * @param \StateMachine\Dto\StateMachine\ItemDto[] $stateMachineItems
     *
     * @return array
     */
    protected function updateStateByEvent($eventName, array $stateMachineItems)
    {
        $sourceStateBuffer = [];
        $targetStateMap = [];
        foreach ($stateMachineItems as $i => $stateMachineItemTransfer) {
            $stateName = $stateMachineItemTransfer->getStateNameOrFail();
            $sourceStateBuffer[$stateMachineItemTransfer->getIdentifier()] = $stateName;

            $process = $this->finder->findProcessByStateMachineAndProcessName(
                $stateMachineItemTransfer->getStateMachineName(),
                $stateMachineItemTransfer->getProcessName()
            );

            $sourceState = $process->getStateFromAllProcesses($stateName);

            $event = $sourceState->getEvent($eventName);
            $this->transitionLog->setEvent($event);

            $this->transitionLog->addSourceState($stateMachineItemTransfer, $sourceState->getName());

            $targetState = $sourceState;
            if ($eventName && $sourceState->hasEvent($eventName)) {
                $transitions = $sourceState->getEvent($eventName)->getTransitionsBySource($sourceState);
                $targetState = $this->condition->getTargetStatesFromTransitions(
                    $transitions,
                    $stateMachineItemTransfer,
                    $sourceState,
                    $this->transitionLog
                );
                $this->transitionLog->addTargetState($stateMachineItemTransfer, $targetState->getName());
            }

            $targetStateMap[$i] = $targetState->getName();
        }

        foreach ($stateMachineItems as $i => $stateMachineItemTransfer) {
            $this->stateMachinePersistence->saveStateMachineItem($stateMachineItems[$i], $targetStateMap[$i]);
        }

        return $sourceStateBuffer;
    }

    /**
     * To protect of loops, every event can only be used some times
     *
     * @param string $eventName
     *
     * @return bool
     */
    protected function checkForEventRepetitions($eventName)
    {
        if (array_key_exists($eventName, $this->eventCounter) === false) {
            $this->eventCounter[$eventName] = 0;
        }
        $this->eventCounter[$eventName]++;

        return $this->eventCounter[$eventName] < self::MAX_EVENT_REPEATS;
    }

    /**
     * @param \StateMachine\Dto\StateMachine\ItemDto[][] $itemsWithOnEnterEvent Keys are event names, values are collections of StateMachineItem transfer objects
     *
     * @return bool
     */
    protected function triggerOnEnterEvents(array $itemsWithOnEnterEvent)
    {
        if (count($itemsWithOnEnterEvent) > 0) {
            foreach ($itemsWithOnEnterEvent as $eventName => $stateMachineItems) {
                $this->triggerEvent($eventName, $stateMachineItems);
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $commandString
     * @param string $stateMachineName
     *
     * @return \StateMachine\Dependency\CommandPluginInterface
     */
    protected function getCommand($commandString, $stateMachineName)
    {
        $stateMachineHandler = $this->stateMachineHandlerResolver->get($stateMachineName);

        $this->assertCommandIsSet($commandString, $stateMachineHandler);

        return $stateMachineHandler->getCommands()[$commandString];
    }

    /**
     * @param \StateMachine\Dto\StateMachine\ItemDto[] $stateMachineItems
     *
     * @return void
     */
    protected function logSourceState(array $stateMachineItems)
    {
        foreach ($stateMachineItems as $stateMachineItemTransfer) {
            $stateName = $stateMachineItemTransfer->getStateName();
            $this->transitionLog->addSourceState($stateMachineItemTransfer, $stateName);
        }
    }

    /**
     * @param \StateMachine\Dto\StateMachine\ProcessDto $stateMachineProcessTransfer
     * @param string $identifier
     *
     * @return \StateMachine\Dto\StateMachine\ItemDto
     */
    protected function createItemTransferForNewProcess(
        ProcessDto $stateMachineProcessTransfer,
        string $identifier
    ): ItemDto {
        $processName = $stateMachineProcessTransfer->getProcessNameOrFail();

        $stateMachineItemTransfer = new ItemDto();
        $stateMachineProcessTransfer->setStateMachineName($stateMachineProcessTransfer->getStateMachineNameOrFail());
        $stateMachineItemTransfer->setProcessName($processName);
        $stateMachineItemTransfer->setIdentifier($identifier);

        $idStateMachineProcess = $this->stateMachinePersistence
            ->getProcessId($stateMachineProcessTransfer);

        $this->assertProcessCreated($idStateMachineProcess);

        $stateMachineItemTransfer->setIdStateMachineProcess($idStateMachineProcess);

        $initialStateName = $this->stateMachineHandlerResolver
            ->get($stateMachineProcessTransfer->getStateMachineNameOrFail())
            ->getInitialStateForProcess($processName);

        $this->assertInitialStateNameProvided($initialStateName, $processName);
        $stateMachineItemTransfer->setStateName($initialStateName);

        $idStateMachineItemState = $this->stateMachinePersistence
            ->getInitialStateIdByStateName(
                $stateMachineItemTransfer,
                $initialStateName
            );

        $this->assertInitialStateCreated($idStateMachineItemState, $initialStateName);

        $stateMachineItemTransfer->setIdItemState($idStateMachineItemState);

        return $stateMachineItemTransfer;
    }

    /**
     * @param string $initialStateName
     * @param string $processName
     *
     * @throws \StateMachine\Business\Exception\TriggerException
     *
     * @return void
     */
    protected function assertInitialStateNameProvided($initialStateName, $processName)
    {
        if (!$initialStateName) {
            throw new TriggerException(
                sprintf(
                    'Initial state name for process "%s" is not provided. You can provide it in "%s::getInitialStateForProcess" method.',
                    $processName,
                    StateMachineHandlerInterface::class
                )
            );
        }
    }

    /**
     * @param int|null $idStateMachineItemState
     * @param string $initialStateName
     *
     * @throws \StateMachine\Business\Exception\TriggerException
     *
     * @return void
     */
    protected function assertInitialStateCreated($idStateMachineItemState, $initialStateName)
    {
        if ($idStateMachineItemState === null) {
            throw new TriggerException(
                sprintf(
                    'Initial state "%s" could not be created.',
                    $initialStateName
                )
            );
        }
    }

    /**
     * @param int $idStateMachineProcess
     *
     * @throws \StateMachine\Business\Exception\TriggerException
     *
     * @return void
     */
    protected function assertProcessCreated($idStateMachineProcess)
    {
        if (!$idStateMachineProcess) {
            throw new TriggerException(
                sprintf(
                    'Process with name "%s" not found!',
                    $idStateMachineProcess
                )
            );
        }
    }

    /**
     * @param string $commandString
     * @param \StateMachine\Dependency\StateMachineHandlerInterface $stateMachineHandler
     *
     * @throws \StateMachine\Business\Exception\CommandNotFoundException
     *
     * @return void
     */
    protected function assertCommandIsSet($commandString, StateMachineHandlerInterface $stateMachineHandler)
    {
        if (!isset($stateMachineHandler->getCommands()[$commandString])) {
            throw new CommandNotFoundException(
                sprintf(
                    'Command "%s" not registered in "%s" class. Please add it to getCommands() method.',
                    $commandString,
                    get_class($stateMachineHandler)
                )
            );
        }
    }
}
