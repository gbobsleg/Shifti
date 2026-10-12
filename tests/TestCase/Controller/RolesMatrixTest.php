<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Authorization\LoginAsTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RolesMatrixTest extends TestCase
{
    use IntegrationTestTrait;
    use LoginAsTrait;

    protected array $fixtures = [
        'app.Regions',
        'app.Sites',
        'app.Roles',
        'app.Users',
    ];

    /**
     * L'admin voit la matrice et les droits du planificateur.
     *
     * @return void
     */
    public function testAdminSeesMatrix(): void
    {
        $this->loginAs(1);
        $this->get('/roles');
        $this->assertResponseOk();
        $this->assertResponseContains('Droits par rôle');
        $this->assertResponseContains('Planificateur');
        $this->assertResponseContains('Lancer et retravailler les générations de planning');
    }

    /**
     * La fiche agent n'affiche que ses droits, pas les groupes vides.
     *
     * @return void
     */
    public function testRoleViewListsGrantedCapabilities(): void
    {
        $this->loginAs(1);
        $this->get('/roles/view/3');
        $this->assertResponseOk();
        $this->assertResponseContains('Consulter la grille de planning');
        $this->assertResponseNotContains('Publier une prévision');
        $this->assertResponseNotContains('Prévisions et historique');
    }

    /**
     * La priorité d'un rôle codé n'est pas modifiable par le formulaire.
     *
     * @return void
     */
    public function testEditIgnoresPriorityOfCodedRole(): void
    {
        $this->loginAs(1);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/roles/edit/2', ['name' => 'Manager renommé', 'priority' => 5]);
        $this->assertRedirect(['controller' => 'Roles', 'action' => 'index']);

        $role = $this->getTableLocator()->get('Roles')->get(2);
        $this->assertSame('Manager renommé', $role->name);
        $this->assertSame(30, (int)$role->priority);
    }

    /**
     * Les rôles sont définis par le code : ni création ni suppression par l'interface.
     *
     * @return void
     */
    public function testRolesCannotBeAddedOrDeleted(): void
    {
        $this->loginAs(1);
        $this->get('/roles/add');
        $this->assertResponseCode(404);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/roles/delete/4');
        $this->assertResponseCode(404);
        $this->assertNotNull($this->getTableLocator()->get('Roles')->find()->where(['id' => 4])->first());
    }

    /**
     * Le manager n'a pas le droit de consulter les rôles.
     *
     * @return void
     */
    public function testManagerCannotSeeRoles(): void
    {
        $this->loginAs(2);
        $this->get('/roles');
        $this->assertResponseCode(403);
    }
}
