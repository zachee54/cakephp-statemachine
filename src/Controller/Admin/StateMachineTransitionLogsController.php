<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace StateMachine\Controller\Admin;

use App\Controller\AppController;
use Cake\Http\Response;

/**
 * @property \StateMachine\Model\Table\StateMachineTransitionLogsTable $StateMachineTransitionLogs
 *
 * @method \StateMachine\Model\Entity\StateMachineTransitionLog[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class StateMachineTransitionLogsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        $this->paginate = [
            'order' => [
                'created' => 'DESC',
            ],
            'contain' => ['StateMachineProcesses'],
        ];
        $stateMachineTransitionLogs = $this->paginate();

        $this->set(compact('stateMachineTransitionLogs'));
        $this->set('_serialize', ['stateMachineTransitionLogs']);
    }

    /**
     * View method
     *
     * @param int|null $id State Machine Transition Log id.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function view(?int $id = null)
    {
        $stateMachineTransitionLog = $this->StateMachineTransitionLogs->get($id, [
            'contain' => ['StateMachineProcesses'],
        ]);

        $this->set(compact('stateMachineTransitionLog'));
        $this->set('_serialize', ['stateMachineTransitionLog']);
    }

    /**
     * Delete method
     *
     * @param int|null $id State Machine Transition Log id.
     *
     * @return \Cake\Http\Response|null Redirects to index.
     */
    public function delete(?int $id = null): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $stateMachineTransitionLog = $this->StateMachineTransitionLogs->get($id);
        if ($this->StateMachineTransitionLogs->delete($stateMachineTransitionLog)) {
            $this->Flash->success(__('The state machine transition log has been deleted.'));
        } else {
            $this->Flash->error(__('The state machine transition log could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
