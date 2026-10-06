<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\OfferColorMetadata;
use Cake\ORM\Table;

/**
 * Une ligne : révision du brouillon et palette ouverte.
 *
 * @property \App\Model\Table\OfferColorPresetsTable&\Cake\ORM\Association\BelongsTo $OfferColorPresets
 * @method \App\Model\Entity\OfferColorMetadata get($primaryKey, $options = [])
 */
class OfferColorMetadataTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offer_color_metadata');
        $this->setPrimaryKey('id');

        $this->belongsTo('OfferColorPresets', [
            'foreignKey' => 'preset_id',
        ]);
    }

    public function current(): OfferColorMetadata
    {
        $row = $this->find()->orderBy(['id' => 'ASC'])->first();
        if ($row instanceof OfferColorMetadata) {
            return $row;
        }

        $this->getConnection()->insert('offer_color_metadata', [
            'revision' => 0,
            'preset_id' => null,
        ]);
        $created = $this->find()->orderBy(['id' => 'ASC'])->first();
        if (!$created instanceof OfferColorMetadata) {
            throw new \RuntimeException('Le jeton du brouillon n\'a pas pu être créé.');
        }

        return $created;
    }

    /**
     * À appeler dans une transaction. Bloque la ligne jusqu'au commit.
     */
    public function lock(): OfferColorMetadata
    {
        $row = $this->current();
        $this->getConnection()->execute(
            'SELECT id FROM offer_color_metadata WHERE id = :id FOR UPDATE',
            ['id' => $row->id],
        )->fetchAll('assoc');

        return $this->get($row->id);
    }
}
