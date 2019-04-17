<?php
/**
 * !!! Auto generated file. Do not directly modify this file. !!!
 * You can either version control this or generate the file on the fly prior to usage/deployment.
 */

namespace StateMachine\Dto\StateMachine;

/**
 * StateMachine/Item DTO
 *
 * @property string|null $identifier
 * @property int|null $idStateMachineProcess
 * @property int|null $idItemState
 * @property string|null $processName
 * @property string|null $stateMachineName
 * @property string|null $stateName
 * @property string|null $eventName
 * @property string|null $createdAt
 */
class ItemDto extends \CakeDto\Dto\AbstractDto {

	const FIELD_IDENTIFIER = 'identifier';
	const FIELD_ID_STATE_MACHINE_PROCESS = 'idStateMachineProcess';
	const FIELD_ID_ITEM_STATE = 'idItemState';
	const FIELD_PROCESS_NAME = 'processName';
	const FIELD_STATE_MACHINE_NAME = 'stateMachineName';
	const FIELD_STATE_NAME = 'stateName';
	const FIELD_EVENT_NAME = 'eventName';
	const FIELD_CREATED_AT = 'createdAt';

	/**
	 * @var string|null
	 */
	protected $identifier;

	/**
	 * @var int|null
	 */
	protected $idStateMachineProcess;

	/**
	 * @var int|null
	 */
	protected $idItemState;

	/**
	 * @var string|null
	 */
	protected $processName;

	/**
	 * @var string|null
	 */
	protected $stateMachineName;

	/**
	 * @var string|null
	 */
	protected $stateName;

	/**
	 * @var string|null
	 */
	protected $eventName;

	/**
	 * @var string|null
	 */
	protected $createdAt;

