<?php
declare(strict_types=1);

namespace App\Controller;

use App\Authorization\Capability;
use App\Resource\DataExportsResource;
use App\Service\Authorization\PermissionService;
use App\Service\Export\DataExportService;
use App\Service\Export\ExportRequest;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Response;
use DateTimeImmutable;
use InvalidArgumentException;
use Laminas\Diactoros\Stream;
use RuntimeException;

/**
 * Atelier d'export CSV : réel, scénario, prévision publiée.
 */
class DataExportsController extends AppController
{
    /**
     * @var list<array{id: int, name: string, start: string, end: string, offers: list<int>}>|null
     */
    private ?array $completedScenarioCache = null;

    public function initialize(): void
    {
        parent::initialize();

        try {
            $this->loadComponent('DebugKit.DebugKit');
            $this->DebugKit->disable();
        } catch (\Exception $e) {
        }
    }

    public function index(): void
    {
        $this->Authorization->authorize(new DataExportsResource(), 'index');
        $canScenario = $this->canManageForecasts();
        $offers = $this->offersForForm($canScenario);
        $scenarios = $canScenario ? $this->completedScenarios() : [];
        $filters = $this->filtersFromQuery();
        if (!$canScenario && $filters['type'] === 'scenario') {
            $filters['type'] = 'actual';
        }

        $this->set(compact('canScenario', 'offers', 'scenarios', 'filters'));
    }

    public function download(): Response
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize(new DataExportsResource(), 'download');
        set_time_limit(0);

        $data = $this->request->getData();
        if ((string)($data['type'] ?? '') === 'scenario' && !$this->canManageForecasts()) {
            throw new ForbiddenException();
        }

        try {
            $exportRequest = $this->buildRequest(is_array($data) ? $data : []);
            $service = new DataExportService();
            $handle = $service->exportToTempStream($exportRequest);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $this->Flash->error($exception->getMessage());

            return $this->redirect(['action' => 'index', '?' => $this->redirectQuery($data)]);
        }

        if ($handle === null) {
            $this->Flash->warning('Aucune donnée pour cette sélection.');

            return $this->redirect(['action' => 'index', '?' => $this->redirectQuery($data)]);
        }

