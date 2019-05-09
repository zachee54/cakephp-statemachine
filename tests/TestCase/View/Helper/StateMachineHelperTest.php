<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace StateMachine\Test\TestCase\View\Helper;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use StateMachine\Model\Entity\StateMachineItem;
use StateMachine\View\Helper\StateMachineHelper;

class StateMachineHelperTest extends TestCase
{
    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $map = [
            'Demo' => [
                'plugin' => 'StateMachine',
                'prefix' => 'admin',
                'controller' => 'Records',
            ],
        ];
        Configure::write('StateMachine.map', $map);
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        Configure::delete('StateMachine.map');
    }

    /**
     * @return void
     */
    public function testItemLink(): void
    {
        $stateMachineItem = new StateMachineItem();
        $stateMachineItem->identifier = 3;

        $helper = new StateMachineHelper(new View());

        $result = $helper->itemLink($stateMachineItem);
        $this->assertSame('3', $result);

        $stateMachineItem->state_machine = 'Demo';

        $result = $helper->itemLink($stateMachineItem);
        $expected = '<a href="/admin/state-machine/records/view/3">3</a>';
        $this->assertSame($expected, $result);
    }
}