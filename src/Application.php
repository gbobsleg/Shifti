<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App;

use App\Policy\RequestPolicy;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authorization\AuthorizationService;
use Authorization\AuthorizationServiceInterface;
use Authorization\AuthorizationServiceProviderInterface;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\MapResolver;
use Authorization\Policy\ResolverCollection;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\ServerRequest;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface, AuthorizationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        $this->addPlugin('BootstrapUI');

        // Call parent to load bootstrap from files.
        parent::bootstrap();

        if (PHP_SAPI === 'cli') {
            $this->bootstrapCli();
        } else {
            FactoryLocator::add(
                'Table',
                (new TableLocator())->allowFallbackClass(false),
            );
        }

        /*
         * Note: DebugKit is automatically loaded by parent::bootstrap() in development mode
         * Do not manually add it here to avoid "Plugin already loaded" error
         */

        // Load more plugins here
//        $this->addPlugin('Flash');
        $this->addPlugin('Ajax', ['bootstrap' => true]);
        // Note: CakeCharts plugin may not be compatible with CakePHP 5
        // $this->addPlugin('CakeCharts');
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error')))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance. For that when
            // creating the middleware instance specify the cache config name by
            // using it's second constructor argument:
            // `new RoutingMiddleware($this, '_cake_routes_')`
            ->add(new RoutingMiddleware($this))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware([
                'json' => true,
            ]))

            // Cross Site Request Forgery (CSRF) Protection Middleware
            // https://book.cakephp.org/4/en/controllers/middleware.html#cross-site-request-forgery-csrf-middleware
            ->add(new CsrfProtectionMiddleware([
                'httponly' => true,
            ]))
            ->add(new AuthenticationMiddleware($this))
            ->add(new AuthorizationMiddleware($this, [
                'requireAuthorizationCheck' => true,
            ]));

        return $middlewareQueue;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
    }

    /**
     * Configurer le service d'autorisation (AuthorizationPlugin)
     */
    public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
    {
        $resolver = new ResolverCollection([
            new MapResolver([
                ServerRequest::class => RequestPolicy::class,
                \App\Resource\PagesResource::class => \App\Policy\PagesPolicy::class,
                \App\Resource\OffersResource::class => \App\Policy\OffersPolicy::class,
                \App\Resource\UsersResource::class => \App\Policy\UsersPolicy::class,
                \App\Resource\AlertsResource::class => \App\Policy\AlertsPolicy::class,
                \App\Resource\AbsencesResource::class => \App\Policy\AbsencesPolicy::class,
                \App\Resource\GridsResource::class => \App\Policy\GridsPolicy::class,
                \App\Resource\SchedulesResource::class => \App\Policy\SchedulesPolicy::class,
                \App\Resource\ForecastScenariosResource::class => \App\Policy\ForecastScenariosPolicy::class,
                \App\Resource\RangesResource::class => \App\Policy\RangesPolicy::class,
                \App\Resource\SitesResource::class => \App\Policy\SitesPolicy::class,
                \App\Resource\RolesResource::class => \App\Policy\RolesPolicy::class,
                \App\Resource\RemoteWorkResource::class => \App\Policy\RemoteWorkPolicy::class,
                \App\Resource\RegionsResource::class => \App\Policy\RegionsPolicy::class,
                \App\Resource\SkillsResource::class => \App\Policy\SkillsPolicy::class,
                \App\Resource\UserAvailabilitiesResource::class => \App\Policy\UserAvailabilitiesPolicy::class,
                \App\Resource\FixedActivityRulesResource::class => \App\Policy\FixedActivityRulesPolicy::class,
                \App\Resource\RotationRulesResource::class => \App\Policy\RotationRulesPolicy::class,
                \App\Resource\DisplaySettingsResource::class => \App\Policy\DisplaySettingsPolicy::class,
                \App\Resource\HistoricalDataResource::class => \App\Policy\HistoricalDataPolicy::class,
                \App\Resource\ExcelUploadsResource::class => \App\Policy\ExcelUploadsPolicy::class,
                \App\Resource\PlanningEventMappingsResource::class => \App\Policy\PlanningEventMappingsPolicy::class,
                \App\Resource\PlanningGenerationJobsResource::class => \App\Policy\PlanningGenerationJobsPolicy::class,
                \App\Resource\BackgroundJobsResource::class => \App\Policy\BackgroundJobsPolicy::class,
            ]),
        ]);

        return new AuthorizationService($resolver);
    }

    /**
     * Configurer le service d'authentification
     */
    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $authenticationService = new AuthenticationService([
            'unauthenticatedRedirect' => Router::url('/users/login'),
            'queryParam' => 'redirect',
        ]);

        // Charge les identifiants - vérifie email et password
        $authenticationService->loadIdentifier('Authentication.Password', [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
        ]);

        // Charge les authenticators - session en premier
        $authenticationService->loadAuthenticator('Authentication.Session');

        // Configurer l'authentification par formulaire
        $authenticationService->loadAuthenticator('Authentication.Form', [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
            'loginUrl' => Router::url('/users/login'),
        ]);

        return $authenticationService;
    }

    /**
     * Bootstrapping for CLI application.
     *
     * That is when running commands.
     *
     * @return void
     */
    protected function bootstrapCli(): void
    {
        // Votre méthode ne devrait avoir AUCUN appel à $this->addPlugin() pour Bake et Migrations.

        // C'est ici que vous chargerez des plugins qui ne sont PAS destinés
        // à être chargés dans l'environnement Web, ou des plugins personnalisés.

        // Par exemple:
        // $this->addPlugin('MyCliTool');
    }

//    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
//    {
//        $authenticationService = new AuthenticationService([
//            'unauthenticatedRedirect' => Router::url('/users/login'),
//            'queryParam' => 'redirect',
//        ]);
//
//        // Charge les identifiants et s'assure que nous vérifions les champs e-mail et mot de passe
//        $authenticationService->loadIdentifier('Authentication.Password', [
//            'fields' => [
//                'username' => 'email',
//                'password' => 'password',
//            ]
//        ]);
//
//        // Charge les authenticators, nous voulons celui de session en premier
//        $authenticationService->loadAuthenticator('Authentication.Session');
//        // Configure la vérification des données du formulaire pour choisir l'email et le mot de passe
//        $authenticationService->loadAuthenticator('Authentication.Form', [
//            'fields' => [
//                'username' => 'email',
//                'password' => 'password',
//            ],
//            'loginUrl' => Router::url('/users/login'),
//        ]);
//
//        return $authenticationService;
//    }
}
