<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

class PlanningEventMappingsSeed extends AbstractSeed
{
    public function run(): void
    {
        $table = $this->table('planning_event_mappings');
        
        // Utiliser l'adapter pour exécuter des requêtes SQL directes
        $adapter = $this->getAdapter();
        
        // Trouver les offres par type via SQL
        $absenceOffer = $adapter->query("SELECT id FROM offers WHERE offer_type = 'absence' LIMIT 1")->fetch();
        $remoteWorkOffer = $adapter->query("SELECT id FROM offers WHERE offer_type = 'remote_work' LIMIT 1")->fetch();
        
        if (!$absenceOffer || !isset($absenceOffer['id'])) {
            throw new \RuntimeException('Aucune offre de type "absence" trouvée. Veuillez créer au moins une offre absence.');
        }
        if (!$remoteWorkOffer || !isset($remoteWorkOffer['id'])) {
            throw new \RuntimeException('Aucune offre de type "remote_work" trouvée. Veuillez créer au moins une offre télétravail.');
        }
        
        $absenceOfferId = (int)$absenceOffer['id'];
        $remoteWorkOfferId = (int)$remoteWorkOffer['id'];
        
        $data = [
            // Télétravail par couleur (priorité 10)
            [
                'keywords' => null,
                'color_code' => 'f1c1d6',
                'offer_id' => $remoteWorkOfferId,
                'priority' => 10,
            ],
            // Télétravail par mot-clé (priorité 20 - plus élevée)
            [
                'keywords' => 'télétravail',
                'color_code' => null,
                'offer_id' => $remoteWorkOfferId,
                'priority' => 20,
            ],
            // Variante du mot-clé télétravail
            [
                'keywords' => 'teletravail',
                'color_code' => null,
                'offer_id' => $remoteWorkOfferId,
                'priority' => 20,
            ],
            // Absence par couleur (priorité 10)
            [
                'keywords' => null,
                'color_code' => '99cc00',
                'offer_id' => $absenceOfferId,
                'priority' => 10,
            ],
            // Absence par mots-clés (priorité 20 - plus élevée)
            [
                'keywords' => 'congé',
                'color_code' => null,
                'offer_id' => $absenceOfferId,
                'priority' => 20,
            ],
            [
                'keywords' => 'conges',
                'color_code' => null,
                'offer_id' => $absenceOfferId,
                'priority' => 20,
            ],
            [
                'keywords' => 'maladie',
                'color_code' => null,
                'offer_id' => $absenceOfferId,
                'priority' => 20,
            ],
            [
                'keywords' => 'rtt',
                'color_code' => null,
                'offer_id' => $absenceOfferId,
                'priority' => 20,
            ],
            [
                'keywords' => 'absence',
                'color_code' => null,
                'offer_id' => $absenceOfferId,
                'priority' => 15,
            ],
        ];
        
        // Insertion idempotente : vérifier l'existence avant d'insérer
        foreach ($data as $row) {
            // Construire la requête de vérification
            $where = [];
            $params = [];
            
            if ($row['keywords'] === null) {
                $where[] = "keywords IS NULL";
            } else {
                $where[] = "keywords = :keywords";
                $params['keywords'] = $row['keywords'];
            }
            
            if ($row['color_code'] === null) {
                $where[] = "color_code IS NULL";
            } else {
                $where[] = "color_code = :color_code";
                $params['color_code'] = $row['color_code'];
            }
            
            
            $sql = "SELECT COUNT(*) as count FROM planning_event_mappings WHERE " . implode(' AND ', $where);
            $exists = $adapter->query($sql, $params)->fetch();
            
            if ($exists && $exists['count'] == 0) {
                $table->insert($row)->save();
            }
        }
    }
}
