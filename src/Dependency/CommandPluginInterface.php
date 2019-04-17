<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace StateMachine\Dependency;

use StateMachine\Dto\StateMachine\ItemDto;

interface CommandPluginInterface
{
    /**
     * Specification:
     * - This method is called when event have concrete command assigned.
     *
     * @api
     *
     * @param \StateMachine\Dto\StateMachine\ItemDto $stateMachineItemTransfer
     *
     * @return bool
     */
    public function run(ItemDto $stateMachineItemTransfer): bool;
}
