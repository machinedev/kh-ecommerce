<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CartService;
use App\Support\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CartController
{
    public function __construct(private readonly CartService $carts = new CartService())
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->carts->view($this->currentUserId($request)));
    }

    public function addItem(Request $request, Response $response): Response
    {
        return ApiResponse::success($this->carts->add($this->currentUserId($request), (array) $request->getParsedBody()), 201);
    }

    public function updateItem(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success(
            $this->carts->updateItem($this->currentUserId($request), (int) $args['id'], (array) $request->getParsedBody())
        );
    }

    public function deleteItem(Request $request, Response $response, array $args): Response
    {
        return ApiResponse::success($this->carts->removeItem($this->currentUserId($request), (int) $args['id']));
    }

    private function currentUserId(Request $request): int
    {
        return (int) $request->getAttribute('user')['id'];
    }
}
