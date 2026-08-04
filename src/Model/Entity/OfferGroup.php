<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * OfferGroup Entity
 *
 * @property int $id
 * @property string $name
 * @property int $mixed_offer_id
 * @property string $forecast_source members|group
 * @property bool $prefer_mixed
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Offer $mixed_offer
 * @property \App\Model\Entity\OfferGroupMember[] $offer_group_members
 */
class OfferGroup extends Entity
{
    public const FORECAST_SOURCE_MEMBERS = 'members';
    public const FORECAST_SOURCE_GROUP = 'group';

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'mixed_offer_id' => true,
        'forecast_source' => true,
        'prefer_mixed' => true,
        'created' => true,
        'modified' => true,
        'mixed_offer' => true,
        'offer_group_members' => true,
    ];

    public function isForecastSourceGroup(): bool
    {
        return $this->forecast_source === self::FORECAST_SOURCE_GROUP;
    }

    public function isForecastSourceMembers(): bool
    {
        return $this->forecast_source === self::FORECAST_SOURCE_MEMBERS;
    }
}
