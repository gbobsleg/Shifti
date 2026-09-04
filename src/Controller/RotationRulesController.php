<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Utility\Text;

class RotationRulesController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'index');

        $query = $this->fetchTable('RotationRules')
            ->find()
            ->contain(['Offers', 'RotationRuleLines', 'UsersRotationRules'])
            ->orderDesc('RotationRules.modified');

        if ($this->request->getQuery('offer_id')) {
            $query->where(['RotationRules.offer_id' => $this->request->getQuery('offer_id')]);
        }
        if ($this->request->getQuery('period_type')) {
            $query->where(['RotationRules.period_type' => $this->request->getQuery('period_type')]);
        }

        $this->paginate = ['limit' => 25];
        $rules = $this->paginate($query);

        $offers = $this->fetchTable('Offers')->find('list')->order(['name' => 'ASC'])->toArray();

        $this->set(compact('rules', 'offers'));
    }

    public function add()
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'add');

        $table = $this->fetchTable('RotationRules');
        $rule = $table->newEmptyEntity();
        $offers = $this->offersList();
        $wfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $defaultTimeWindowStart = $wfmSettings?->day_start_time ? (string)$wfmSettings->day_start_time : '09:00';
        $defaultTimeWindowEnd = $wfmSettings?->day_end_time ? (string)$wfmSettings->day_end_time : '18:00';

        if ($this->request->is('post')) {
            $data = $this->normalizeRulePayload($this->request->getData());
            if (empty($data['id'])) {
                $data['id'] = Text::uuid();
            }
            $rule = $table->patchEntity($rule, $data, [
                'associated' => ['RotationRuleLines.RotationRuleLineSlots'],
            ]);
            $this->syncParentFromQuotaLine($rule);
            if ($table->save($rule)) {
                $this->Flash->success('Modèle de rotation créé.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error($this->saveFailureMessage($rule));
        } else {
            $line = $this->fetchTable('RotationRuleLines')->newEmptyEntity();
            $line->line_type = 'quota';
            $line->sort_order = 1;
            $line->target_count = 3;
            $line->shift_duration = 180;
            $line->fit_need_curve = true;
            $rule->rotation_rule_lines = [$line];
            $rule->exclusive_day = true;
        }

        $this->set(compact('rule', 'offers', 'defaultTimeWindowStart', 'defaultTimeWindowEnd'));
    }

    public function edit(string $id)
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'edit');

        $table = $this->fetchTable('RotationRules');
        $rule = $table->get($id, [
            'contain' => ['Offers', 'RotationRuleLines.RotationRuleLineSlots'],
        ]);
        $offers = $this->offersList();
        $wfmSettings = $this->fetchTable('WfmSettings')->find()->first();
        $defaultTimeWindowStart = $wfmSettings?->day_start_time ? (string)$wfmSettings->day_start_time : '09:00';
        $defaultTimeWindowEnd = $wfmSettings?->day_end_time ? (string)$wfmSettings->day_end_time : '18:00';

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->normalizeRulePayload($this->request->getData());
            $rule = $table->patchEntity($rule, $data, [
                'associated' => ['RotationRuleLines.RotationRuleLineSlots'],
            ]);
            $this->syncParentFromQuotaLine($rule);
            if ($table->save($rule)) {
                $this->Flash->success('Modèle de rotation mis à jour.');
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error($this->saveFailureMessage($rule));
        }

        $this->set(compact('rule', 'offers', 'defaultTimeWindowStart', 'defaultTimeWindowEnd'));
    }

    public function view(string $id)
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'view');

        $table = $this->fetchTable('RotationRules');
        $rule = $table->get($id, [
            'contain' => [
                'Offers',
                'UsersRotationRules.Users.Sites',
                'RotationRuleLines.Offers',
                'RotationRuleLines.RotationRuleLineSlots',
            ],
        ]);

        $this->set(compact('rule'));
    }

    public function delete(string $id)
    {
        $this->Authorization->authorize(new \App\Resource\RotationRulesResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);

        $table = $this->fetchTable('RotationRules');
        $rule = $table->get($id);

        if ($table->delete($rule)) {
            $this->Flash->success('Modèle de rotation supprimé.');
        } else {
            $this->Flash->error('Suppression impossible.');
        }

        return $this->redirect(['action' => 'index']);
    }

    private function offersList(): array
    {
        return $this->fetchTable('Offers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])->order(['name' => 'ASC'])->toArray();
    }

    private function normalizeRulePayload(array $data): array
    {
        $data['exclusive_day'] = !empty($data['exclusive_day']);
        if (($data['offer_id'] ?? '') === '') {
            $data['offer_id'] = null;
        }
        $lines = $data['rotation_rule_lines'] ?? [];
        if (!is_array($lines)) {
            $lines = [];
        }
        $normalized = [];
        $order = 1;
        foreach ($lines as $key => $line) {
            if (!is_numeric($key) || !is_array($line)) {
                continue;
            }
            $type = (string)($line['line_type'] ?? 'quota');
            if (!in_array($type, ['quota', 'coverage'], true)) {
                continue;
            }
            if (($line['offer_id'] ?? '') === '') {
                $line['offer_id'] = null;
            }
            $line['sort_order'] = (int)($line['sort_order'] ?? $order);
            $order++;
            $days = array_values(array_filter(
                array_map('intval', (array)($line['days_of_week_selected'] ?? [])),
                fn($v) => $v >= 1 && $v <= 7
            ));
            $line['days_of_week'] = $days === [] ? null : $days;
            unset($line['days_of_week_selected']);
            $line['fit_need_curve'] = !empty($line['fit_need_curve']);
            $line['equity_enabled'] = !empty($line['equity_enabled']);
            $line['same_person_day_slots'] = !empty($line['same_person_day_slots']);
            $slots = [];
            foreach ((array)($line['rotation_rule_line_slots'] ?? []) as $pos => $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $s = trim((string)($slot['start_time'] ?? ''));
                $e = trim((string)($slot['end_time'] ?? ''));
                if ($s === '' || $e === '') {
                    continue;
                }
                $slot['position'] = (int)$pos;
                $slots[] = $slot;
            }
            $line['rotation_rule_line_slots'] = $slots;
            $normalized[] = $line;
        }
        $data['rotation_rule_lines'] = $normalized;

        return $data;
    }

    private function syncParentFromQuotaLine($rule): void
    {
        $quota = null;
        foreach ($rule->rotation_rule_lines ?? [] as $line) {
            if ((string)$line->line_type === 'quota') {
                $quota = $line;
                break;
            }
        }
        if ($quota === null) {
            return;
        }
        $rule->offer_id = $quota->offer_id;
        if ($quota->target_count) {
            $rule->target_count = $quota->target_count;
        }
        if ($quota->shift_duration) {
            $rule->shift_duration = $quota->shift_duration;
        }
        if ($quota->time_window_start) {
            $rule->time_window_start = $quota->time_window_start;
        }
        if ($quota->time_window_end) {
            $rule->time_window_end = $quota->time_window_end;
        }
    }

    private function saveFailureMessage($rule): string
    {
        $messages = [];
        $walk = function ($errors) use (&$walk, &$messages): void {
            foreach ($errors as $msgs) {
                if (is_array($msgs)) {
                    $walk($msgs);
                } elseif (is_string($msgs) && $msgs !== '') {
                    $messages[] = $msgs;
                }
            }
        };
        $walk($rule->getErrors());
        $messages = array_values(array_unique($messages));
        if ($messages === []) {
            return 'Échec de sauvegarde.';
        }

        return 'Échec de sauvegarde : ' . implode(' ', $messages);
    }
}
