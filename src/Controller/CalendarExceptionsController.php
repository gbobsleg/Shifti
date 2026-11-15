<?php
declare(strict_types=1);

namespace App\Controller;

use App\Resource\CalendarExceptionsResource;
use App\Service\OperatingCalendarService;

class CalendarExceptionsController extends AppController
{
    public function index()
    {
        $this->Authorization->authorize(new CalendarExceptionsResource(), 'index');
        $year = (int)($this->request->getQuery('year') ?: date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }
        $exceptions = $this->fetchTable('CalendarExceptions')->find()
            ->where([
                'date >=' => sprintf('%04d-01-01', $year),
                'date <=' => sprintf('%04d-12-31', $year),
            ])
            ->orderAsc('date')
            ->all();
        $this->set(compact('exceptions', 'year'));
    }

    public function add()
    {
        $this->Authorization->authorize(new CalendarExceptionsResource(), 'add');
        $table = $this->fetchTable('CalendarExceptions');
        $exception = $table->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['source'] = 'manual';
            $data['is_open'] = !empty($data['is_open']);
            $exception = $table->patchEntity($exception, $data);
            if ($table->save($exception)) {
                $this->Flash->success('La date a été enregistrée.');

                return $this->redirect(['action' => 'index', '?' => ['year' => $this->yearOf($exception->date)]]);
            }
            $this->Flash->error('La date n\'a pas pu être enregistrée.');
        }
        $this->set(compact('exception'));
    }

    public function edit($id = null)
    {
        $this->Authorization->authorize(new CalendarExceptionsResource(), 'edit');
        $table = $this->fetchTable('CalendarExceptions');
        $exception = $table->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            $data['is_open'] = !empty($data['is_open']);
            unset($data['source'], $data['date']);
            $exception = $table->patchEntity($exception, $data, [
                'fields' => ['label', 'is_open'],
            ]);
            if ($table->save($exception)) {
                $this->Flash->success('La date a été mise à jour.');

                return $this->redirect(['action' => 'index', '?' => ['year' => $this->yearOf($exception->date)]]);
            }
            $this->Flash->error('La date n\'a pas pu être mise à jour.');
        }
        $this->set(compact('exception'));
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $this->Authorization->authorize(new CalendarExceptionsResource(), 'delete');
        $table = $this->fetchTable('CalendarExceptions');
        $exception = $table->get($id);
        $year = $this->yearOf($exception->date);
        if ((string)$exception->source !== 'manual') {
            $this->Flash->error('Un férié légal ne se supprime pas. Utilisez Ouvrir pour le travailler, ou laissez-le fermé.');

            return $this->redirect(['action' => 'index', '?' => ['year' => $year]]);
        }
        if ($table->delete($exception)) {
            $this->Flash->success('La fermeture manuelle a été supprimée.');
        } else {
            $this->Flash->error('Suppression impossible.');
        }

        return $this->redirect(['action' => 'index', '?' => ['year' => $year]]);
    }

    public function toggle($id = null)
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize(new CalendarExceptionsResource(), 'toggle');
        $table = $this->fetchTable('CalendarExceptions');
        $exception = $table->get($id);
        $exception->is_open = !$exception->is_open;
        $year = $this->yearOf($exception->date);
        if ($table->save($exception)) {
            $this->Flash->success($exception->is_open ? 'Jour marqué ouvert.' : 'Jour marqué fermé.');
        } else {
            $this->Flash->error('Mise à jour impossible.');
        }

        return $this->redirect(['action' => 'index', '?' => ['year' => $year]]);
    }

    public function seed()
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize(new CalendarExceptionsResource(), 'seed');
        $year = (int)$this->request->getData('year');
        if ($year < 2000 || $year > 2100) {
            $this->Flash->error('Année invalide.');

            return $this->redirect(['action' => 'index']);
        }
        $result = (new OperatingCalendarService())->seedLegalRange($year, $year);
        $this->Flash->success(sprintf(
            '%d férié(s) ajouté(s), %d déjà présent(s) laissé(s) tels quels.',
            $result['inserted'],
            $result['skipped']
        ));

        return $this->redirect(['action' => 'index', '?' => ['year' => $year]]);
    }

    /**
     * Cake\I18n\Date n'implémente pas DateTimeInterface. format('Y') reste l'année ISO.
     */
    private function yearOf(mixed $date): string
    {
        if ($date instanceof \Cake\Chronos\ChronosDate || $date instanceof \DateTimeInterface) {
            return $date->format('Y');
        }

        return date('Y');
    }
}
