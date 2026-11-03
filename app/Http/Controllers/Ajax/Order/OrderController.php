<?php

namespace App\Http\Controllers\Ajax\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderIndexRequest;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Http\Resources\Order\OrderResource;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Orders\OrderCancelService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderTrackingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OrderPlacementService $placement,
        private readonly CheckoutRepositoryInterface $checkout,
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderCancelService $cancellation,
        private readonly OrderTrackingService $tracking,
    ) {}

    /** POST /order/place {address_id, delivery_type, payment_method: cod|online} */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        try {
            $result = $this->placement->place($request->user(), $request->validated());
        } catch (\App\Exceptions\ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            Log::error('Order placement failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return $this->error('api.server_error', 500);
        }

        $data = OrderResource::make($this->checkout->withRelations($result['order']))->resolve();

        // every order this checkout created (one per vendor); the fields above are those of the last one, as in the API
        $data['placed_orders'] = collect($result['placed'])->map(fn ($placed) => $this->checkout->summary($placed))->values()->all();

        return $this->success($data, 'api.order_placed');
    }

    /** GET /order/orders?order_status=&delivery_mode=quick|standard - the customer's orders, 15 a page, newest first */
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $page = $this->orders->paginateForUser($request->user()->id, $request->validated(), 15);

        return $this->success($page->through(fn ($order) => OrderResource::make($order)->resolve())->toArray(), 'api.orders_fetched');
    }

    /** GET /order/orders/{id} - one order with its bill, payments and refunds */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orders->findForDetail($request->user()->id, $id);

        return $order ? $this->success(OrderResource::make($order)->resolve(), 'api.order_details_fetched') : $this->error('api.order_not_found', 404);
    }

    /** POST /order/orders/{order_id}/cancel */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        try {
            return $this->success($this->cancellation->cancel($request->user(), $orderId), 'api.order_cancelled');
        } catch (\App\Exceptions\ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error('api.server_error', 500);
        }
    }

    /** GET /order/orders/{id}/delivery-location - where the delivery person is (Quick orders) */
    public function deliveryLocation(Request $request, int $id): JsonResponse
    {
        $result = $this->tracking->location($request->user(), $id);

        return $this->success($result['data'], $result['message']);
    }
}
