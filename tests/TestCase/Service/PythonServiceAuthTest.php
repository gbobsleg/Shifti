<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PythonServiceAuth;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

class PythonServiceAuthTest extends TestCase
{
    public function testHeadersVidesSansJeton(): void
    {
        Configure::write('PythonServices.token', '');
        $this->assertSame([], PythonServiceAuth::headers());
    }

    public function testHeadersAvecJeton(): void
    {
        Configure::write('PythonServices.token', 'abc123');
        $this->assertSame(['X-Shifti-Token' => 'abc123'], PythonServiceAuth::headers());
    }
}
