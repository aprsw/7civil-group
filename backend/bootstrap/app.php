<?php

declare(strict_types=1);

use ArtaPharma\Core\Application;
use ArtaPharma\Core\Autoloader;
use ArtaPharma\Core\Config;
use ArtaPharma\Core\Container;
use ArtaPharma\Core\Http\Router;
use ArtaPharma\Domain\Repositories\DepartmentRepositoryInterface;
use ArtaPharma\Domain\Repositories\EventRepositoryInterface;
use ArtaPharma\Domain\Repositories\LetterActionRepositoryInterface;
use ArtaPharma\Domain\Repositories\LetterAttachmentRepositoryInterface;
use ArtaPharma\Domain\Repositories\LetterRecipientRepositoryInterface;
use ArtaPharma\Domain\Repositories\LetterRepositoryInterface;
use ArtaPharma\Domain\Repositories\RoleRepositoryInterface;
use ArtaPharma\Domain\Repositories\SettingsRepositoryInterface;
use ArtaPharma\Domain\Repositories\TemplateRepositoryInterface;
use ArtaPharma\Domain\Repositories\UserRepositoryInterface;
use ArtaPharma\Domain\Repositories\WorkflowRepositoryInterface;
use ArtaPharma\Http\Controllers\AttachmentController;
use ArtaPharma\Http\Controllers\AuthController;
use ArtaPharma\Http\Controllers\DashboardController;
use ArtaPharma\Http\Controllers\DepartmentController;
use ArtaPharma\Http\Controllers\LetterController;
use ArtaPharma\Http\Controllers\MetaController;
use ArtaPharma\Http\Controllers\NotificationController;
use ArtaPharma\Http\Controllers\RoleController;
use ArtaPharma\Http\Controllers\TemplateController;
use ArtaPharma\Http\Controllers\UserController;
use ArtaPharma\Http\Controllers\WorkflowController;
use ArtaPharma\Http\Middleware\AuthenticateMiddleware;
use ArtaPharma\Infrastructure\Database\Connection;
use ArtaPharma\Infrastructure\Repositories\PdoDepartmentRepository;
use ArtaPharma\Infrastructure\Repositories\PdoLetterActionRepository;
use ArtaPharma\Infrastructure\Repositories\PdoLetterRecipientRepository;
use ArtaPharma\Infrastructure\Repositories\PdoLetterRepository;
use ArtaPharma\Infrastructure\Repositories\PdoRoleRepository;
use ArtaPharma\Infrastructure\Repositories\PdoSettingsRepository;
use ArtaPharma\Infrastructure\Repositories\PdoTemplateRepository;
use ArtaPharma\Infrastructure\Repositories\PdoUserRepository;
use ArtaPharma\Infrastructure\Repositories\PdoWorkflowRepository;
use ArtaPharma\Security\BcryptHasher;
use ArtaPharma\Security\Contracts\HasherInterface;
use ArtaPharma\Security\Contracts\TokenServiceInterface;
use ArtaPharma\Security\JwtTokenService;
use ArtaPharma\Services\AccessControl;
use ArtaPharma\Services\AuthService;
use ArtaPharma\Services\DashboardService;
use ArtaPharma\Services\LetterAccess;
use ArtaPharma\Services\LetterService;
use ArtaPharma\Services\UserService;
use ArtaPharma\Services\WorkflowService;
use ArtaPharma\View\LetterTransformer;
use ArtaPharma\View\UserTransformer;
use ArtaPharma\View\WorkflowTransformer;

/**
 * Composition root. Wires abstractions to concrete implementations in one place
 * (Dependency Inversion). Returns a fully-built Application kernel.
 */

require __DIR__ . '/../src/Core/Autoloader.php';
(new Autoloader())->addNamespace('ArtaPharma', __DIR__ . '/../src')->register();

// Load backend/.env (if present) so configured values take effect at runtime.
\ArtaPharma\Core\Env::load(__DIR__ . '/../.env');

// All timestamps are Iran (Tehran) local time so stored and displayed times match.
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Tehran');

