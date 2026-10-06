<?php
declare(strict_types=1);

namespace App\Service\OfferColors;

use App\Model\Entity\OfferColorMetadata;
use App\Model\Table\OfferColorFamiliesTable;
use App\Model\Table\OfferColorMetadataTable;
use App\Model\Table\OfferColorPresetsTable;
use Cake\Database\Connection;
use Cake\ORM\TableRegistry;

/**
 * Ouvre, enregistre et applique une palette, avec le jeton du brouillon.
 */
class OfferColorBoard
{
    public function open(int $presetId, array $data): BoardOutcome
    {
        $presets = $this->presets();
        $preset = $presets->get($presetId);
        $outcome = BoardOutcome::error('La palette n\'a pas pu être ouverte.');
        $committed = $this->connection()->transactional(function () use ($data, $preset, $presets, &$outcome) {
            $row = $this->metadata()->lock();
            if (!$this->accepted($row, $data)) {
                $outcome = BoardOutcome::error(
                    'Le rangement a été modifié par quelqu\'un d\'autre. La palette n\'a pas été ouverte.',
                );

                return true;
            }
            $error = $this->families()->replaceArrangement($presets->arrangementPayload((int)$preset->id));
            if ($error !== null) {
                $outcome = BoardOutcome::error($error);

                return false;
            }
            $this->bump($row, (int)$preset->id, true);
            $outcome = BoardOutcome::ok('La palette « ' . $preset->name . ' » est ouverte.');

            return true;
        });
        if ($committed === false) {
            return $outcome;
        }

        return $outcome;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): BoardOutcome
    {
        $prepared = $this->prepareSave($data);
        if ($prepared instanceof BoardOutcome) {
            return $prepared;
        }

        $outcome = BoardOutcome::error('La palette n\'a pas pu être enregistrée.');
        $committed = $this->connection()->transactional(function () use ($data, $prepared, &$outcome) {
            $row = $this->metadata()->lock();
            if (!$this->accepted($row, $data)) {
                $outcome = BoardOutcome::conflict((int)$row->revision);

                return true;
            }
            $stored = $this->presets()->storeSnapshot(
                $prepared['presetId'],
                $prepared['asNew'],
                $prepared['name'],
                $prepared['items'],
            );
            if ($stored === 'missing') {
                $outcome = BoardOutcome::missing();

                return true;
            }
            if (is_string($stored)) {
                $outcome = BoardOutcome::error($stored);

                return false;
            }
            $error = $this->families()->replaceArrangement($prepared['families']);
            if ($error !== null) {
                $outcome = BoardOutcome::error($error);

                return false;
            }
            $this->bump($row, (int)$stored->id, true);
            $outcome = BoardOutcome::ok('La palette « ' . $stored->name . ' » a été enregistrée.');

            return true;
        });
        if ($committed === false) {
            return $outcome;
        }

        return $outcome;
    }

