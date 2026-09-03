<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRotationRuleLines extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('rotation_rules')) {
            $rules = $this->table('rotation_rules');
            if (!$rules->hasColumn('exclusive_day')) {
                $rules->addColumn('exclusive_day', 'boolean', [
                    'default' => true,
                    'null' => false,
                    'comment' => 'Max 1 duty par jour et par agent',
                ]);
                $rules->update();
            }
        }

        if (!$this->hasTable('rotation_rule_lines')) {
            $lines = $this->table('rotation_rule_lines');
            $lines
                ->addColumn('rotation_rule_id', 'uuid', ['null' => false])
                ->addColumn('line_type', 'string', ['limit' => 20, 'null' => false])
                ->addColumn('offer_id', 'integer', ['null' => true, 'default' => null])
                ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 1])
                ->addColumn('target_count', 'integer', ['null' => true, 'default' => null])
                ->addColumn('shift_duration', 'integer', ['null' => true, 'default' => null])
                ->addColumn('time_window_start', 'time', ['null' => true, 'default' => null])
                ->addColumn('time_window_end', 'time', ['null' => true, 'default' => null])
                ->addColumn('fit_need_curve', 'boolean', ['null' => false, 'default' => true])
                ->addColumn('quantity', 'integer', ['null' => true, 'default' => null])
                ->addColumn('equity_enabled', 'boolean', ['null' => false, 'default' => true])
                ->addColumn('same_person_day_slots', 'boolean', ['null' => false, 'default' => false])
                ->addColumn('days_of_week', 'json', ['null' => true, 'default' => null])
                ->addColumn('quota_flag', 'tinyinteger', [
                    'null' => true,
                    'default' => null,
                    'comment' => '1 si ligne quota, NULL sinon — unicité (rule, quota_flag)',
                ])
                ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
                ->addIndex(['rotation_rule_id'], ['name' => 'IDX_RRL_RULE'])
                ->addIndex(['offer_id'], ['name' => 'IDX_RRL_OFFER'])
                ->addIndex(['rotation_rule_id', 'quota_flag'], [
                    'name' => 'UNQ_RRL_ONE_QUOTA',
                    'unique' => true,
                ])
                ->addForeignKey('rotation_rule_id', 'rotation_rules', 'id', [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE',
                ])
                ->addForeignKey('offer_id', 'offers', 'id', [
                    'delete' => 'SET_NULL',
                    'update' => 'CASCADE',
                ])
                ->create();
        }

        if (!$this->hasTable('rotation_rule_line_slots')) {
            $slots = $this->table('rotation_rule_line_slots');
            $slots
                ->addColumn('rotation_rule_line_id', 'integer', ['null' => false])
                ->addColumn('start_time', 'time', ['null' => false])
                ->addColumn('end_time', 'time', ['null' => false])
                ->addColumn('position', 'integer', ['null' => true, 'default' => null])
                ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
                ->addIndex(['rotation_rule_line_id'], ['name' => 'IDX_RRLS_LINE'])
                ->addForeignKey('rotation_rule_line_id', 'rotation_rule_lines', 'id', [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE',
                ])
                ->create();
        }

        if (!$this->hasTable('rotation_rules') || !$this->hasTable('rotation_rule_lines')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->fetchAll('SELECT rotation_rule_id FROM rotation_rule_lines');
        $already = [];
        foreach ($existing as $row) {
            $already[(string)$row['rotation_rule_id']] = true;
        }

        $rules = $this->fetchAll(
            'SELECT id, offer_id, target_count, shift_duration, time_window_start, time_window_end FROM rotation_rules'
        );
        foreach ($rules as $rule) {
            $rid = (string)$rule['id'];
            if (isset($already[$rid])) {
                continue;
            }
            $this->table('rotation_rule_lines')->insert([
                'rotation_rule_id' => $rid,
                'line_type' => 'quota',
                'offer_id' => $rule['offer_id'],
                'sort_order' => 1,
                'target_count' => $rule['target_count'],
                'shift_duration' => $rule['shift_duration'],
                'time_window_start' => $rule['time_window_start'],
                'time_window_end' => $rule['time_window_end'],
                'fit_need_curve' => 1,
                'quantity' => null,
                'equity_enabled' => 1,
                'same_person_day_slots' => 0,
                'days_of_week' => null,
                'quota_flag' => 1,
                'created' => $now,
                'modified' => $now,
            ])->saveData();
        }

        $ruleCountRows = $this->fetchAll('SELECT COUNT(*) AS c FROM rotation_rules');
        $lineCountRows = $this->fetchAll(
            "SELECT COUNT(*) AS c FROM rotation_rule_lines WHERE line_type = 'quota'"
        );
        $ruleCount = (int)($ruleCountRows[0]['c'] ?? 0);
        $lineCount = (int)($lineCountRows[0]['c'] ?? 0);
        if ($ruleCount > 0 && $lineCount < $ruleCount) {
            throw new \RuntimeException(sprintf(
                'Backfill rotation_rule_lines incomplet : %d règles, %d lignes quota.',
                $ruleCount,
                $lineCount
            ));
        }
    }

    public function down(): void
    {
        if ($this->hasTable('rotation_rule_line_slots')) {
            $this->table('rotation_rule_line_slots')->drop()->save();
        }
        if ($this->hasTable('rotation_rule_lines')) {
            $this->table('rotation_rule_lines')->drop()->save();
        }
        if ($this->hasTable('rotation_rules')) {
            $rules = $this->table('rotation_rules');
            if ($rules->hasColumn('exclusive_day')) {
                $rules->removeColumn('exclusive_day');
                $rules->update();
            }
        }
    }
}