$container = new Container();
$uploadDir = __DIR__ . '/../storage/uploads';
$photoDir = __DIR__ . '/../public/uploads/photos';
$signatureDir = __DIR__ . '/../public/uploads/signatures';

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
$container->singleton(\ArtaPharma\Services\NumberingService::class, static fn (Container $c) => new \ArtaPharma\Services\NumberingService(
    $c->get(SettingsRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
));
$container->singleton(LetterRepositoryInterface::class, static fn (Container $c) => new PdoLetterRepository(
    $c->get(Connection::class),
    $c->get(\ArtaPharma\Services\NumberingService::class),
));
$container->singleton(LetterActionRepositoryInterface::class, static fn (Container $c) => new PdoLetterActionRepository($c->get(Connection::class)));
$container->singleton(LetterRecipientRepositoryInterface::class, static fn (Container $c) => new PdoLetterRecipientRepository($c->get(Connection::class)));
$container->singleton(LetterAttachmentRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoLetterAttachmentRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\LetterReadRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoLetterReadRepository($c->get(Connection::class)));
$container->singleton(EventRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoEventRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\UserNoteRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoUserNoteRepository($c->get(Connection::class)));
$container->bind(\ArtaPharma\Http\Controllers\UserNoteController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\UserNoteController($c->get(\ArtaPharma\Domain\Repositories\UserNoteRepositoryInterface::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\MessagingRuleRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoMessagingRuleRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\ChatRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoChatRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\FolderRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoFolderRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\LetterApproverRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoLetterApproverRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\LetterNoteRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoLetterNoteRepository($c->get(Connection::class)));
$container->singleton(WorkflowRepositoryInterface::class, static fn (Container $c) => new PdoWorkflowRepository($c->get(Connection::class)));
$container->singleton(TemplateRepositoryInterface::class, static fn (Container $c) => new PdoTemplateRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\OfficeFileRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoOfficeFileRepository($c->get(Connection::class)));

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
$container->singleton(\ArtaPharma\Domain\Repositories\UserPermissionRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoUserPermissionRepository($c->get(Connection::class)));
$container->singleton(\ArtaPharma\Domain\Repositories\SecretariatFolderRepositoryInterface::class, static fn (Container $c) => new \ArtaPharma\Infrastructure\Repositories\PdoSecretariatFolderRepository($c->get(Connection::class)));
$container->singleton(AccessControl::class, static fn (Container $c) => new AccessControl($c->get(\ArtaPharma\Domain\Repositories\UserPermissionRepositoryInterface::class)));
$container->singleton(LetterAccess::class, static fn (Container $c) => new LetterAccess($c->get(LetterRecipientRepositoryInterface::class), $c->get(\ArtaPharma\Domain\Repositories\LetterApproverRepositoryInterface::class)));
$container->singleton(\ArtaPharma\Services\RecipientPolicy::class, static fn (Container $c) => new \ArtaPharma\Services\RecipientPolicy(
    $c->get(\ArtaPharma\Domain\Repositories\MessagingRuleRepositoryInterface::class),
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
    $c->get(\ArtaPharma\Services\RecipientPolicy::class),
    $c->get(LetterAttachmentRepositoryInterface::class),
    $c->get(\ArtaPharma\Domain\Repositories\LetterReadRepositoryInterface::class),
    $c->get(\ArtaPharma\Domain\Repositories\LetterApproverRepositoryInterface::class),
    $c->get(\ArtaPharma\Domain\Repositories\LetterNoteRepositoryInterface::class),
));
$container->bind(\ArtaPharma\Http\Controllers\FolderController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\FolderController(
    $c->get(\ArtaPharma\Domain\Repositories\FolderRepositoryInterface::class),
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
$container->singleton(UserTransformer::class, static fn (Container $c) => new UserTransformer($c->get(\ArtaPharma\Domain\Repositories\UserPermissionRepositoryInterface::class)));
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
    $c->get(\ArtaPharma\Domain\Repositories\UserNoteRepositoryInterface::class),
));
$container->bind(LetterController::class, static fn (Container $c) => new LetterController(
    $c->get(LetterRepositoryInterface::class),
    $c->get(LetterService::class),
    $c->get(LetterTransformer::class),
    $c->get(AccessControl::class),
    $c->get(LetterAccess::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(LetterAttachmentRepositoryInterface::class),
    $c->get(\ArtaPharma\Domain\Repositories\FolderRepositoryInterface::class),
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
$container->bind(\ArtaPharma\Http\Controllers\OfficeFileController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\OfficeFileController(
    $c->get(\ArtaPharma\Domain\Repositories\OfficeFileRepositoryInterface::class),
    $c->get(AccessControl::class),
    $uploadDir,
));
$container->bind(MetaController::class, static fn (Container $c) => new MetaController(
    $c->get(UserRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(WorkflowRepositoryInterface::class),
    $c->get(\ArtaPharma\Services\RecipientPolicy::class),
));
$container->bind(\ArtaPharma\Http\Controllers\ProfileController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\ProfileController(
    $c->get(UserRepositoryInterface::class),
    $c->get(UserTransformer::class),
    $c->get(HasherInterface::class),
    $photoDir,
    $signatureDir,
));
$container->bind(\ArtaPharma\Http\Controllers\SettingsController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\SettingsController(
    $c->get(SettingsRepositoryInterface::class),
    $c->get(\ArtaPharma\Services\NumberingService::class),
    $c->get(AccessControl::class),
));
$container->bind(\ArtaPharma\Http\Controllers\EventController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\EventController(
    $c->get(EventRepositoryInterface::class),
    $c->get(UserRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\ArtaPharma\Http\Controllers\MessagingController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\MessagingController(
    $c->get(\ArtaPharma\Domain\Repositories\MessagingRuleRepositoryInterface::class),
    $c->get(DepartmentRepositoryInterface::class),
    $c->get(RoleRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\ArtaPharma\Http\Controllers\SecretariatController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\SecretariatController(
    $c->get(LetterService::class),
    $c->get(LetterRepositoryInterface::class),
    $c->get(LetterTransformer::class),
    $c->get(LetterAccess::class),
    $c->get(AccessControl::class),
    $c->get(\ArtaPharma\Domain\Repositories\SecretariatFolderRepositoryInterface::class),
));
$container->bind(\ArtaPharma\Http\Controllers\PermissionGrantController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\PermissionGrantController(
    $c->get(UserRepositoryInterface::class),
    $c->get(\ArtaPharma\Domain\Repositories\UserPermissionRepositoryInterface::class),
    $c->get(AccessControl::class),
));
$container->bind(\ArtaPharma\Http\Controllers\ChatController::class, static fn (Container $c) => new \ArtaPharma\Http\Controllers\ChatController(
    $c->get(\ArtaPharma\Domain\Repositories\ChatRepositoryInterface::class),
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
    (new \ArtaPharma\Infrastructure\Database\Migrator($container->get(Connection::class)))
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
