<?php

declare(strict_types=1);

namespace ArtaPharma\Http\Controllers;

use ArtaPharma\Core\Http\Request;
use ArtaPharma\Core\Http\Response;
use ArtaPharma\Services\AuthService;
use ArtaPharma\View\UserTransformer;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserTransformer $users,
    ) {
    }

    public function login(Request $request): Response
    {
        $result = $this->auth->login(
            (string) $request->input('username', ''),
            (string) $request->input('password', ''),
        );

        return Response::ok([
            'token' => $result['token'],
            'user' => $this->users->transform($result['user']),
        ], 'خوش آمدید.');
    }

    public function me(Request $request): Response
    {
        return Response::ok($this->users->transform($this->user($request)));
    }
}
