<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use Cake\TestSuite\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AuthorizationCoverageTest extends TestCase
{
    public function testEveryActionChecksAuthorization(): void
    {
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller';
        $skipped = ['AppController', 'ErrorController'];
        // login et logout sont publiques : AppController::beforeFilter appelle déjà skipAuthorization.
        $exemptActions = ['login', 'logout'];
        $ignored = ['initialize', 'beforeFilter', 'beforeRender', 'afterFilter'];
        $missing = [];

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*Controller.php') ?: [] as $file) {
            $short = basename($file, '.php');
            if (in_array($short, $skipped, true)) {
                continue;
            }
            $class = 'App\\Controller\\' . $short;
            $lines = file($file);
            if ($lines === false) {
                $this->fail('Lecture impossible : ' . $file);
            }
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                if (in_array($name, $ignored, true) || in_array($name, $exemptActions, true)) {
                    continue;
                }
                $body = implode('', array_slice(
                    $lines,
                    $method->getStartLine() - 1,
                    $method->getEndLine() - $method->getStartLine() + 1,
                ));
                if (!str_contains($body, 'Authorization->authorize(') && !str_contains($body, 'skipAuthorization(')) {
                    $missing[] = $short . '::' . $name;
                }
            }
        }

        $this->assertSame([], $missing, 'Actions sans vérification : ' . implode(', ', $missing));
    }
}