    public function applyPreset(int $presetId, array $data): BoardOutcome
    {
        $presets = $this->presets();
        $preset = $presets->get($presetId);
        $outcome = BoardOutcome::error('La palette n\'a pas pu être appliquée.');
        $committed = $this->connection()->transactional(function () use ($data, $preset, $presets, &$outcome) {
            $row = $this->metadata()->lock();
            if (!$this->accepted($row, $data)) {
                $outcome = BoardOutcome::error(
                    'Le rangement a été modifié par quelqu\'un d\'autre. La palette n\'a pas été appliquée.',
                );

                return true;
            }
            $presets->restore((int)$preset->id);
            $error = $this->families()->replaceArrangement($presets->arrangementPayload((int)$preset->id));
            if ($error !== null) {
                $outcome = BoardOutcome::error($error);

                return false;
            }
            $this->bump($row, (int)$preset->id, true);
            $outcome = BoardOutcome::ok('La palette « ' . $preset->name . ' » a été appliquée au planning.');

            return true;
        });
        if ($committed === false) {
            return $outcome;
        }

        return $outcome;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{presetId:?int,asNew:bool,name:string,items:list<array<string,mixed>>,families:array<mixed>}|BoardOutcome
     */
    private function prepareSave(array $data): array|BoardOutcome
    {
        $name = trim((string)($data['name'] ?? ''));
        $nameError = $this->nameError($name);
        if ($nameError !== null) {
            return BoardOutcome::error($nameError);
        }

        $asNew = !empty($data['save_as_new']);
        $presetId = $this->presetId($data);
        if (!$asNew && $presetId === null) {
            return BoardOutcome::error('Aucune palette n\'est ouverte.');
        }

        $families = is_array($data['families'] ?? null) ? $data['families'] : [];
        $parsed = $this->families()->parsedArrangement($families);
        if (is_string($parsed)) {
            return BoardOutcome::error($parsed);
        }

        $unassigned = is_array($data['unassigned'] ?? null) ? $data['unassigned'] : [];
        $items = $this->itemsFromBoard($parsed, $families, $unassigned);
        if (is_string($items)) {
            return BoardOutcome::error($items);
        }

        return [
            'presetId' => $presetId,
            'asNew' => $asNew,
            'name' => $name,
            'items' => $items,
            'families' => $families,
        ];
    }

    /**
     * Copie une palette enregistrée sous un nouveau nom et l'ouvre.
     * N'écrit pas le planning.
     *
     * @param array<string, mixed> $data
     */
    public function createFromPreset(int $sourceId, array $data): BoardOutcome
    {
        $name = trim((string)($data['name'] ?? ''));
        $nameError = $this->nameError($name);
        if ($nameError !== null) {
            return BoardOutcome::error($nameError);
        }

        $presets = $this->presets();
        if (!$presets->exists(['id' => $sourceId])) {
            return BoardOutcome::error('La palette de départ n\'existe plus.');
        }

        $items = $presets->itemRows($sourceId);
        $outcome = BoardOutcome::error('La palette n\'a pas pu être créée.');
        $committed = $this->connection()->transactional(function () use ($data, $name, $items, $presets, &$outcome) {
            $row = $this->metadata()->lock();
            if (!$this->accepted($row, $data)) {
                $outcome = BoardOutcome::error(
                    'Le rangement a été modifié par quelqu\'un d\'autre. La palette n\'a pas été créée.',
                );

                return true;
            }
            $stored = $presets->storeSnapshot(null, true, $name, $items);
            if (is_string($stored)) {
                $outcome = BoardOutcome::error($stored);

                return false;
            }
            $error = $this->families()->replaceArrangement($presets->arrangementPayload((int)$stored->id));
            if ($error !== null) {
                $outcome = BoardOutcome::error($error);

                return false;
            }
            $this->bump($row, (int)$stored->id, true);
            $outcome = BoardOutcome::ok('La palette « ' . $stored->name . ' » a été créée.');

            return true;
        });
        if ($committed === false) {
            return $outcome;
        }

        return $outcome;
    }

    private function nameError(string $name): ?string
    {
        if ($name === '') {
            return 'Le nom est obligatoire.';
        }
        if (mb_strlen($name) > 255) {
            return 'Le nom est trop long.';
        }

        return null;
    }

    /**
     * @param list<array{name:string,position:int,hue:int|null,pastel:bool,offer_ids:list<int>}> $parsed
     * @param array<mixed> $families
     * @param array<mixed> $unassigned
     * @return list<array<string, mixed>>|string
     */
    private function itemsFromBoard(array $parsed, array $families, array $unassigned): array|string
    {
        $rawFamilies = array_values($families);
        if (count($rawFamilies) !== count($parsed)) {
            return 'Les couleurs affichées sont incomplètes.';
        }

        $decorated = [];
        foreach ($parsed as $index => $family) {
            $rawColors = $rawFamilies[$index]['colors'] ?? null;
            $colors = [];
            if ($family['offer_ids'] !== []) {
                if (!is_array($rawColors) || count($rawColors) !== count($family['offer_ids'])) {
                    return 'Les couleurs affichées sont incomplètes.';
                }
                foreach ($family['offer_ids'] as $offerIndex => $offerId) {
                    $hex = strtolower(trim((string)$rawColors[$offerIndex]));
                    if (preg_match('/^#[0-9a-f]{6}$/', $hex) !== 1) {
                        return 'Une couleur affichée est invalide.';
                    }
                    $colors[$offerId] = $hex;
                }
            }
            $family['colors'] = $colors;
            $decorated[] = $family;
        }

        usort($decorated, function (array $left, array $right): int {
            return $left['position'] <=> $right['position'];
        });

        $items = [];
        $seen = [];
        $order = 0;
        foreach ($decorated as $family) {
            $position = 0;
            foreach ($family['offer_ids'] as $offerId) {
                $items[] = [
                    'offer_id' => $offerId,
                    'color' => $family['colors'][$offerId],
                    'display_order' => $order,
                    'family_name' => $family['name'],
                    'family_position' => $family['position'],
                    'hue' => $family['hue'],
                    'pastel' => $family['pastel'],
                    'position' => $position,
                ];
                $seen[$offerId] = true;
                $order++;
                $position++;
            }
        }

        $offersById = $this->offersById();
        $tail = [];
        $tailColors = [];
        foreach (array_values($unassigned) as $row) {
            if (!is_array($row)) {
                return 'Une couleur affichée est invalide.';
            }
            $offerId = filter_var($row['offer_id'] ?? null, FILTER_VALIDATE_INT);
            if ($offerId === false || $offerId <= 0 || isset($seen[$offerId]) || !isset($offersById[$offerId])) {
                continue;
            }
            $hex = strtolower(trim((string)($row['color'] ?? '')));
            if (preg_match('/^#[0-9a-f]{6}$/', $hex) !== 1) {
                return 'Une couleur affichée est invalide.';
            }
            $tail[] = $offersById[$offerId];
            $tailColors[$offerId] = $hex;
            $seen[$offerId] = true;
        }
        usort($tail, function ($left, $right): int {
            $displayOrder = ((int)$left->display_order) <=> ((int)$right->display_order);
            if ($displayOrder !== 0) {
                return $displayOrder;
            }
            $name = strcmp((string)$left->name, (string)$right->name);
            if ($name !== 0) {
                return $name;
            }

            return ((int)$left->id) <=> ((int)$right->id);
        });
        foreach ($tail as $offer) {
            $offerId = (int)$offer->id;
            $items[] = [
                'offer_id' => $offerId,
                'color' => $tailColors[$offerId],
                'display_order' => $order,
                'family_name' => null,
                'family_position' => null,
                'hue' => null,
                'pastel' => false,
                'position' => null,
            ];
            $order++;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function accepted(OfferColorMetadata $row, array $data): bool
    {
        $current = (int)$row->revision;
        if (
            array_key_exists('overwrite_revision', $data)
            && $data['overwrite_revision'] !== ''
            && $data['overwrite_revision'] !== null
        ) {
            return (int)$data['overwrite_revision'] === $current;
        }

        return (int)($data['revision'] ?? -1) === $current;
    }

    private function bump(OfferColorMetadata $row, ?int $presetId, bool $touchPreset): void
    {
        $row->revision = (int)$row->revision + 1;
        if ($touchPreset) {
            $row->preset_id = $presetId;
        }
        $this->metadata()->saveOrFail($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function presetId(array $data): ?int
    {
        if (!isset($data['preset_id']) || $data['preset_id'] === '' || $data['preset_id'] === null) {
            return null;
        }

        return (int)$data['preset_id'];
    }

    /**
     * @return array<int, \App\Model\Entity\Offer>
     */
    private function offersById(): array
    {
        $offers = TableRegistry::getTableLocator()->get('Offers')->find()
            ->select(['id', 'name', 'display_order'])
            ->all();
        $byId = [];
        foreach ($offers as $offer) {
            $byId[(int)$offer->id] = $offer;
        }

        return $byId;
    }

    private function connection(): Connection
    {
        return $this->families()->getConnection();
    }

    private function metadata(): OfferColorMetadataTable
    {
        $table = TableRegistry::getTableLocator()->get('OfferColorMetadata');
        if (!$table instanceof OfferColorMetadataTable) {
            throw new \RuntimeException('Table offer_color_metadata indisponible.');
        }

        return $table;
    }

    private function presets(): OfferColorPresetsTable
    {
        $table = TableRegistry::getTableLocator()->get('OfferColorPresets');
        if (!$table instanceof OfferColorPresetsTable) {
            throw new \RuntimeException('Table offer_color_presets indisponible.');
        }

        return $table;
    }

    private function families(): OfferColorFamiliesTable
    {
        $table = TableRegistry::getTableLocator()->get('OfferColorFamilies');
        if (!$table instanceof OfferColorFamiliesTable) {
            throw new \RuntimeException('Table offer_color_families indisponible.');
        }

        return $table;
    }
}
