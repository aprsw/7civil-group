<?php

declare(strict_types=1);

use SevenCivilGroup\Core\Application;
use SevenCivilGroup\Core\Autoloader;
use SevenCivilGroup\Core\Config;
use SevenCivilGroup\Core\Container;
use SevenCivilGroup\Core\Http\Router;
use SevenCivilGroup\Domain\Repositories\DepartmentRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\EventRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterActionRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterAttachmentRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterRecipientRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\LetterRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\RoleRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\SettingsRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\TemplateRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\UserRepositoryInterface;
use SevenCivilGroup\Domain\Repositories\WorkflowRepositoryInterface;
use SevenCivilGroup\Http\Controllers\AttachmentController;
use SevenCivilGroup\Http\Controllers\AuthController;
use SevenCivilGroup\Http\Controllers\DashboardController;
use SevenCivilGroup\Http\Controllers\DepartmentController;
use SevenCivilGroup\Http\Controllers\LetterController;
use SevenCivilGroup\Http\Controllers\MetaController;
use SevenCivilGroup\Http\Controllers\NotificationController;
use SevenCivilGroup\Http\Controllers\RoleController;
use SevenCivilGroup\Http\Controllers\TemplateController;
use SevenCivilGroup\Http\Controllers\UserController;
use SevenCivilGroup\Http\Controllers\WorkflowController;
use SevenCivilGroup\Http\Middleware\AuthenticateMiddleware;
use SevenCivilGroup\Infrastructure\Database\Connection;
use SevenCivilGroup\Infrastructure\Repositories\PdoDepartmentRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoLetterActionRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoLetterRecipientRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoLetterRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoRoleRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoSettingsRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoTemplateRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoUserRepository;
use SevenCivilGroup\Infrastructure\Repositories\PdoWorkflowRepository;
use SevenCivilGroup\Security\BcryptHasher;
use SevenCivilGroup\Security\Contracts\HasherInterface;
use SevenCivilGroup\Security\Contracts\TokenServiceInterface;
use SevenCivilGroup\Security\JwtTokenService;
use SevenCivilGroup\Services\AccessControl;
use SevenCivilGroup\Services\AuthService;
use SevenCivilGroup\Services\DashboardService;
use SevenCivilGroup\Services\LetterAccess;
use SevenCivilGroup\Services\LetterService;
use SevenCivilGroup\Services\UserService;
use SevenCivilGroup\Services\WorkflowService;
use SevenCivilGroup\View\LetterTransformer;
use SevenCivilGroup\View\UserTransformer;
use SevenCivilGroup\View\WorkflowTransformer;

/**
 * Composition root. Wires abstractions to concrete implementations in one place
 * (Dependency Inversion). Returns a fully-built Application kernel.
 */

require __DIR__ . '/../src/Core/Autoloader.php';
(new Autoloader())->addNamespace('SevenCivilGroup', __DIR__ . '/../src')->register();

// Load backend/.env (if present) so configured values take effect at runtime.
\SevenCivilGroup\Core\Env::load(__DIR__ . '/../.env');

// All timestamps are Iran (Tehran) local time so stored and displayed times match.
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Tehran');

$container = new Container();
$uploadDir = __DIR__ . '/../storage/uploads';
$photoDir = __DIR__ . '/../public/uploads/photos';
$signatureDir = __DIR__ . '/../public/uploads/signatures';
$logoDir = __DIR__ . '/../public/uploads/logos';

// ── Configuration & database ─────────────────────────────────────────────
$container->singleton(Config::class, static fn () => Config::fromFile(__DIR__ . '/../config/config.php'));
$container->singleton(Connection::class, static fn (Container $c) => new Connection($c->get(Config::class)));