        return $this->response
            ->withType('csv')
            ->withDownload($service->filename($exportRequest))
            ->withBody(new Stream($handle));
    }

    private function canManageForecasts(): bool
    {
        return (new PermissionService())->has(
            $this->request->getAttribute('identity'),
            Capability::PREVISION_GERER
        );
    }

    /**
     * @return array{type: string, scenario_id: string, start_date: string, end_date: string, granularity: string, offers: list<int>}
     */
    private function filtersFromQuery(): array
    {
        return [
            'type' => (string)$this->request->getQuery('type', 'actual'),
            'scenario_id' => (string)$this->request->getQuery('scenario_id', ''),
            'start_date' => (string)$this->request->getQuery('start_date', ''),
            'end_date' => (string)$this->request->getQuery('end_date', ''),
            'granularity' => (string)$this->request->getQuery('granularity', '15min'),
            'offers' => $this->normalizeOfferIds($this->request->getQuery('offers')),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildRequest(array $data): ExportRequest
    {
        $type = (string)($data['type'] ?? '');
        if (!in_array($type, ['actual', 'scenario', 'published'], true)) {
            throw new InvalidArgumentException('Type d\'export inconnu.');
        }
        $granularity = (string)($data['granularity'] ?? '');
        if (!in_array($granularity, ['15min', 'hour', 'day'], true)) {
            throw new InvalidArgumentException('Granularité inconnue.');
        }
        $start = $this->requireDate((string)($data['start_date'] ?? ''), 'début');
        $end = $this->requireDate((string)($data['end_date'] ?? ''), 'fin');
        if ($end < $start) {
            throw new InvalidArgumentException('La date de fin est avant la date de début.');
        }
        if ($start->diff($end)->days > 365) {
            throw new InvalidArgumentException('La période ne peut pas dépasser 366 jours.');
        }

        $offerIds = $this->normalizeOfferIds($data['offers'] ?? []);
        if ($offerIds === []) {
            throw new InvalidArgumentException('Sélectionnez au moins une offre.');
        }

        $scenarioId = null;
        if ($type === 'scenario') {
            $scenarioId = (int)($data['scenario_id'] ?? 0);
            $scenario = $this->fetchTable('ForecastScenarios')->find()
                ->select(['id', 'status', 'start_date', 'end_date'])
                ->where(['id' => $scenarioId])
                ->first();
            if ($scenario === null || (string)$scenario->status !== 'completed') {
                throw new InvalidArgumentException('Choisissez un scénario terminé.');
            }
            $boundsStart = new DateTimeImmutable($scenario->start_date->format('Y-m-d'));
            $boundsEnd = new DateTimeImmutable($scenario->end_date->format('Y-m-d'));
            if ($start < $boundsStart) {
                $start = $boundsStart;
            }
            if ($end > $boundsEnd) {
                $end = $boundsEnd;
            }
            if ($start > $end) {
                throw new InvalidArgumentException('La période est en dehors du scénario.');
            }
            $linked = $this->linkedOfferIds($scenarioId);
            if (array_diff($offerIds, $linked) !== []) {
                throw new InvalidArgumentException('Une offre sélectionnée n\'appartient pas à ce scénario.');
            }
        } else {
            $allowed = $this->fetchTable('Offers')->find()
                ->select(['id'])
                ->where(['id IN' => $offerIds, 'is_forecastable' => true])
                ->all()
                ->extract('id')
                ->toList();
            $allowed = array_map('intval', $allowed);
            if (array_diff($offerIds, $allowed) !== []) {
                throw new InvalidArgumentException('Une offre sélectionnée n\'est pas prévisible.');
            }
        }

        return new ExportRequest(
            $type,
            $offerIds,
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $granularity,
            $scenarioId,
        );
    }

    /**
     * @return list<int>
     */
    private function linkedOfferIds(int $scenarioId): array
    {
        $rows = $this->fetchTable('ForecastScenariosOffers')->find()
            ->select(['offer_id'])
            ->where(['scenario_id' => $scenarioId])
            ->disableHydration()
            ->all();
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['offer_id'];
        }

        return $ids;
    }

    private function requireDate(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Date de ' . $label . ' invalide.');
        }

        return $date;
    }

    /**
     * @return list<array{id: int, name: string, forecastable: bool}>
     */
    private function offersForForm(bool $canScenario): array
    {
        $offers = $this->fetchTable('Offers');
        $rows = $offers->find()
            ->select(['id', 'name', 'is_forecastable'])
            ->where(['is_forecastable' => true])
            ->disableHydration()
            ->all()
            ->toList();

        if ($canScenario) {
            $linkedIds = [];
            foreach ($this->completedScenarios() as $scenario) {
                foreach ($scenario['offers'] as $offerId) {
                    $linkedIds[$offerId] = $offerId;
                }
            }
            $known = [];
            foreach ($rows as $row) {
                $known[(int)$row['id']] = true;
            }
            $missing = array_values(array_diff($linkedIds, array_keys($known)));
            if ($missing !== []) {
                $extra = $offers->find()
                    ->select(['id', 'name', 'is_forecastable'])
                    ->where(['id IN' => $missing])
                    ->disableHydration()
                    ->all()
                    ->toList();
                $rows = array_merge($rows, $extra);
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'forecastable' => (bool)$row['is_forecastable'],
            ];
        }
        usort($list, static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return $list;
    }

    /**
     * @return list<array{id: int, name: string, start: string, end: string, offers: list<int>}>
     */
    private function completedScenarios(): array
    {
        if ($this->completedScenarioCache !== null) {
            return $this->completedScenarioCache;
        }

        $rows = $this->fetchTable('ForecastScenarios')->find()
            ->select(['id', 'name', 'start_date', 'end_date'])
            ->where(['status' => 'completed'])
            ->contain(['ForecastScenariosOffers'])
            ->orderBy(['name' => 'ASC'])
            ->all();

        $scenarios = [];
        foreach ($rows as $row) {
            $offerIds = [];
            foreach ($row->forecast_scenarios_offers as $link) {
                $offerIds[] = (int)$link->offer_id;
            }
            $scenarios[] = [
                'id' => (int)$row->id,
                'name' => (string)$row->name,
                'start' => $row->start_date->format('Y-m-d'),
                'end' => $row->end_date->format('Y-m-d'),
                'offers' => $offerIds,
            ];
        }

        return $this->completedScenarioCache = $scenarios;
    }

    /**
     * @return list<int>
     */
    private function normalizeOfferIds(mixed $offerIds): array
    {
        if (!is_array($offerIds)) {
            return [];
        }
        $ids = [];
        foreach ($offerIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $data
     * @return array<string, mixed>
     */
    private function redirectQuery(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        return [
            'type' => (string)($data['type'] ?? ''),
            'scenario_id' => (string)($data['scenario_id'] ?? ''),
            'start_date' => (string)($data['start_date'] ?? ''),
            'end_date' => (string)($data['end_date'] ?? ''),
            'granularity' => (string)($data['granularity'] ?? ''),
            'offers' => $this->normalizeOfferIds($data['offers'] ?? []),
        ];
    }
}
