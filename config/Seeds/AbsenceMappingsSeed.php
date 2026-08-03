<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

class AbsenceMappingsSeed extends AbstractSeed
{
    public function run(): void
    {
        $table = $this->table('absence_mappings');
        
        $offersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Offers');
        $offers = $offersTable->find()
            ->where(['offer_type' => 'absence'])
            ->toArray();
        
        $offersByName = [];
        foreach ($offers as $offer) {
            $offersByName[$offer->name] = $offer->id;
        }
        
        $data = [
            ['excel_pattern' => 'Congés Principaux', 'offer_id' => $offersByName['Congés Principaux'] ?? 1, 'priority' => 100],
            ['excel_pattern' => 'Congés Supplémentaires', 'offer_id' => $offersByName['Congés Supplémentaires'] ?? 1, 'priority' => 90],
            ['excel_pattern' => '147 - RTT', 'offer_id' => $offersByName['RTT'] ?? 1, 'priority' => 80],
        ];
        
        foreach ($data as $row) {
            if (isset($row['offer_id']) && $row['offer_id'] > 0) {
                $table->insert($row)->save();
            }
        }
    }
}

