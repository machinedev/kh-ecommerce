<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UserService;
use App\Support\ApiResponse;
use App\Support\TemplateEngine;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserController
{
    public function __construct(private readonly UserService $users = new UserService())
    {
    }

    public function me(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->users->find($this->currentUserId($request)));
    }

    public function updateMe(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->users->update($this->currentUserId($request), (array) $request->getParsedBody()));
    }

    public function meActivity(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->users->activity($this->currentUserId($request)));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->users->findByPath((string) $args['id']));
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->users->update((int) $args['id'], (array) $request->getParsedBody()));
    }

    public function activity(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->users->activity((int) $args['id']));
    }

    public function products(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->users->products((int) $args['id']));
    }

    public function sellers(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->users->sellers());
    }

    public function card(Request $request, Response $response, array $args): Response
    {
        $user = $this->users->findByPath((string) $args['id']);
        $name = TemplateEngine::renderString((string) $user['display_name'], ['user' => $user]);

        $html = '<div class="profile-card">'
            . '<h2 class="profile-name">' . $name . '</h2>'
            . '<p class="profile-username">@' . htmlspecialchars((string) $user['username'], ENT_QUOTES) . '</p>'
            . '</div>';

        return ApiResponse::html($html);
    }

    private function currentUserId(Request $request): int
    {
        return (int) $request->getAttribute('user')['id'];
    }
}