	/**
	 * Some data is only for debugging for now.
	 *
	 * @var array
	 */
	protected $_metadata = [
		'identifier' => [
			'name' => 'identifier',
			'type' => 'string',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'idStateMachineProcess' => [
			'name' => 'idStateMachineProcess',
			'type' => 'int',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'idItemState' => [
			'name' => 'idItemState',
			'type' => 'int',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'processName' => [
			'name' => 'processName',
			'type' => 'string',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'stateMachineName' => [
			'name' => 'stateMachineName',
			'type' => 'string',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'stateName' => [
			'name' => 'stateName',
			'type' => 'string',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'eventName' => [
			'name' => 'eventName',
			'type' => 'string',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
		'createdAt' => [
			'name' => 'createdAt',
			'type' => 'string',
			'required' => false,
			'defaultValue' => null,
			'dto' => null,
			'collectionType' => null,
			'associative' => false,
			'key' => null,
			'serializable' => false,
			'toArray' => false,
		],
	];

	/**
	* @var array
	*/
	protected $_keyMap = [
		'underscored' => [
			'identifier' => 'identifier',
			'id_state_machine_process' => 'idStateMachineProcess',
			'id_item_state' => 'idItemState',
			'process_name' => 'processName',
			'state_machine_name' => 'stateMachineName',
			'state_name' => 'stateName',
			'event_name' => 'eventName',
			'created_at' => 'createdAt',
		],
		'dashed' => [
			'identifier' => 'identifier',
			'id-state-machine-process' => 'idStateMachineProcess',
			'id-item-state' => 'idItemState',
			'process-name' => 'processName',
			'state-machine-name' => 'stateMachineName',
			'state-name' => 'stateName',
			'event-name' => 'eventName',
			'created-at' => 'createdAt',
		],
	];

	/**
	 * @param string|null $identifier
	 *
	 * @return $this
	 */
	public function setIdentifier(?string $identifier = null) {
		$this->identifier = $identifier;
		$this->_touchedFields[self::FIELD_IDENTIFIER] = true;

		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getIdentifier(): ?string {
		return $this->identifier;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return string
	 */
	public function getIdentifierOrFail(): string {
		if (!isset($this->identifier)) {
			throw new \RuntimeException('Value not set for field `identifier` (expected to be not null)');
		}

		return $this->identifier;
	}

	/**
	 * @return bool
	 */
	public function hasIdentifier() {
		return $this->identifier !== null;
	}

	/**
	 * @param int|null $idStateMachineProcess
	 *
	 * @return $this
	 */
	public function setIdStateMachineProcess(?int $idStateMachineProcess = null) {
		$this->idStateMachineProcess = $idStateMachineProcess;
		$this->_touchedFields[self::FIELD_ID_STATE_MACHINE_PROCESS] = true;

		return $this;
	}

	/**
	 * @return int|null
	 */
	public function getIdStateMachineProcess(): ?int {
		return $this->idStateMachineProcess;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return int
	 */
	public function getIdStateMachineProcessOrFail(): int {
		if (!isset($this->idStateMachineProcess)) {
			throw new \RuntimeException('Value not set for field `idStateMachineProcess` (expected to be not null)');
		}

		return $this->idStateMachineProcess;
	}

	/**
	 * @return bool
	 */
	public function hasIdStateMachineProcess() {
		return $this->idStateMachineProcess !== null;
	}

	/**
	 * @param int|null $idItemState
	 *
	 * @return $this
	 */
	public function setIdItemState(?int $idItemState = null) {
		$this->idItemState = $idItemState;
		$this->_touchedFields[self::FIELD_ID_ITEM_STATE] = true;

		return $this;
	}

	/**
	 * @return int|null
	 */
	public function getIdItemState(): ?int {
		return $this->idItemState;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return int
	 */
	public function getIdItemStateOrFail(): int {
		if (!isset($this->idItemState)) {
			throw new \RuntimeException('Value not set for field `idItemState` (expected to be not null)');
		}

		return $this->idItemState;
	}

	/**
	 * @return bool
	 */
	public function hasIdItemState() {
		return $this->idItemState !== null;
	}

	/**
	 * @param string|null $processName
	 *
	 * @return $this
	 */
	public function setProcessName(?string $processName = null) {
		$this->processName = $processName;
		$this->_touchedFields[self::FIELD_PROCESS_NAME] = true;

		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getProcessName(): ?string {
		return $this->processName;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return string
	 */
	public function getProcessNameOrFail(): string {
		if (!isset($this->processName)) {
			throw new \RuntimeException('Value not set for field `processName` (expected to be not null)');
		}

		return $this->processName;
	}

	/**
	 * @return bool
	 */
	public function hasProcessName() {
		return $this->processName !== null;
	}

	/**
	 * @param string|null $stateMachineName
	 *
	 * @return $this
	 */
	public function setStateMachineName(?string $stateMachineName = null) {
		$this->stateMachineName = $stateMachineName;
		$this->_touchedFields[self::FIELD_STATE_MACHINE_NAME] = true;

		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getStateMachineName(): ?string {
		return $this->stateMachineName;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return string
	 */
	public function getStateMachineNameOrFail(): string {
		if (!isset($this->stateMachineName)) {
			throw new \RuntimeException('Value not set for field `stateMachineName` (expected to be not null)');
		}

		return $this->stateMachineName;
	}

	/**
	 * @return bool
	 */
	public function hasStateMachineName() {
		return $this->stateMachineName !== null;
	}

	/**
	 * @param string|null $stateName
	 *
	 * @return $this
	 */
	public function setStateName(?string $stateName = null) {
		$this->stateName = $stateName;
		$this->_touchedFields[self::FIELD_STATE_NAME] = true;

		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getStateName(): ?string {
		return $this->stateName;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return string
	 */
	public function getStateNameOrFail(): string {
		if (!isset($this->stateName)) {
			throw new \RuntimeException('Value not set for field `stateName` (expected to be not null)');
		}

		return $this->stateName;
	}

	/**
	 * @return bool
	 */
	public function hasStateName() {
		return $this->stateName !== null;
	}

	/**
	 * @param string|null $eventName
	 *
	 * @return $this
	 */
	public function setEventName(?string $eventName = null) {
		$this->eventName = $eventName;
		$this->_touchedFields[self::FIELD_EVENT_NAME] = true;

		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getEventName(): ?string {
		return $this->eventName;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return string
	 */
	public function getEventNameOrFail(): string {
		if (!isset($this->eventName)) {
			throw new \RuntimeException('Value not set for field `eventName` (expected to be not null)');
		}

		return $this->eventName;
	}

	/**
	 * @return bool
	 */
	public function hasEventName() {
		return $this->eventName !== null;
	}

	/**
	 * @param string|null $createdAt
	 *
	 * @return $this
	 */
	public function setCreatedAt(?string $createdAt = null) {
		$this->createdAt = $createdAt;
		$this->_touchedFields[self::FIELD_CREATED_AT] = true;

		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getCreatedAt(): ?string {
		return $this->createdAt;
	}

	/**
	 * @throws \RuntimeException If value is not set.
	 *
	 * @return string
	 */
	public function getCreatedAtOrFail(): string {
		if (!isset($this->createdAt)) {
			throw new \RuntimeException('Value not set for field `createdAt` (expected to be not null)');
		}

		return $this->createdAt;
	}

	/**
	 * @return bool
	 */
	public function hasCreatedAt() {
		return $this->createdAt !== null;
	}

}