// ── Repositories (interface → PDO implementation) ────────────────────────
$container->singleton(RoleRepositoryInterface::class, static fn (Container $c) => new PdoRoleRepository($c->get(Connection::class)));
$container->singleton(DepartmentRepositoryInterface::class, static fn (Container $c) => new PdoDepartmentRepository($c->get(Connection::class)));
$container->singleton(UserRepositoryInterface::class, static fn (Container $c) => new PdoUserRepository(
    $c->get(Connection::class),
    $c->get(RoleRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
));
$container->singleton(SettingsRepositoryInterface::class, static fn (Container $c) => new PdoSettingsRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoOrgLogoRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Services\OrgIdentity::class, static fn (Container $c) => new \SevenCivilGroup\Services\OrgIdentity(
    $c->get(SettingsRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface::class),
));
$container->singleton(\SevenCivilGroup\Services\NumberingService::class, static fn (Container $c) => new \SevenCivilGroup\Services\NumberingService(
    $c->get(SettingsRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
));
$container->singleton(LetterRepositoryInterface::class, static fn (Container $c) => new PdoLetterRepository(
    $c->get(Connection::class),
    $c->get(\SevenCivilGroup\Services\NumberingService::class),
));
$container->singleton(LetterActionRepositoryInterface::class, static fn (Container $c) => new PdoLetterActionRepository($c->get(Connection::class)));
$container->singleton(LetterRecipientRepositoryInterface::class, static fn (Container $c) => new PdoLetterRecipientRepository($c->get(Connection::class)));
$container->singleton(LetterAttachmentRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoLetterAttachmentRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\LetterReadRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoLetterReadRepository($c->get(Connection::class)));
$container->singleton(EventRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoEventRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\UserNoteRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoUserNoteRepository($c->get(Connection::class)));
$container->bind(\SevenCivilGroup\Http\Controllers\UserNoteController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\UserNoteController($c->get(\SevenCivilGroup\Domain\Repositories\UserNoteRepositoryInterface::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\MessagingRuleRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoMessagingRuleRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\ChatRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoChatRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\FolderRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoFolderRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\LetterApproverRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoLetterApproverRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\LetterNoteRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoLetterNoteRepository($c->get(Connection::class)));
$container->singleton(WorkflowRepositoryInterface::class, static fn (Container $c) => new PdoWorkflowRepository($c->get(Connection::class)));
$container->singleton(TemplateRepositoryInterface::class, static fn (Container $c) => new PdoTemplateRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\OfficeFileRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoOfficeFileRepository($c->get(Connection::class)));

// ── Security ─────────────────────────────────────────────────────────────
$container->singleton(HasherInterface::class, static fn () => new BcryptHasher());
$container->singleton(TokenServiceInterface::class, static function (Container $c) {
    $config = $c->get(Config::class);

    return new JwtTokenService(
        (string) $config->get('auth.jwt_secret'),
        (int) $config->get('auth.jwt_ttl'),
        (string) $config->get('auth.issuer'),
    );
});

// ── Services ─────────────────────────────────────────────────────────────
$container->singleton(\SevenCivilGroup\Domain\Repositories\UserPermissionRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoUserPermissionRepository($c->get(Connection::class)));
$container->singleton(\SevenCivilGroup\Domain\Repositories\SecretariatFolderRepositoryInterface::class, static fn (Container $c) => new \SevenCivilGroup\Infrastructure\Repositories\PdoSecretariatFolderRepository($c->get(Connection::class)));
$container->singleton(AccessControl::class, static fn (Container $c) => new AccessControl($c->get(\SevenCivilGroup\Domain\Repositories\UserPermissionRepositoryInterface::class)));
$container->singleton(LetterAccess::class, static fn (Container $c) => new LetterAccess($c->get(LetterRecipientRepositoryInterface::class), $c->get(\SevenCivilGroup\Domain\Repositories\LetterApproverRepositoryInterface::class)));
$container->singleton(\SevenCivilGroup\Services\RecipientPolicy::class, static fn (Container $c) => new \SevenCivilGroup\Services\RecipientPolicy(
    $c->get(\SevenCivilGroup\Domain\Repositories\MessagingRuleRepositoryInterface::class),
));
$container->singleton(AuthService::class, static fn (Container $c) => new AuthService(
    $c->get(UserRepositoryInterface::class),
    $c->get(HasherInterface::class),
    $c->get(TokenServiceInterface::class),
));
$container->singleton(LetterService::class, static fn (Container $c) => new LetterService(
    $c->get(LetterRepositoryInterface::class),
    $c->get(WorkflowRepositoryInterface::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(LetterActionRepositoryInterface::class),
    $c->get(LetterRecipientRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Services\RecipientPolicy::class),
    $c->get(LetterAttachmentRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\LetterReadRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\LetterApproverRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\LetterNoteRepositoryInterface::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\FolderController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\FolderController(
    $c->get(\SevenCivilGroup\Domain\Repositories\FolderRepositoryInterface::class),
));
$container->singleton(WorkflowService::class, static fn (Container $c) => new WorkflowService(
    $c->get(WorkflowRepositoryInterface::class),
    $c->get(AccessControl::class),
    $c->get(RoleRepositoryInterface::class),
));
$container->singleton(DashboardService::class, static fn () => new DashboardService());
$container->singleton(UserService::class, static fn (Container $c) => new UserService(
    $c->get(UserRepositoryInterface::class),
    $c->get(RoleRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(HasherInterface::class),
));

// ── View transformers ────────────────────────────────────────────────────
$container->singleton(UserTransformer::class, static fn (Container $c) => new UserTransformer($c->get(\SevenCivilGroup\Domain\Repositories\UserPermissionRepositoryInterface::class)));
$container->singleton(LetterTransformer::class, static fn (Container $c) => new LetterTransformer($c->get(WorkflowRepositoryInterface::class), $c->get(RoleRepositoryInterface::class)));
$container->singleton(WorkflowTransformer::class, static fn () => new WorkflowTransformer());

// ── Middleware ───────────────────────────────────────────────────────────
$container->singleton(AuthenticateMiddleware::class, static fn (Container $c) => new AuthenticateMiddleware(
    $c->get(AuthService::class),
    $c->get(UserRepositoryInterface::class),
));

// ── Controllers ──────────────────────────────────────────────────────────
$container->bind(AuthController::class, static fn (Container $c) => new AuthController($c->get(AuthService::class), $c->get(UserTransformer::class)));
$container->bind(DashboardController::class, static fn (Container $c) => new DashboardController(
    $c->get(DashboardService::class),
    $c->get(LetterRepositoryInterface::class),
    $c->get(LetterTransformer::class),
    $c->get(LetterAccess::class),
    $c->get(LetterService::class),
    $c->get(EventRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\UserNoteRepositoryInterface::class),
));
$container->bind(LetterController::class, static fn (Container $c) => new LetterController(
    $c->get(LetterRepositoryInterface::class),
    $c->get(LetterService::class),
    $c->get(LetterTransformer::class),
    $c->get(AccessControl::class),
    $c->get(LetterAccess::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(LetterAttachmentRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\FolderRepositoryInterface::class),
    $uploadDir,
));
$container->bind(UserController::class, static fn (Container $c) => new UserController(
    $c->get(UserRepositoryInterface::class),
    $c->get(UserTransformer::class),
    $c->get(UserService::class),
    $c->get(AccessControl::class),
));
$container->bind(DepartmentController::class, static fn (Container $c) => new DepartmentController(
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(RoleController::class, static fn (Container $c) => new RoleController(
    $c->get(RoleRepositoryInterface::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(WorkflowController::class, static fn (Container $c) => new WorkflowController(
    $c->get(WorkflowRepositoryInterface::class),
    $c->get(RoleRepositoryInterface::class),
    $c->get(WorkflowService::class),
    $c->get(WorkflowTransformer::class),
    $c->get(AccessControl::class),
    $c->get(UserRepositoryInterface::class),
));
$container->bind(TemplateController::class, static fn (Container $c) => new TemplateController(
    $c->get(TemplateRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(AttachmentController::class, static fn () => new AttachmentController($uploadDir));
$container->bind(\SevenCivilGroup\Http\Controllers\OfficeFileController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\OfficeFileController(
    $c->get(\SevenCivilGroup\Domain\Repositories\OfficeFileRepositoryInterface::class),
    $c->get(AccessControl::class),
    $uploadDir,
));
$container->bind(MetaController::class, static fn (Container $c) => new MetaController(
    $c->get(UserRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(WorkflowRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Services\RecipientPolicy::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\ProfileController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\ProfileController(
    $c->get(UserRepositoryInterface::class),
    $c->get(UserTransformer::class),
    $c->get(HasherInterface::class),
    $photoDir,
    $signatureDir,
));
$container->bind(\SevenCivilGroup\Http\Controllers\SettingsController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\SettingsController(
    $c->get(SettingsRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Services\NumberingService::class),
    $c->get(\SevenCivilGroup\Services\OrgIdentity::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\BrandingController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\BrandingController(
    $c->get(\SevenCivilGroup\Services\OrgIdentity::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\OrgLogoRepositoryInterface::class),
    $c->get(AccessControl::class),
    $logoDir,
));
$container->bind(\SevenCivilGroup\Http\Controllers\EventController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\EventController(
    $c->get(EventRepositoryInterface::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\MessagingController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\MessagingController(
    $c->get(\SevenCivilGroup\Domain\Repositories\MessagingRuleRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(RoleRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\SecretariatController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\SecretariatController(
    $c->get(LetterService::class),
    $c->get(LetterRepositoryInterface::class),
    $c->get(LetterTransformer::class),
    $c->get(LetterAccess::class),
    $c->get(AccessControl::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\SecretariatFolderRepositoryInterface::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\PermissionGrantController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\PermissionGrantController(
    $c->get(UserRepositoryInterface::class),
    $c->get(\SevenCivilGroup\Domain\Repositories\UserPermissionRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\SevenCivilGroup\Http\Controllers\ChatController::class, static fn (Container $c) => new \SevenCivilGroup\Http\Controllers\ChatController(
    $c->get(\SevenCivilGroup\Domain\Repositories\ChatRepositoryInterface::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(AccessControl::class),
    $uploadDir,
));
$container->bind(NotificationController::class, static fn (Container $c) => new NotificationController(
    $c->get(LetterRepositoryInterface::class),
    $c->get(LetterAccess::class),
    $c->get(LetterService::class),
));

// ── Router & kernel ──────────────────────────────────────────────────────
$container->singleton(Router::class, static function (Container $c) {
    $router = new Router($c);
    (require __DIR__ . '/../routes/api.php')($router);

    return $router;
});

$config = $container->get(Config::class);

// Self-heal the schema: after a deploy that adds tables, create any that are
// missing (non-destructive) on the first request. Cheap no-op once done; never
// blocks boot if the database is unreachable.
try {
    (new \SevenCivilGroup\Infrastructure\Database\Migrator($container->get(Connection::class)))
        ->ensureSchema(__DIR__ . '/../storage');
} catch (\Throwable $e) {
    // Ignore — a misconfigured/unreachable DB surfaces on the actual request.
}

return new Application(
    $container,
    $container->get(Router::class),
    (bool) $config->get('app.debug'),
    (string) $config->get('app.cors_origins', '*'),
);
