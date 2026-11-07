<?php

declare(strict_types=1);

use ArtaPharma\Core\Http\Router;
use ArtaPharma\Http\Controllers\AttachmentController;
use ArtaPharma\Http\Controllers\AuthController;
use ArtaPharma\Http\Controllers\DashboardController;
use ArtaPharma\Http\Controllers\DepartmentController;
use ArtaPharma\Http\Controllers\LetterController;
use ArtaPharma\Http\Controllers\MetaController;
use ArtaPharma\Http\Controllers\NotificationController;
use ArtaPharma\Http\Controllers\ProfileController;
use ArtaPharma\Http\Controllers\RoleController;
use ArtaPharma\Http\Controllers\TemplateController;
use ArtaPharma\Http\Controllers\UserController;
use ArtaPharma\Http\Controllers\WorkflowController;
use ArtaPharma\Http\Middleware\AuthenticateMiddleware;

/**
 * API route table. CORS is applied globally by the Application kernel (so
 * pre-flight OPTIONS works). Protected routes pass the Authenticate middleware;
 * authorization (RBAC) is enforced per-action by the controllers via AccessControl.
 */
return static function (Router $router): void {
    $router->group(['prefix' => '/api'], function (Router $r): void {
        // Public
        $r->post('/auth/login', AuthController::class, 'login');

        $auth = [AuthenticateMiddleware::class];

        $r->get('/auth/me', AuthController::class, 'me', $auth);
        $r->get('/dashboard', DashboardController::class, 'index', $auth);
        $r->get('/notifications', NotificationController::class, 'index', $auth);
        $r->get('/meta/compose', MetaController::class, 'compose', $auth);

        // Letters — order: static paths before the {id} wildcard.
        $r->get('/admin/letters', LetterController::class, 'adminAll', $auth);
        $r->get('/letters', LetterController::class, 'index', $auth);
        $r->get('/letters/archive', LetterController::class, 'archive', $auth);
        $r->post('/letters', LetterController::class, 'store', $auth);
        $r->put('/letters/{id}', LetterController::class, 'update', $auth);
        $r->delete('/letters/{id}', LetterController::class, 'destroy', $auth);
        $r->get('/letters/{id}', LetterController::class, 'show', $auth);
        $r->get('/letters/{id}/download', LetterController::class, 'download', $auth);
        $r->get('/letters/{id}/attachments/{aid}/download', LetterController::class, 'downloadAttachment', $auth);
        $r->get('/letters/{id}/notes/{nid}/download', LetterController::class, 'downloadNote', $auth);
        $r->post('/letters/{id}/approve', LetterController::class, 'approve', $auth);
        $r->post('/letters/{id}/reject', LetterController::class, 'reject', $auth);
        $r->post('/letters/{id}/refer', LetterController::class, 'refer', $auth);
        $r->post('/letters/{id}/reply', LetterController::class, 'reply', $auth);

        // کتابخانهٔ فایل‌های Word/Excel (مدیریت با ادمین، استفاده در ساخت نامه)
        $r->get('/office-files', \ArtaPharma\Http\Controllers\OfficeFileController::class, 'index', $auth);
        $r->post('/office-files', \ArtaPharma\Http\Controllers\OfficeFileController::class, 'store', $auth);
        $r->get('/office-files/{id}/download', \ArtaPharma\Http\Controllers\OfficeFileController::class, 'download', $auth);
        $r->delete('/office-files/{id}', \ArtaPharma\Http\Controllers\OfficeFileController::class, 'destroy', $auth);

        // دبیرخانه (secretariat)
        $r->get('/secretariat', \ArtaPharma\Http\Controllers\SecretariatController::class, 'index', $auth);
        $r->get('/secretariat/folders', \ArtaPharma\Http\Controllers\SecretariatController::class, 'folders', $auth);
        $r->get('/secretariat/last-number', \ArtaPharma\Http\Controllers\SecretariatController::class, 'lastNumber', $auth);
        $r->get('/secretariat/check-number', \ArtaPharma\Http\Controllers\SecretariatController::class, 'checkNumber', $auth);
        $r->post('/secretariat/folders', \ArtaPharma\Http\Controllers\SecretariatController::class, 'createFolder', $auth);
        $r->put('/secretariat/folders/{id}', \ArtaPharma\Http\Controllers\SecretariatController::class, 'renameFolder', $auth);
        $r->delete('/secretariat/folders/{id}', \ArtaPharma\Http\Controllers\SecretariatController::class, 'deleteFolder', $auth);
        $r->post('/secretariat/incoming', \ArtaPharma\Http\Controllers\SecretariatController::class, 'incoming', $auth);
        $r->post('/secretariat/outgoing', \ArtaPharma\Http\Controllers\SecretariatController::class, 'outgoing', $auth);
        $r->put('/secretariat/{id}/folder', \ArtaPharma\Http\Controllers\SecretariatController::class, 'fileLetter', $auth);

        // Per-user access grants (admin grants e.g. دبیرخانه to any user)
        $r->get('/permission-grants', \ArtaPharma\Http\Controllers\PermissionGrantController::class, 'index', $auth);
        $r->put('/permission-grants/user/{id}', \ArtaPharma\Http\Controllers\PermissionGrantController::class, 'update', $auth);

        // Per-user mailbox folders
        $r->get('/folders', \ArtaPharma\Http\Controllers\FolderController::class, 'index', $auth);
        $r->post('/folders', \ArtaPharma\Http\Controllers\FolderController::class, 'store', $auth);
        $r->post('/folders/unfile', \ArtaPharma\Http\Controllers\FolderController::class, 'unfile', $auth);
        $r->put('/folders/{id}', \ArtaPharma\Http\Controllers\FolderController::class, 'update', $auth);
        $r->delete('/folders/{id}', \ArtaPharma\Http\Controllers\FolderController::class, 'destroy', $auth);
        $r->post('/folders/{id}/letters', \ArtaPharma\Http\Controllers\FolderController::class, 'fileLetters', $auth);

        // Attachments (multipart upload)
        $r->post('/attachments', AttachmentController::class, 'store', $auth);

        // Staff chat (گفتگو) — gated by chat.use in the controller.
        $r->get('/chat/contacts', \ArtaPharma\Http\Controllers\ChatController::class, 'contacts', $auth);
        $r->get('/chat/conversations', \ArtaPharma\Http\Controllers\ChatController::class, 'index', $auth);
        $r->post('/chat/conversations', \ArtaPharma\Http\Controllers\ChatController::class, 'store', $auth);
        $r->delete('/chat/conversations/{id}', \ArtaPharma\Http\Controllers\ChatController::class, 'destroy', $auth);
        $r->get('/chat/conversations/{id}/messages', \ArtaPharma\Http\Controllers\ChatController::class, 'messages', $auth);
        $r->post('/chat/conversations/{id}/messages', \ArtaPharma\Http\Controllers\ChatController::class, 'send', $auth);
        $r->get('/chat/messages/{id}/download', \ArtaPharma\Http\Controllers\ChatController::class, 'download', $auth);

        // Self-service profile
        $r->get('/profile', ProfileController::class, 'show', $auth);
        $r->put('/profile', ProfileController::class, 'update', $auth);
        $r->post('/profile/password', ProfileController::class, 'changePassword', $auth);
        $r->post('/profile/photo', ProfileController::class, 'photo', $auth);
        $r->post('/profile/signature', ProfileController::class, 'signature', $auth);

        // Org settings (numbering pattern) — CEO only.
        $r->get('/settings', \ArtaPharma\Http\Controllers\SettingsController::class, 'index', $auth);
        $r->put('/settings', \ArtaPharma\Http\Controllers\SettingsController::class, 'update', $auth);

        // Per-unit messaging permissions — messaging.manage (CEO).
        $r->get('/messaging-rules', \ArtaPharma\Http\Controllers\MessagingController::class, 'index', $auth);
        $r->put('/messaging-rules/role/{id}', \ArtaPharma\Http\Controllers\MessagingController::class, 'updateRole', $auth);
        $r->put('/messaging-rules/{id}', \ArtaPharma\Http\Controllers\MessagingController::class, 'update', $auth);

        // Staff (CRUD gated by users.manage in the controller)
        $r->get('/users', UserController::class, 'index', $auth);
        $r->get('/users/online', UserController::class, 'online', $auth);
        $r->post('/users', UserController::class, 'store', $auth);
        $r->put('/users/{id}', UserController::class, 'update', $auth);
        $r->delete('/users/{id}', UserController::class, 'destroy', $auth);

        // Departments (view for executives; CRUD for departments.manage)
        $r->get('/departments', DepartmentController::class, 'index', $auth);
        $r->post('/departments', DepartmentController::class, 'store', $auth);
        $r->put('/departments/{id}', DepartmentController::class, 'update', $auth);
        $r->delete('/departments/{id}', DepartmentController::class, 'destroy', $auth);
        // Roles & access (roles.manage) — permissions catalogue before {id}.
        $r->get('/roles/permissions', RoleController::class, 'permissions', $auth);
        $r->get('/roles', RoleController::class, 'index', $auth);
        $r->post('/roles', RoleController::class, 'store', $auth);
        $r->put('/roles/{id}', RoleController::class, 'update', $auth);
        $r->delete('/roles/{id}', RoleController::class, 'destroy', $auth);

        // یادداشت کاربر — private per-user notes/reminders (any authenticated user).
        $r->get('/user-notes', \ArtaPharma\Http\Controllers\UserNoteController::class, 'index', $auth);
        $r->post('/user-notes', \ArtaPharma\Http\Controllers\UserNoteController::class, 'store', $auth);
        $r->put('/user-notes/{id}', \ArtaPharma\Http\Controllers\UserNoteController::class, 'update', $auth);
        $r->delete('/user-notes/{id}', \ArtaPharma\Http\Controllers\UserNoteController::class, 'destroy', $auth);

        // Org-wide events (رویدادها) — view for all; CRUD for events.manage.
        $r->get('/events', \ArtaPharma\Http\Controllers\EventController::class, 'index', $auth);
        $r->post('/events', \ArtaPharma\Http\Controllers\EventController::class, 'store', $auth);
        $r->put('/events/{id}', \ArtaPharma\Http\Controllers\EventController::class, 'update', $auth);
        $r->delete('/events/{id}', \ArtaPharma\Http\Controllers\EventController::class, 'destroy', $auth);

        // Templates (قالب‌ساز)
        $r->get('/templates', TemplateController::class, 'index', $auth);
        $r->post('/templates', TemplateController::class, 'store', $auth);
        $r->put('/templates/{id}', TemplateController::class, 'update', $auth);
        $r->post('/templates/{id}/duplicate', TemplateController::class, 'duplicate', $auth);
        $r->delete('/templates/{id}', TemplateController::class, 'destroy', $auth);

        // Workflows (builder — manage gated by workflow.manage)
        $r->get('/workflows', WorkflowController::class, 'index', $auth);
        $r->post('/workflows', WorkflowController::class, 'save', $auth);
        $r->put('/workflows/{id}', WorkflowController::class, 'save', $auth);
        $r->delete('/workflows/{id}', WorkflowController::class, 'destroy', $auth);
    });
};
