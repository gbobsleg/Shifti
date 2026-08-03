<?php
namespace App\View\Helper;
use Cake\View\Helper;

class DayOfWeekHelper extends Helper
{
    private $days = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
        5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'
    ];

    public function format(int $dayNumber): string
    {
        return $this->days[$dayNumber] ?? 'Inconnu';
    }
}
