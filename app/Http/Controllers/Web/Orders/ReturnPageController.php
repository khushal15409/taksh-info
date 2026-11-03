<?php

namespace App\Http\Controllers\Web\Orders;

use App\Exceptions\ApiException;
use App\Http\Controllers\Web\WebController;
use App\Http\Requests\Web\Orders\StoreReturnPageRequest;
use App\Services\Orders\OrderPageService;
use App\Services\Returns\ReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/** Return flow: the request form for a delivered order (/return?id=ORDER), sending it (with photos), and following a request (/returns/{id}). */
class ReturnPageController extends WebController
{
    public function __construct(
        private readonly OrderPageService $orders,
        private readonly ReturnService $returns,
    ) {}

    public function create(Request $request): View|RedirectResponse|Response
    {
        $user = $request->user();
        if (! $user) {
            return $this->page('orders.return-form', ['state' => 'guest']);
        }

        $id = max(0, (int) $request->query('id'));
        $order = $id ? $this->orders->detail($user, $id) : null;

        if (! $order) {
            return $this->notFound('orders.return-form', ['state' => 'missing']);
        }
        if ($order['order_status'] !== 'delivered') {
            return $this->page('orders.return-form', ['state' => 'not_delivered', 'order' => $order]);
        }

        return $this->page('orders.return-form', ['state' => 'form', 'order' => $order]);
    }

    public function store(StoreReturnPageRequest $request): RedirectResponse
    {
        $user = $request->user();
        $v = $request->validated();

        try {
            $return = $this->returns->create($user, [
                'order_id' => (int) $v['order_id'], 'order_item_id' => (int) $v['order_item_id'], 'quantity' => (int) $v['quantity'],
                'reason' => $v['reason'], 'resolution' => $v['resolution'],
            ]);
        } catch (ApiException $e) {
            $this->flash('error', Lang::has($e->getMessage()) ? __($e->getMessage()) : $e->getMessage());

            return redirect()->route('returns', ['id' => $v['order_id']])->withInput();
        }

        // the request exists now; if the photos cannot be stored it still stands and the customer is told
        $note = '';
        $files = $request->files5();
        if ($files) {
            try {
                $this->returns->uploadMedia($user, (int) $return['id'], $files);
            } catch (\Throwable $e) {
                $note = ' (the photos could not be uploaded - you can send them to support)';
            }
        }

        return $this->redirectWith('returns.show', 'success', '✅ Return request submitted'.$note, ['id' => $return['id']]);
    }

    /** GET /returns/{id} - the request, its pickup and every status it has been through */
    public function show(Request $request, int $id): View|Response
    {
        try {
            $return = $this->returns->show($request->user(), $id);
            $track = $this->returns->track($request->user(), $id);
        } catch (ApiException) {
            return $this->notFound('orders.return-show', ['return' => null, 'track' => null]);
        }

        return $this->page('orders.return-show', ['return' => $return, 'track' => $track]);
    }
}
