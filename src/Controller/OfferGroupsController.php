<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\OfferGroup;
use App\Resource\OfferGroupsResource;

/**
 * OfferGroups Controller — CRUD admin des groupes d'offres (profils mixtes).
 *
 * @property \App\Model\Table\OfferGroupsTable $OfferGroups
 */
class OfferGroupsController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new OfferGroupsResource(), 'index');

        $query = $this->OfferGroups->find()
            ->contain(['MixedOffers', 'OfferGroupMembers' => ['Offers']])
            ->orderBy(['OfferGroups.name' => 'ASC']);

        $offerGroups = $this->paginate($query);
        $this->set(compact('offerGroups'));
    }

    public function view($id = null)
    {
        $this->Authorization->authorize(new OfferGroupsResource(), 'view');

        $offerGroup = $this->OfferGroups->get($id, contain: [
            'MixedOffers',
            'OfferGroupMembers' => ['Offers'],
        ]);

        $this->set(compact('offerGroup'));
    }

    public function add()
    {
        $this->Authorization->authorize(new OfferGroupsResource(), 'add');

        $offerGroup = $this->OfferGroups->newEmptyEntity();
        $offerGroup->forecast_source = OfferGroup::FORECAST_SOURCE_MEMBERS;
        $offerGroup->prefer_mixed = true;

        if ($this->request->is('post')) {
            $data = $this->normalizeRequestData($this->request->getData());
            $offerGroup = $this->OfferGroups->patchEntity($offerGroup, $data, [
                'associated' => ['OfferGroupMembers'],
            ]);

            if ($this->OfferGroups->save($offerGroup, ['associated' => ['OfferGroupMembers']])) {
                $this->Flash->success(__('Le groupe d\'offres a été enregistré.'));

                return $this->redirect(['action' => 'view', $offerGroup->id]);
            }
            $this->Flash->error(__('Impossible d\'enregistrer le groupe. Vérifiez les erreurs du formulaire.'));
        }

        $mixedOffers = $this->getAvailableMixedOfferList();
        $memberOfferRows = $this->buildMemberOfferRows(null);
        $this->set(compact('offerGroup', 'mixedOffers', 'memberOfferRows'));
    }

    public function edit($id = null)
    {
        $this->Authorization->authorize(new OfferGroupsResource(), 'edit');

        $offerGroup = $this->OfferGroups->get($id, contain: [
            'MixedOffers',
            'OfferGroupMembers' => ['Offers'],
        ]);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->normalizeRequestData($this->request->getData());
            $offerGroup = $this->OfferGroups->patchEntity($offerGroup, $data, [
                'associated' => ['OfferGroupMembers'],
            ]);

            if ($this->OfferGroups->save($offerGroup, ['associated' => ['OfferGroupMembers']])) {
                $this->Flash->success(__('Le groupe d\'offres a été mis à jour.'));

                return $this->redirect(['action' => 'view', $offerGroup->id]);
            }
            $this->Flash->error(__('Impossible de mettre à jour le groupe. Vérifiez les erreurs du formulaire.'));
        }

        $mixedOffers = $this->getAvailableMixedOfferList((int)$offerGroup->id, (int)$offerGroup->mixed_offer_id);
        $memberOfferRows = $this->buildMemberOfferRows($offerGroup);
        $this->set(compact('offerGroup', 'mixedOffers', 'memberOfferRows'));
    }

    public function delete($id = null)
    {
        $this->Authorization->authorize(new OfferGroupsResource(), 'delete');
        $this->request->allowMethod(['post', 'delete']);

        $offerGroup = $this->OfferGroups->get($id);
        if ($this->OfferGroups->delete($offerGroup)) {
            $this->Flash->success(__('Le groupe d\'offres a été supprimé.'));
        } else {
            $this->Flash->error(__('Impossible de supprimer le groupe d\'offres.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Transforme la sélection UI (checkbox + ratios) en tableau associated patchable.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeRequestData(array $data): array
    {
        $membersIn = $data['offer_group_members'] ?? [];
        $membersOut = [];
        $order = 0;

        if (is_array($membersIn)) {
            foreach ($membersIn as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $selected = !empty($row['_selected']);
                $offerId = (int)($row['offer_id'] ?? 0);
                if (!$selected || $offerId <= 0) {
                    continue;
                }
                $member = [
                    'offer_id' => $offerId,
                    'display_order' => $order++,
                ];
                if (array_key_exists('split_ratio_percent', $row) && $row['split_ratio_percent'] !== '') {
                    $member['split_ratio_percent'] = (int)$row['split_ratio_percent'];
                } else {
                    $member['split_ratio_percent'] = null;
                }
                $membersOut[] = $member;
            }
        }

        $data['offer_group_members'] = $membersOut;
        if (!isset($data['prefer_mixed'])) {
            $data['prefer_mixed'] = false;
        } else {
            $data['prefer_mixed'] = (bool)$data['prefer_mixed'];
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function getAvailableMixedOfferList(?int $exceptGroupId = null, ?int $keepOfferId = null): array
    {
        $usedMixedIds = $this->OfferGroups->find()
            ->select(['mixed_offer_id']);
        if ($exceptGroupId) {
            $usedMixedIds->where(['id !=' => $exceptGroupId]);
        }
        $usedMixedIds = $usedMixedIds->all()->extract('mixed_offer_id')->toList();

        $usedMemberIds = $this->OfferGroups->OfferGroupMembers->find()
            ->select(['offer_id']);
        if ($exceptGroupId) {
            $usedMemberIds->where(['offer_group_id !=' => $exceptGroupId]);
        }
        $usedMemberIds = $usedMemberIds->all()->extract('offer_id')->toList();

        $blocked = array_values(array_unique(array_merge($usedMixedIds, $usedMemberIds)));
        if ($keepOfferId) {
            $blocked = array_values(array_filter($blocked, static fn ($id) => (int)$id !== (int)$keepOfferId));
        }

        $query = $this->fetchTable('Offers')->find('list', [
            'keyField' => 'id',
            'valueField' => 'name',
        ])
            ->where(['offer_type' => 'normal'])
            ->orderBy(['name' => 'ASC']);

        if ($blocked !== []) {
            $query->where(['id NOT IN' => $blocked]);
        }

        return $query->toArray();
    }

    /**
     * Lignes de formulaire membres (toutes les offres normales libres + membres actuels).
     *
     * @return list<array{offer_id:int,name:string,selected:bool,split_ratio_percent:int|null,display_order:int}>
     */
    private function buildMemberOfferRows(?OfferGroup $offerGroup): array
    {
        $exceptGroupId = $offerGroup?->id ? (int)$offerGroup->id : null;
        $currentMemberIds = [];
        $currentRatios = [];
        $currentOrders = [];

        if ($offerGroup && !empty($offerGroup->offer_group_members)) {
            foreach ($offerGroup->offer_group_members as $member) {
                $oid = (int)$member->offer_id;
                $currentMemberIds[] = $oid;
                $currentRatios[$oid] = $member->split_ratio_percent !== null
                    ? (int)$member->split_ratio_percent
                    : null;
                $currentOrders[$oid] = (int)$member->display_order;
            }
        }

        $usedMixedIds = $this->OfferGroups->find()
            ->select(['mixed_offer_id']);
        if ($exceptGroupId) {
            $usedMixedIds->where(['id !=' => $exceptGroupId]);
        }
        $usedMixedIds = $usedMixedIds->all()->extract('mixed_offer_id')->toList();

        $usedMemberIds = $this->OfferGroups->OfferGroupMembers->find()
            ->select(['offer_id']);
        if ($exceptGroupId) {
            $usedMemberIds->where(['offer_group_id !=' => $exceptGroupId]);
        }
        $usedMemberIds = $usedMemberIds->all()->extract('offer_id')->toList();

        $blocked = array_values(array_unique(array_merge($usedMixedIds, $usedMemberIds)));
        // Ne pas bloquer les membres du groupe en cours d'édition
        $blocked = array_values(array_diff($blocked, $currentMemberIds));
        // Ne pas proposer l'offre mixte du groupe comme membre
        if ($offerGroup && $offerGroup->mixed_offer_id) {
            $blocked[] = (int)$offerGroup->mixed_offer_id;
        }

        $query = $this->fetchTable('Offers')->find()
            ->select(['id', 'name'])
            ->where(['offer_type' => 'normal'])
            ->orderBy(['name' => 'ASC']);
        if ($blocked !== []) {
            $query->where(['id NOT IN' => $blocked]);
        }

        $rows = [];
        $i = 0;
        foreach ($query->all() as $offer) {
            $oid = (int)$offer->id;
            $rows[] = [
                'offer_id' => $oid,
                'name' => (string)$offer->name,
                'selected' => in_array($oid, $currentMemberIds, true),
                'split_ratio_percent' => $currentRatios[$oid] ?? null,
                'display_order' => $currentOrders[$oid] ?? $i,
            ];
            $i++;
        }

        // Trier : sélectionnés d'abord par display_order, puis le reste alpha
        usort($rows, static function (array $a, array $b): int {
            if ($a['selected'] !== $b['selected']) {
                return $a['selected'] ? -1 : 1;
            }
            if ($a['selected'] && $b['selected']) {
                return $a['display_order'] <=> $b['display_order'];
            }

            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }
}
